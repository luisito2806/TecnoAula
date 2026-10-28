<?php
// Funciones de cursos compartidas por los paneles de docente, alumno, director y administración
require_once __DIR__ . '/layout.php';

const MODALIDADES = ['en_linea' => 'En línea', 'presencial' => 'Presencial', 'mixta' => 'Mixta'];
const TIPOS_MATERIAL = [
    'video' => ['Video', 'play-circle'],
    'documento' => ['Documento', 'file-text'],
    'enlace' => ['Enlace', 'link'],
];

// ---------- Permisos ----------

function puede_crear_cursos(array $usuario): bool
{
    return tiene_rol($usuario, 'ponente') || tiene_permiso($usuario, 'crear_cursos');
}

function puede_supervisar_cursos(array $usuario): bool
{
    return tiene_rol($usuario, 'superadmin') || tiene_rol($usuario, 'director');
}

function puede_ver_reportes(array $usuario): bool
{
    return puede_supervisar_cursos($usuario) || tiene_rol($usuario, 'ponente') || tiene_permiso($usuario, 'ver_reportes');
}

function puede_editar_curso(array $usuario, array $curso): bool
{
    return tiene_rol($usuario, 'superadmin') || (int) $curso['ponente_id'] === $usuario['id'];
}

function esta_inscrito(int $alumno_id, int $curso_id): bool
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT 1 FROM inscripciones WHERE alumno_id = ? AND curso_id = ?");
    $stmt->execute([$alumno_id, $curso_id]);
    return (bool) $stmt->fetchColumn();
}

function puede_ver_curso(array $usuario, array $curso): bool
{
    return puede_editar_curso($usuario, $curso)
        || puede_supervisar_cursos($usuario)
        || ($curso['estado'] === 'publicado' && esta_inscrito($usuario['id'], $curso['id']));
}

// ---------- Consultas ----------

function obtener_curso(int $id): ?array
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT c.*, u.nombre AS docente_nombre, u.cargo AS docente_cargo, u.avatar_url AS docente_avatar
        FROM cursos c LEFT JOIN usuarios u ON u.id = c.ponente_id
        WHERE c.id = ? AND c.eliminado_en IS NULL");
    $stmt->execute([$id]);
    $curso = $stmt->fetch();
    if ($curso) {
        $curso['id'] = (int) $curso['id'];
    }
    return $curso ?: null;
}

// Curso de la URL (?id= o ?curso=) con su permiso; responde 404/403 si no aplica
function curso_de_url(array $usuario, bool $editar = false): array
{
    $curso = obtener_curso((int) ($_GET['id'] ?? $_GET['curso'] ?? 0));
    if (!$curso) {
        http_response_code(404);
        acceso_denegado();
    }
    if ($editar ? !puede_editar_curso($usuario, $curso) : !puede_ver_curso($usuario, $curso)) {
        acceso_denegado();
    }
    return $curso;
}

// Bloques con sus temas y materiales. Si se pasa $alumno_id, cada tema trae 'completado'
function estructura_curso(int $curso_id, ?int $alumno_id = null): array
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM bloques WHERE curso_id = ? ORDER BY orden, id");
    $stmt->execute([$curso_id]);
    $bloques = [];
    foreach ($stmt->fetchAll() as $b) {
        $bloques[$b['id']] = $b + ['temas' => []];
    }
    if (!$bloques) {
        return [];
    }
    $stmt = $pdo->prepare("SELECT t.*, " . ($alumno_id ? "(p.tema_id IS NOT NULL)" : "0") . " AS completado
        FROM temas t JOIN bloques b ON b.id = t.bloque_id
        " . ($alumno_id ? "LEFT JOIN progreso_temas p ON p.tema_id = t.id AND p.alumno_id = " . (int) $alumno_id : "") . "
        WHERE b.curso_id = ? ORDER BY t.orden, t.id");
    $stmt->execute([$curso_id]);
    $temas = [];
    foreach ($stmt->fetchAll() as $t) {
        $t['completado'] = (bool) $t['completado'];
        $t['materiales'] = [];
        $temas[$t['id']] = $t;
    }
    $stmt = $pdo->prepare("SELECT m.* FROM materiales m JOIN temas t ON t.id = m.tema_id JOIN bloques b ON b.id = t.bloque_id
        WHERE b.curso_id = ? ORDER BY m.orden, m.id");
    $stmt->execute([$curso_id]);
    foreach ($stmt->fetchAll() as $m) {
        $temas[$m['tema_id']]['materiales'][] = $m;
    }
    foreach ($temas as $t) {
        $bloques[$t['bloque_id']]['temas'][] = $t;
    }
    return array_values($bloques);
}

// Temas en orden de lectura (bloque por bloque)
function temas_en_orden(array $estructura): array
{
    return array_merge([], ...array_map(fn ($b) => $b['temas'], $estructura));
}

function total_temas(int $curso_id): int
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM temas t JOIN bloques b ON b.id = t.bloque_id WHERE b.curso_id = ?");
    $stmt->execute([$curso_id]);
    return (int) $stmt->fetchColumn();
}

