<?php
// API JSON de gestión de usuarios. Requiere Super Admin o el permiso gestionar_usuarios.
//   GET  ?accion=listar|stats|detalle
//   POST ?accion=crear|actualizar|roles|estado|restablecer|eliminar   (cuerpo JSON + cabecera X-CSRF-Token)
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

function responder(array $datos, int $codigo = 200): never
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function fallar(string $mensaje, int $codigo = 422, array $campos = []): never
{
    responder(['error' => $mensaje] + ($campos ? ['campos' => $campos] : []), $codigo);
}

$yo = usuario_actual();
if (!$yo) {
    fallar('Tu sesión expiró. Vuelve a iniciar sesión.', 401);
}
if (!puede_gestionar_usuarios($yo)) {
    fallar('No tienes permiso para gestionar usuarios.', 403);
}
$soy_superadmin = tiene_rol($yo, 'superadmin');

$accion = $_GET['accion'] ?? '';
$metodo = $_SERVER['REQUEST_METHOD'];
$datos = [];
if ($metodo === 'POST') {
    if (!csrf_valido()) {
        fallar('Tu sesión expiró. Recarga la página.', 419);
    }
    $datos = json_decode(file_get_contents('php://input'), true) ?: [];
}

// ---------- Helpers ----------

function formatear_usuario(array $u): array
{
    return [
        'id' => (int) $u['id'],
        'nombre' => $u['nombre'],
        'email' => $u['email'],
        'cargo' => $u['cargo'],
        'avatar_url' => $u['avatar_url'],
        'rol' => $u['rol'],
        'estado' => $u['estado'],
        'fecha_alta' => fecha_corta($u['creado_en']),
        'ultimo_acceso' => fecha_corta($u['ultimo_acceso'], true),
    ];
}

function buscar_usuario(int $id): array
{
    $usuario = cargar_usuario($id);
    if (!$usuario) {
        fallar('El usuario no existe.', 404);
    }
    return $usuario;
}

// Super Admins activos (por rol principal o adicional), sin contar a $excepto_id
function otros_superadmins_activos(int $excepto_id): int
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios u
        WHERE u.id <> ? AND u.estado = 'activo' AND u.eliminado_en IS NULL
          AND (u.rol = 'superadmin' OR EXISTS (SELECT 1 FROM usuario_roles r WHERE r.usuario_id = u.id AND r.rol = 'superadmin'))");
    $stmt->execute([$excepto_id]);
    return (int) $stmt->fetchColumn();
}

// Un gestor que no es Super Admin no puede modificar a un Super Admin
function proteger_superadmin(array $objetivo): void
{
    global $soy_superadmin;
    if (!$soy_superadmin && tiene_rol($objetivo, 'superadmin')) {
        fallar('Solo un Super Admin puede modificar a otro Super Admin.', 403);
    }
}

// Reglas al dejar a un usuario sin acceso de Super Admin (desactivar, eliminar o quitarle el rol)
function validar_perdida_superadmin(array $objetivo, string $mensaje_propio): void
{
    global $yo;
    if (!tiene_rol($objetivo, 'superadmin') || $objetivo['estado'] !== 'activo') {
        return;
    }
    if ($objetivo['id'] === $yo['id']) {
        fallar($mensaje_propio);
    }
    if (otros_superadmins_activos($objetivo['id']) === 0) {
        fallar('Debe existir siempre al menos un Super Admin activo.');
    }
}

function validar_datos_basicos(array $datos, ?int $id = null): array
{
    global $pdo;
    $limpio = [
        'nombre' => trim((string) ($datos['nombre'] ?? '')),
        'email' => strtolower(trim((string) ($datos['email'] ?? ''))),
        'cargo' => trim((string) ($datos['cargo'] ?? '')),
        'avatar_url' => trim((string) ($datos['avatar_url'] ?? '')),
        'estado' => $datos['estado'] ?? 'activo',
    ];
    $campos = [];
    if ($limpio['nombre'] === '' || largo_texto($limpio['nombre']) > 100) {
        $campos['nombre'] = 'Escribe un nombre (máximo 100 caracteres).';
    }
    if (!filter_var($limpio['email'], FILTER_VALIDATE_EMAIL) || strlen($limpio['email']) > 100) {
        $campos['email'] = 'Escribe un correo válido.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id <> ?");
        $stmt->execute([$limpio['email'], $id ?? 0]);
        if ($stmt->fetch()) {
            $campos['email'] = 'Este correo ya está registrado.';
        }
    }
    if (largo_texto($limpio['cargo']) > 100) {
        $campos['cargo'] = 'Máximo 100 caracteres.';
    }
    if ($limpio['avatar_url'] !== '' && (!filter_var($limpio['avatar_url'], FILTER_VALIDATE_URL)
            || !preg_match('#^https?://#i', $limpio['avatar_url']) || strlen($limpio['avatar_url']) > 255)) {
        $campos['avatar_url'] = 'Escribe una URL de imagen válida (http/https).';
    }
    if (!in_array($limpio['estado'], ['activo', 'inactivo'], true)) {
        $campos['estado'] = 'Estado no válido.';
    }
    if ($campos) {
        fallar('Revisa los campos marcados.', 422, $campos);
    }
    $limpio['cargo'] = $limpio['cargo'] ?: null;
    $limpio['avatar_url'] = $limpio['avatar_url'] ?: null;
    return $limpio;
}

