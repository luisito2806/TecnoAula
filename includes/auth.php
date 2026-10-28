<?php
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/iconos.php';

// En desarrollo los enlaces de recuperación se muestran en pantalla (no hay servicio de correo configurado)
const MODO_DESARROLLO = true;
// URL pública del sitio para los enlaces de correo, ej. 'https://tecnoaula.com'. Vacío = se toma del host actual
const APP_URL = '';

const RECORDAR_DIAS = 30;
const MAX_INTENTOS_EMAIL = 5;
const MAX_INTENTOS_IP = 20;
const VENTANA_INTENTOS_MIN = 15;

// Claves tal como están en la BD (usuarios.rol) => cómo se muestran
const ROLES = [
    'superadmin' => [
        'nombre' => 'Super Admin',
        'descripcion' => 'Gestión total de la plataforma, usuarios y roles.',
        'icono' => 'crown',
        'panel' => 'admin/dashboard.php',
    ],
    'director' => [
        'nombre' => 'Director',
        'descripcion' => 'Monitorea alumnos, asigna cursos y revisa reportes.',
        'icono' => 'users',
        'panel' => 'director/index.php',
    ],
    'ponente' => [
        'nombre' => 'Docente',
        'descripcion' => 'Crea y administra cursos, contenidos, foros y actividades.',
        'icono' => 'graduation-cap',
        'panel' => 'docente/cursos.php',
    ],
    'estudiante' => [
        'nombre' => 'Alumno',
        'descripcion' => 'Accede a sus cursos, revisa su avance y participa en foros.',
        'icono' => 'user',
        'panel' => 'alumno/cursos.php',
    ],
];

const PERMISOS = [
    'crear_cursos' => 'Puede crear cursos',
    'ver_reportes' => 'Puede ver reportes',
    'gestionar_usuarios' => 'Puede gestionar usuarios',
];

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

// ---------- Utilidades ----------

// Longitud en caracteres UTF-8 (el servidor no tiene la extensión mbstring)
function largo_texto(string $texto): int
{
    return (int) preg_match_all('/./us', $texto);
}

function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

// Ruta base del proyecto (funciona en la raíz del servidor o en una subcarpeta)
function base_url(): string
{
    static $base = null;
    if ($base === null) {
        $raiz = realpath(dirname(__DIR__));
        $script = realpath($_SERVER['SCRIPT_FILENAME']);
        $relativo = str_replace('\\', '/', substr($script, strlen($raiz)));
        $base = substr($_SERVER['SCRIPT_NAME'], 0, strlen($_SERVER['SCRIPT_NAME']) - strlen($relativo));
    }
    return $base;
}

function url(string $ruta = ''): string
{
    return base_url() . '/' . ltrim($ruta, '/');
}

function url_absoluta(string $ruta): string
{
    if (APP_URL !== '') {
        return rtrim(APP_URL, '/') . '/' . ltrim($ruta, '/');
    }
    $esquema = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
    return $esquema . '://' . $_SERVER['HTTP_HOST'] . url($ruta);
}

function redirigir(string $ruta): never
{
    header('Location: ' . url($ruta));
    exit;
}