// Porcentaje de avance por alumno inscrito: [alumno_id => ['completados' => n, 'porcentaje' => p]]
function progreso_alumnos(int $curso_id): array
{
    global $pdo;
    $total = total_temas($curso_id);
    $stmt = $pdo->prepare("SELECT i.alumno_id, COUNT(t.id) AS completados
        FROM inscripciones i
        LEFT JOIN progreso_temas p ON p.alumno_id = i.alumno_id
        LEFT JOIN temas t ON t.id = p.tema_id AND t.bloque_id IN (SELECT id FROM bloques WHERE curso_id = ?)
        WHERE i.curso_id = ? GROUP BY i.alumno_id");
    $stmt->execute([$curso_id, $curso_id]);
    $resultado = [];
    foreach ($stmt->fetchAll() as $fila) {
        $resultado[$fila['alumno_id']] = [
            'completados' => (int) $fila['completados'],
            'porcentaje' => $total ? (int) round($fila['completados'] / $total * 100) : 0,
        ];
    }
    return $resultado;
}

function progreso_alumno(int $alumno_id, int $curso_id): array
{
    $total = total_temas($curso_id);
    $completados = progreso_alumnos($curso_id)[$alumno_id]['completados'] ?? 0;
    return ['completados' => $completados, 'total' => $total, 'porcentaje' => $total ? (int) round($completados / $total * 100) : 0];
}

// Cursos con conteos. $filtro: 'docente' => id, 'alumno' => id, 'publicados' => true, 'buscar' => texto
function listar_cursos(array $filtro = []): array
{
    global $pdo;
    $where = ['c.eliminado_en IS NULL'];
    $params = [];
    if (isset($filtro['docente'])) {
        $where[] = 'c.ponente_id = ?';
        $params[] = $filtro['docente'];
    }
    if (isset($filtro['alumno'])) {
        $where[] = "c.estado = 'publicado' AND c.id IN (SELECT curso_id FROM inscripciones WHERE alumno_id = ?)";
        $params[] = $filtro['alumno'];
    }
    if (!empty($filtro['publicados'])) {
        $where[] = "c.estado = 'publicado'";
    }
    if (!empty($filtro['estado'])) {
        $where[] = 'c.estado = ?';
        $params[] = $filtro['estado'];
    }
    if (($filtro['buscar'] ?? '') !== '') {
        $where[] = '(c.titulo LIKE ? OR c.categoria LIKE ? OR u.nombre LIKE ?)';
        $comodin = '%' . addcslashes($filtro['buscar'], '%_\\') . '%';
        array_push($params, $comodin, $comodin, $comodin);
    }
    $stmt = $pdo->prepare("SELECT c.*, u.nombre AS docente_nombre,
            (SELECT COUNT(*) FROM inscripciones i WHERE i.curso_id = c.id) AS total_alumnos,
            (SELECT COUNT(*) FROM temas t JOIN bloques b ON b.id = t.bloque_id WHERE b.curso_id = c.id) AS total_temas,
            (SELECT COALESCE(SUM(t.duracion_min), 0) FROM temas t JOIN bloques b ON b.id = t.bloque_id WHERE b.curso_id = c.id) AS duracion_total,
            (SELECT COUNT(*) FROM bloques b WHERE b.curso_id = c.id) AS total_bloques
        FROM cursos c LEFT JOIN usuarios u ON u.id = c.ponente_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY c.estado = 'publicado' DESC, c.creado_en DESC, c.id DESC");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Mensajes de chat sin leer por curso para un usuario: [curso_id => n]
function chat_no_leidos(int $usuario_id, array $curso_ids): array
{
    global $pdo;
    if (!$curso_ids) {
        return [];
    }
    $marcas = implode(',', array_fill(0, count($curso_ids), '?'));
    $stmt = $pdo->prepare("SELECT m.curso_id, COUNT(*) AS n FROM chat_mensajes m
        LEFT JOIN chat_lecturas l ON l.curso_id = m.curso_id AND l.usuario_id = ?
        WHERE m.curso_id IN ($marcas) AND m.usuario_id <> ? AND m.id > COALESCE(l.ultimo_id, 0)
        GROUP BY m.curso_id");
    $stmt->execute([$usuario_id, ...$curso_ids, $usuario_id]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));
}

// Alumnos activos que se pueden inscribir en un curso
function alumnos_disponibles(int $curso_id): array
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT u.id, u.nombre, u.email FROM usuarios u
        WHERE u.eliminado_en IS NULL AND u.estado = 'activo'
          AND (u.rol = 'estudiante' OR EXISTS (SELECT 1 FROM usuario_roles r WHERE r.usuario_id = u.id AND r.rol = 'estudiante'))
          AND u.id NOT IN (SELECT alumno_id FROM inscripciones WHERE curso_id = ?)
        ORDER BY u.nombre");
    $stmt->execute([$curso_id]);
    return $stmt->fetchAll();
}

function docentes_disponibles(): array
{
    global $pdo;
    return $pdo->query("SELECT u.id, u.nombre FROM usuarios u
        WHERE u.eliminado_en IS NULL AND u.estado = 'activo'
          AND (u.rol = 'ponente' OR EXISTS (SELECT 1 FROM usuario_roles r WHERE r.usuario_id = u.id AND r.rol = 'ponente')
               OR EXISTS (SELECT 1 FROM usuario_permisos p WHERE p.usuario_id = u.id AND p.permiso = 'crear_cursos'))
        ORDER BY u.nombre")->fetchAll();
}

function inscribir(int $curso_id, int $alumno_id, ?int $actor_id = null): bool
{
    global $pdo;
    $stmt = $pdo->prepare("INSERT IGNORE INTO inscripciones (curso_id, alumno_id) VALUES (?, ?)");
    $stmt->execute([$curso_id, $alumno_id]);
    if ($stmt->rowCount()) {
        $titulo = $pdo->prepare("SELECT titulo FROM cursos WHERE id = ?");
        $titulo->execute([$curso_id]);
        registrar_actividad($alumno_id, 'inscripcion', 'Inscrito en ' . $titulo->fetchColumn(), $actor_id);
        return true;
    }
    return false;
}

// ---------- Formularios ----------

// Devuelve la acción del POST tras validar el token CSRF (o null si no es POST)
function accion_post(): ?string
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return null;
    }
    if (!csrf_valido()) {
        flash('error', 'Tu sesión expiró. Intenta de nuevo.');
        recargar();
    }
    return (string) ($_POST['accion'] ?? '');
}

// Corta un texto a $maximo caracteres UTF-8 (agrega … si se pasó y $puntos es true)
function recortar_texto(string $texto, int $maximo, bool $puntos = true): string
{
    if (largo_texto($texto) <= $maximo) {
        return $texto;
    }
    $corto = implode('', array_slice(preg_split('//u', $texto, -1, PREG_SPLIT_NO_EMPTY), 0, $maximo));
    return $puntos ? rtrim($corto) . '…' : $corto;
}

function texto_post(string $clave, int $maximo = 150): string
{
    return recortar_texto(trim((string) ($_POST[$clave] ?? '')), $maximo, false);
}

function entero_post(string $clave): int
{
    return (int) ($_POST[$clave] ?? 0);
}

function fecha_valida(string $valor, string $formato = 'Y-m-d'): ?string
{
    $fecha = DateTime::createFromFormat('!' . $formato, $valor);
    return $fecha && $fecha->format($formato) === $valor ? $valor : null;
}

function url_valida(string $url): bool
{
    return $url !== '' && filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $url) && strlen($url) <= 500;
}