function aplicar_estado(array $objetivo, string $estado): void
{
    global $pdo, $yo;
    if ($estado === $objetivo['estado']) {
        return;
    }
    if ($estado === 'inactivo') {
        if ($objetivo['id'] === $yo['id']) {
            fallar('No puedes desactivar tu propia cuenta.');
        }
        validar_perdida_superadmin($objetivo, 'No puedes desactivar tu propia cuenta.');
    }
    $pdo->prepare("UPDATE usuarios SET estado = ? WHERE id = ?")->execute([$estado, $objetivo['id']]);
    if ($estado === 'inactivo') {
        cerrar_sesiones_usuario($objetivo['id']);
    }
    registrar_actividad($objetivo['id'], $estado === 'activo' ? 'activado' : 'desactivado',
        $estado === 'activo' ? 'Cuenta activada' : 'Cuenta desactivada', $yo['id']);
}

// ---------- Acciones ----------

switch ("$metodo $accion") {

    case 'GET listar':
        $where = ['u.eliminado_en IS NULL'];
        $params = [];
        $buscar = trim($_GET['buscar'] ?? '');
        if ($buscar !== '') {
            $where[] = '(u.nombre LIKE ? OR u.email LIKE ?)';
            $comodin = '%' . addcslashes($buscar, '%_\\') . '%';
            array_push($params, $comodin, $comodin);
        }
        if (isset(ROLES[$_GET['rol'] ?? ''])) {
            $where[] = 'u.rol = ?';
            $params[] = $_GET['rol'];
        }
        if (in_array($_GET['estado'] ?? '', ['activo', 'inactivo'], true)) {
            $where[] = 'u.estado = ?';
            $params[] = $_GET['estado'];
        }
        foreach (['desde' => '>=', 'hasta' => '<'] as $campo => $operador) {
            $fecha = DateTime::createFromFormat('!Y-m-d', $_GET[$campo] ?? '');
            if ($fecha) {
                if ($campo === 'hasta') {
                    $fecha->modify('+1 day');
                }
                $where[] = "u.creado_en $operador ?";
                $params[] = $fecha->format('Y-m-d H:i:s');
            }
        }
        $columnas_orden = ['nombre' => 'u.nombre', 'email' => 'u.email', 'creado_en' => 'u.creado_en'];
        $orden = $columnas_orden[$_GET['orden'] ?? ''] ?? 'u.creado_en';
        $direccion = ($_GET['dir'] ?? '') === 'asc' ? 'ASC' : 'DESC';
        $por_pagina = (int) ($_GET['por_pagina'] ?? 10);
        $por_pagina = in_array($por_pagina, [10, 25, 50], true) ? $por_pagina : 10;
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $sql_where = implode(' AND ', $where);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios u WHERE $sql_where");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();
        $pagina = min($pagina, max(1, (int) ceil($total / $por_pagina)));
        $offset = ($pagina - 1) * $por_pagina;

        $stmt = $pdo->prepare("SELECT u.* FROM usuarios u WHERE $sql_where
                               ORDER BY $orden $direccion, u.id DESC LIMIT $por_pagina OFFSET $offset");
        $stmt->execute($params);
        responder([
            'usuarios' => array_map('formatear_usuario', $stmt->fetchAll()),
            'total' => $total,
            'pagina' => $pagina,
            'por_pagina' => $por_pagina,
        ]);

    case 'GET stats':
        // Comparación contra el cierre del mes anterior (usuarios que ya existían antes del día 1)
        $inicio_mes = $pdo->quote(date('Y-m-01 00:00:00'));
        $previo = "creado_en < $inicio_mes AND (eliminado_en IS NULL OR eliminado_en >= $inicio_mes)";
        $fila = $pdo->query("SELECT
                SUM(eliminado_en IS NULL) AS total,
                SUM($previo) AS total_prev,
                SUM(eliminado_en IS NULL AND rol = 'estudiante' AND estado = 'activo') AS alumnos,
                SUM($previo AND rol = 'estudiante' AND estado = 'activo') AS alumnos_prev,
                SUM(eliminado_en IS NULL AND rol = 'ponente') AS docentes,
                SUM($previo AND rol = 'ponente') AS docentes_prev,
                SUM(eliminado_en IS NULL AND rol = 'director') AS directores,
                SUM($previo AND rol = 'director') AS directores_prev
            FROM usuarios")->fetch();
        $tarjeta = function (string $clave) use ($fila) {
            $actual = (int) $fila[$clave];
            $anterior = (int) $fila[$clave . '_prev'];
            $tendencia = $anterior === 0 ? ($actual > 0 ? 100 : 0) : round(($actual - $anterior) / $anterior * 100, 1);
            return ['valor' => $actual, 'tendencia' => $tendencia];
        };
        responder([
            'total' => $tarjeta('total'),
            'alumnos' => $tarjeta('alumnos'),
            'docentes' => $tarjeta('docentes'),
            'directores' => $tarjeta('directores'),
        ]);

    case 'GET detalle':
        $usuario = buscar_usuario((int) ($_GET['id'] ?? 0));
        $stmt = $pdo->prepare("SELECT a.accion, a.detalle, a.creado_en, act.nombre AS actor
                               FROM actividad_log a LEFT JOIN usuarios act ON act.id = a.actor_id
                               WHERE a.usuario_id = ? ORDER BY a.creado_en DESC, a.id DESC LIMIT 15");
        $stmt->execute([$usuario['id']]);
        $actividad = array_map(fn ($a) => [
            'accion' => $a['accion'],
            'detalle' => $a['detalle'],
            'actor' => $a['actor'],
            'fecha' => fecha_corta($a['creado_en'], true),
        ], $stmt->fetchAll());
        responder(formatear_usuario($usuario) + [
            'roles' => $usuario['roles'],
            'permisos' => $usuario['permisos'],
            'actividad' => $actividad,
        ]);

    case 'POST crear':
        $limpio = validar_datos_basicos($datos);
        $rol = $datos['rol'] ?? '';
        if (!isset(ROLES[$rol])) {
            fallar('Revisa los campos marcados.', 422, ['rol' => 'Elige un rol principal.']);
        }
        if ($rol === 'superadmin' && !$soy_superadmin) {
            fallar('Solo un Super Admin puede crear otro Super Admin.', 403);
        }
        $invitacion = !empty($datos['invitacion']);
        $password = (string) ($datos['password'] ?? '');
        if (!$invitacion && strlen($password) < 8) {
            fallar('Revisa los campos marcados.', 422, ['password' => 'La contraseña temporal debe tener al menos 8 caracteres.']);
        }
        if ($invitacion) {
            $password = bin2hex(random_bytes(24));
        }
        $pdo->prepare("INSERT INTO usuarios (nombre, email, password, cargo, avatar_url, rol, estado) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$limpio['nombre'], $limpio['email'], password_hash($password, PASSWORD_BCRYPT),
                $limpio['cargo'], $limpio['avatar_url'], $rol, $limpio['estado']]);
        $nuevo = buscar_usuario((int) $pdo->lastInsertId());
        registrar_actividad($nuevo['id'], 'creado', 'Cuenta creada por un administrador', $yo['id']);
        $respuesta = ['mensaje' => 'Usuario creado', 'usuario' => formatear_usuario($nuevo)];
        if ($invitacion) {
            $enlace = crear_enlace_restablecer($nuevo, 48, 'Te invitaron a Cursos Online');
            $respuesta['mensaje'] = 'Usuario creado. Se envió la invitación por correo.';
            if (MODO_DESARROLLO) {
                $respuesta['enlace'] = $enlace;
            }
        }
        responder($respuesta, 201);

    case 'POST actualizar':
        $objetivo = buscar_usuario((int) ($datos['id'] ?? 0));
        proteger_superadmin($objetivo);
        $limpio = validar_datos_basicos($datos, $objetivo['id']);
        $pdo->beginTransaction();
        aplicar_estado($objetivo, $limpio['estado']);
        $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, cargo = ?, avatar_url = ? WHERE id = ?")
            ->execute([$limpio['nombre'], $limpio['email'], $limpio['cargo'], $limpio['avatar_url'], $objetivo['id']]);
        registrar_actividad($objetivo['id'], 'editado', 'Información actualizada', $yo['id']);
        $pdo->commit();
        responder(['mensaje' => 'Cambios guardados', 'usuario' => formatear_usuario(buscar_usuario($objetivo['id']))]);

    case 'POST roles':
        $objetivo = buscar_usuario((int) ($datos['id'] ?? 0));
        proteger_superadmin($objetivo);
        $rol = $datos['rol'] ?? '';
        if (!isset(ROLES[$rol])) {
            fallar('Elige un rol principal válido.');
        }
        // El rol principal no se repite en los adicionales
        $adicionales = array_values(array_diff(array_unique(array_filter((array) ($datos['roles'] ?? []), fn ($r) => isset(ROLES[$r]))), [$rol]));
        $permisos = array_values(array_unique(array_filter((array) ($datos['permisos'] ?? []), fn ($p) => isset(PERMISOS[$p]))));

        $tendra_superadmin = $rol === 'superadmin' || in_array('superadmin', $adicionales, true);
        if ($tendra_superadmin && !tiene_rol($objetivo, 'superadmin') && !$soy_superadmin) {
            fallar('Solo un Super Admin puede asignar el rol Super Admin.', 403);
        }
        if (!$tendra_superadmin) {
            validar_perdida_superadmin($objetivo, 'No puedes quitarte a ti mismo el rol Super Admin.');
        }

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE usuarios SET rol = ? WHERE id = ?")->execute([$rol, $objetivo['id']]);
        $pdo->prepare("DELETE FROM usuario_roles WHERE usuario_id = ?")->execute([$objetivo['id']]);
        $insertar = $pdo->prepare("INSERT INTO usuario_roles (usuario_id, rol) VALUES (?, ?)");
        foreach ($adicionales as $r) {
            $insertar->execute([$objetivo['id'], $r]);
        }
        $pdo->prepare("DELETE FROM usuario_permisos WHERE usuario_id = ?")->execute([$objetivo['id']]);
        $insertar = $pdo->prepare("INSERT INTO usuario_permisos (usuario_id, permiso) VALUES (?, ?)");
        foreach ($permisos as $p) {
            $insertar->execute([$objetivo['id'], $p]);
        }
        $nombres = array_map(fn ($r) => ROLES[$r]['nombre'], $adicionales);
        registrar_actividad($objetivo['id'], 'roles', 'Rol principal: ' . ROLES[$rol]['nombre']
            . ($nombres ? '; adicionales: ' . implode(', ', $nombres) : ''), $yo['id']);
        $pdo->commit();
        responder(['mensaje' => 'Cambios guardados']);

    case 'POST estado':
        $objetivo = buscar_usuario((int) ($datos['id'] ?? 0));
        proteger_superadmin($objetivo);
        $estado = $datos['estado'] ?? '';
        if (!in_array($estado, ['activo', 'inactivo'], true)) {
            fallar('Estado no válido.');
        }
        aplicar_estado($objetivo, $estado);
        responder(['mensaje' => $estado === 'activo' ? 'Usuario activado' : 'Usuario desactivado']);

    case 'POST restablecer':
        $objetivo = buscar_usuario((int) ($datos['id'] ?? 0));
        proteger_superadmin($objetivo);
        $enlace = crear_enlace_restablecer($objetivo, 24);
        registrar_actividad($objetivo['id'], 'recuperar_password', 'Se envió un enlace para restablecer la contraseña', $yo['id']);
        responder(['mensaje' => 'Se envió el enlace a ' . $objetivo['email']] + (MODO_DESARROLLO ? ['enlace' => $enlace] : []));

    case 'POST eliminar':
        $objetivo = buscar_usuario((int) ($datos['id'] ?? 0));
        proteger_superadmin($objetivo);
        if ($objetivo['id'] === $yo['id']) {
            fallar('No puedes eliminar tu propia cuenta.');
        }
        validar_perdida_superadmin($objetivo, 'No puedes eliminar tu propia cuenta.');
        // Borrado lógico: el registro se conserva para el historial
        $pdo->prepare("UPDATE usuarios SET eliminado_en = NOW(), estado = 'inactivo' WHERE id = ?")->execute([$objetivo['id']]);
        cerrar_sesiones_usuario($objetivo['id']);
        registrar_actividad($objetivo['id'], 'eliminado', 'Usuario eliminado', $yo['id']);
        responder(['mensaje' => 'Usuario eliminado']);

    default:
        fallar('Acción no válida.', 404);
}