// Recarga la página actual (patrón POST -> redirección -> GET)
function recargar(): never
{
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Valores de la tabla configuracion (nombre_plataforma, registro_abierto, catalogo_publico)
function config(string $clave, string $por_defecto = ''): string
{
    global $pdo;
    static $valores = null;
    if ($valores === null) {
        try {
            $valores = $pdo->query("SELECT clave, valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException) {
            $valores = []; // la migración de las fases 3 y 4 aún no se ejecuta
        }
    }
    return $valores[$clave] ?? $por_defecto;
}

function flash(?string $tipo = null, ?string $mensaje = null): ?array
{
    if ($tipo !== null) {
        $_SESSION['flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

function fecha_corta(?string $fecha, bool $con_hora = false): string
{
    if (!$fecha) {
        return '—';
    }
    $t = strtotime($fecha);
    $texto = date('j', $t) . ' ' . MESES[date('n', $t) - 1] . ' ' . date('Y', $t);
    return $con_hora ? $texto . ', ' . date('H:i', $t) : $texto;
}

function ip_cliente(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// ---------- CSRF ----------

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_valido(): bool
{
    $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($token) && hash_equals(csrf_token(), $token);
}

// ---------- Usuario actual ----------

function cargar_usuario(int $id): ?array
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, nombre, email, avatar_url, cargo, rol, estado, creado_en, actualizado_en, ultimo_acceso
                           FROM usuarios WHERE id = ? AND eliminado_en IS NULL");
    $stmt->execute([$id]);
    $usuario = $stmt->fetch();
    if (!$usuario) {
        return null;
    }
    $usuario['id'] = (int) $usuario['id'];
    $stmt = $pdo->prepare("SELECT rol FROM usuario_roles WHERE usuario_id = ?");
    $stmt->execute([$id]);
    $usuario['roles'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $stmt = $pdo->prepare("SELECT permiso FROM usuario_permisos WHERE usuario_id = ?");
    $stmt->execute([$id]);
    $usuario['permisos'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return $usuario;
}

// Se relee de la BD en cada petición: si el usuario se desactiva o elimina, su sesión deja de valer
function usuario_actual(bool $recargar = false): ?array
{
    static $usuario = false;
    if ($usuario !== false && !$recargar) {
        return $usuario;
    }
    $usuario = null;
    $id = $_SESSION['usuario_id'] ?? login_desde_cookie();
    if ($id) {
        $encontrado = cargar_usuario((int) $id);
        if ($encontrado && $encontrado['estado'] === 'activo') {
            $usuario = $encontrado;
        } else {
            cerrar_sesion();
        }
    }
    return $usuario;
}

function tiene_rol(array $usuario, string $rol): bool
{
    return $usuario['rol'] === $rol || in_array($rol, $usuario['roles'], true);
}

function tiene_permiso(array $usuario, string $permiso): bool
{
    return tiene_rol($usuario, 'superadmin') || in_array($permiso, $usuario['permisos'], true);
}

function puede_gestionar_usuarios(array $usuario): bool
{
    return tiene_permiso($usuario, 'gestionar_usuarios');
}

function ruta_panel(array $usuario): string
{
    return ROLES[$usuario['rol']]['panel'];
}

function requiere_login(): array
{
    $usuario = usuario_actual();
    if (!$usuario) {
        redirigir('index.php');
    }
    return $usuario;
}

function requiere_rol(string ...$roles): array
{
    $usuario = requiere_login();
    foreach ($roles as $rol) {
        if (tiene_rol($usuario, $rol)) {
            return $usuario;
        }
    }
    acceso_denegado();
}

function requiere_gestion_usuarios(): array
{
    $usuario = requiere_login();
    if (!puede_gestionar_usuarios($usuario)) {
        acceso_denegado();
    }
    return $usuario;
}

function acceso_denegado(): never
{
    http_response_code(403);
    require __DIR__ . '/403.php';
    exit;
}

// ---------- Inicio / cierre de sesión ----------

function iniciar_sesion(array $usuario, bool $recordar): void
{
    global $pdo;
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $usuario['id'];
    // actualizado_en = actualizado_en evita que el login cuente como edición del perfil
    $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW(), actualizado_en = actualizado_en WHERE id = ?")
        ->execute([$usuario['id']]);
    registrar_actividad($usuario['id'], 'login', 'Inicio de sesión');
    if ($recordar) {
        crear_token_recordar($usuario['id']);
    }
}

function cerrar_sesion(): void
{
    global $pdo;
    $cookie = $_COOKIE['recordar'] ?? '';
    if (str_contains($cookie, ':')) {
        $selector = explode(':', $cookie, 2)[0];
        $pdo->prepare("DELETE FROM recordar_tokens WHERE selector = ?")->execute([$selector]);
    }
    borrar_cookie_recordar();
    $_SESSION = [];
    session_regenerate_id(true);
}

function crear_token_recordar(int $usuario_id): void
{
    global $pdo;
    $selector = bin2hex(random_bytes(12));
    $validador = bin2hex(random_bytes(32));
    $pdo->prepare("INSERT INTO recordar_tokens (usuario_id, selector, token_hash, expira_en)
                   VALUES (?, ?, ?, NOW() + INTERVAL " . RECORDAR_DIAS . " DAY)")
        ->execute([$usuario_id, $selector, hash('sha256', $validador)]);
    setcookie('recordar', "$selector:$validador", [
        'expires' => time() + 86400 * RECORDAR_DIAS,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']),
    ]);
}

function borrar_cookie_recordar(): void
{
    if (isset($_COOKIE['recordar'])) {
        setcookie('recordar', '', ['expires' => time() - 3600, 'path' => '/']);
        unset($_COOKIE['recordar']);
    }
}

function login_desde_cookie(): ?int
{
    global $pdo;
    $cookie = $_COOKIE['recordar'] ?? '';
    if (!str_contains($cookie, ':')) {
        return null;
    }
    [$selector, $validador] = explode(':', $cookie, 2);
    $stmt = $pdo->prepare("SELECT * FROM recordar_tokens WHERE selector = ? AND expira_en > NOW()");
    $stmt->execute([$selector]);
    $token = $stmt->fetch();
    if (!$token || !hash_equals($token['token_hash'], hash('sha256', $validador))) {
        borrar_cookie_recordar();
        return null;
    }
    // Rotar el token en cada uso
    $pdo->prepare("DELETE FROM recordar_tokens WHERE id = ?")->execute([$token['id']]);
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $token['usuario_id'];
    crear_token_recordar($token['usuario_id']);
    return (int) $token['usuario_id'];
}

// Cierra las sesiones "Recordarme" de un usuario (al desactivarlo, eliminarlo o cambiar contraseña)
function cerrar_sesiones_usuario(int $usuario_id): void
{
    global $pdo;
    $pdo->prepare("DELETE FROM recordar_tokens WHERE usuario_id = ?")->execute([$usuario_id]);
}

// ---------- Límite de intentos ----------

function demasiados_intentos(string $email): bool
{
    global $pdo;
    $ventana = "NOW() - INTERVAL " . VENTANA_INTENTOS_MIN . " MINUTE";
    $stmt = $pdo->prepare("SELECT
            SUM(email = ? AND creado_en > $ventana) AS por_email,
            SUM(ip = ? AND creado_en > $ventana) AS por_ip
        FROM login_intentos WHERE email = ? OR ip = ?");
    $stmt->execute([$email, ip_cliente(), $email, ip_cliente()]);
    $fila = $stmt->fetch();
    return $fila['por_email'] >= MAX_INTENTOS_EMAIL || $fila['por_ip'] >= MAX_INTENTOS_IP;
}

function registrar_intento_fallido(string $email): void
{
    global $pdo;
    $pdo->prepare("INSERT INTO login_intentos (email, ip) VALUES (?, ?)")->execute([$email, ip_cliente()]);
    $pdo->exec("DELETE FROM login_intentos WHERE creado_en < NOW() - INTERVAL 1 DAY");
}

function limpiar_intentos(string $email): void
{
    global $pdo;
    $pdo->prepare("DELETE FROM login_intentos WHERE email = ?")->execute([$email]);
}

// ---------- Actividad, correo y recuperación ----------

function registrar_actividad(int $usuario_id, string $accion, string $detalle = '', ?int $actor_id = null): void
{
    global $pdo;
    $pdo->prepare("INSERT INTO actividad_log (usuario_id, actor_id, accion, detalle) VALUES (?, ?, ?, ?)")
        ->execute([$usuario_id, $actor_id, $accion, $detalle]);
}

function enviar_correo(string $para, string $asunto, string $cuerpo): bool
{
    $cabeceras = "Content-Type: text/plain; charset=UTF-8\r\nFrom: TecnoAula <no-responder@tecnoaula.com>";
    return @mail($para, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $cuerpo, $cabeceras);
}

// Crea un token de restablecimiento, envía el correo y devuelve el enlace
function crear_enlace_restablecer(array $usuario, int $horas = 1, string $asunto = 'Restablece tu contraseña'): string
{
    global $pdo;
    $token = bin2hex(random_bytes(32));
    $pdo->prepare("INSERT INTO password_resets (usuario_id, token_hash, expira_en) VALUES (?, ?, NOW() + INTERVAL $horas HOUR)")
        ->execute([$usuario['id'], hash('sha256', $token)]);
    $enlace = url_absoluta('restablecer.php?token=' . $token);
    $vigencia = $horas === 1 ? '1 hora' : "$horas horas";
    enviar_correo(
        $usuario['email'],
        "$asunto — TecnoAula",
        "Hola {$usuario['nombre']},\n\nPara crear tu nueva contraseña entra a este enlace (válido por $vigencia):\n\n$enlace\n\nSi no lo solicitaste, ignora este correo."
    );
    return $enlace;
}