// Detiene la acción mostrando el error como aviso
function error_formulario(string $mensaje): never
{
    flash('error', $mensaje);
    recargar();
}

function hecho(string $mensaje, ?string $ruta = null): never
{
    flash('exito', $mensaje);
    $ruta ? redirigir($ruta) : recargar();
}

// ---------- Presentación ----------

function duracion_texto(int $minutos): string
{
    if ($minutos < 60) {
        return "$minutos min";
    }
    $resto = $minutos % 60;
    return intdiv($minutos, 60) . ' h' . ($resto ? " $resto min" : '');
}

function fecha_hora_texto(?string $fecha): string
{
    return fecha_corta($fecha, true);
}

// Tiempo restante hasta una fecha: "en 3 días", "hoy", "venció hace 2 días"
function plazo_texto(?string $fecha): string
{
    if (!$fecha) {
        return 'Sin fecha límite';
    }
    $dias = (int) floor((strtotime(date('Y-m-d', strtotime($fecha))) - strtotime(date('Y-m-d'))) / 86400);
    return match (true) {
        $dias === 0 => strtotime($fecha) < time() ? 'Venció hoy' : 'Vence hoy',
        $dias === 1 => 'Vence mañana',
        $dias > 1 => "Vence en $dias días",
        $dias === -1 => 'Venció ayer',
        default => 'Venció hace ' . abs($dias) . ' días',
    };
}

// Miniatura 16:9 del curso (imagen o portada monocromática con iniciales)
function miniatura_html(array $curso, string $etiqueta = ''): string
{
    $html = '<div class="miniatura">';
    if (!empty($curso['imagen_url'])) {
        $html .= '<img src="' . e($curso['imagen_url']) . '" alt="" loading="lazy">';
    } else {
        $html .= '<div class="miniatura-portada"><span class="miniatura-iniciales">' . e(iniciales($curso['titulo'])) . '</span>'
            . '<span class="miniatura-categoria">' . e($curso['categoria'] ?: 'Curso') . '</span></div>';
    }
    if ($etiqueta !== '') {
        $html .= '<span class="duracion">' . e($etiqueta) . '</span>';
    }
    return $html . '</div>';
}

function barra_progreso(int $porcentaje, bool $con_texto = true): string
{
    $porcentaje = max(0, min(100, $porcentaje));
    return '<div class="progreso" role="progressbar" aria-valuenow="' . $porcentaje . '" aria-valuemin="0" aria-valuemax="100">'
        . '<div class="progreso-barra"><span style="width:' . $porcentaje . '%"></span></div>'
        . ($con_texto ? '<span class="progreso-texto">' . $porcentaje . '%</span>' : '') . '</div>';
}

function badge_estado_curso(string $estado): string
{
    return '<span class="badge ' . ($estado === 'publicado' ? 'badge-claro' : 'badge-tenue') . '"><i class="punto"></i>'
        . ($estado === 'publicado' ? 'Publicado' : 'Borrador') . '</span>';
}

function badge_rol(string $rol): string
{
    return '<span class="badge">' . icono(ROLES[$rol]['icono'], 13) . e(ROLES[$rol]['nombre']) . '</span>';
}

// Texto con saltos de línea y enlaces clicables
function texto_html(?string $texto): string
{
    $seguro = e($texto);
    $seguro = preg_replace('#\bhttps?://[^\s<]+#i', '<a href="$0" target="_blank" rel="noopener" class="enlace">$0</a>', $seguro);
    return nl2br($seguro);
}

// URL de inserción para videos de YouTube; null si no es un video reconocible
function video_embebido(string $url): ?string
{
    if (preg_match('#(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([\w-]{11})#', $url, $m)) {
        return 'https://www.youtube-nocookie.com/embed/' . $m[1];
    }
    return null;
}

// Tarjeta de curso (estilo lección en video) con enlace
function tarjeta_curso(array $curso, string $enlace, string $pie = ''): string
{
    $etiqueta = $curso['duracion_total'] ? duracion_texto((int) $curso['duracion_total']) : $curso['total_temas'] . ' temas';
    return '<a class="tarjeta-curso" href="' . e($enlace) . '">' . miniatura_html($curso, $etiqueta)
        . '<div class="tarjeta-curso-cuerpo"><h3>' . e($curso['titulo']) . '</h3>'
        . '<p>' . e($curso['docente_nombre'] ?? 'Sin docente asignado') . '</p>' . $pie . '</div></a>';
}
