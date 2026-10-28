<?php
require_once __DIR__ . '/auth.php';

function iniciales(string $nombre): string
{
    $partes = preg_split('/\s+/', trim($nombre));
    $letras = '';
    foreach (array_slice($partes, 0, 2) as $parte) {
        $letras .= preg_match('/^./u', $parte, $m) ? $m[0] : '';
    }
    return strtoupper($letras ?: '?');
}

function avatar_html(array $usuario, string $tam = ''): string
{
    $clase = 'avatar ' . $tam;
    if (!empty($usuario['avatar_url'])) {
        return '<span class="' . $clase . '"><img src="' . e($usuario['avatar_url']) . '" alt=""></span>';
    }
    return '<span class="' . $clase . '">' . e(iniciales($usuario['nombre'])) . '</span>';
}

function logo_html(): string
{
    return '<a href="' . url('index.php') . '" class="logo">'
        . '<span class="logo-icono">' . icono('graduation-cap', 20) . '</span>'
        . '<span>' . e(strtoupper(config('nombre_plataforma', 'Cursos Online'))) . '</span></a>';
}

// Menú lateral según el rol principal: [clave, texto, icono, ruta]
function menu_panel(array $usuario): array
{
    $menus = [
        'superadmin' => [
            ['dashboard', 'Dashboard', 'layout-dashboard', 'admin/dashboard.php'],
            ['usuarios', 'Usuarios', 'users', 'admin/usuarios.php'],
            ['roles', 'Roles', 'shield', 'admin/roles.php'],
            ['cursos', 'Cursos', 'book-open', 'admin/cursos.php'],
            ['reportes', 'Reportes', 'bar-chart', 'reportes.php'],
            ['configuracion', 'Configuración', 'settings', 'admin/configuracion.php'],
        ],
        'director' => [
            ['alumnos', 'Alumnos', 'users', 'director/index.php'],
            ['cursos', 'Cursos', 'book-open', 'admin/cursos.php'],
            ['reportes', 'Reportes', 'bar-chart', 'reportes.php'],
            ['perfil', 'Perfil', 'user-circle', 'perfil.php'],
        ],
        'ponente' => [
            ['cursos', 'Mis cursos', 'book-open', 'docente/cursos.php'],
            ['alumnos', 'Alumnos', 'users', 'docente/alumnos.php'],
            ['reportes', 'Reportes', 'bar-chart', 'reportes.php'],
            ['mensajes', 'Mensajes', 'message-square', 'mensajes.php'],
            ['perfil', 'Perfil', 'user-circle', 'perfil.php'],
        ],
        'estudiante' => [
            ['cursos', 'Mis cursos', 'book-open', 'alumno/cursos.php'],
            ['calendario', 'Calendario', 'calendar', 'alumno/calendario.php'],
            ['tareas', 'Tareas', 'clipboard-list', 'alumno/tareas.php'],
            ['foros', 'Foros', 'message-circle', 'foros.php'],
            ['mensajes', 'Mensajes', 'message-square', 'mensajes.php'],
            ['calificaciones', 'Calificaciones', 'award', 'alumno/calificaciones.php'],
            ['perfil', 'Perfil', 'user-circle', 'perfil.php'],
        ],
    ];
    $menu = $menus[$usuario['rol']];
    // Accesos que dan los permisos adicionales a quien no los tiene por su rol
    if ($usuario['rol'] !== 'superadmin' && puede_gestionar_usuarios($usuario)) {
        $menu[] = ['usuarios', 'Usuarios', 'user-cog', 'admin/usuarios.php'];
    }
    if (!in_array($usuario['rol'], ['superadmin', 'ponente'], true) && tiene_permiso($usuario, 'crear_cursos')) {
        $menu[] = ['mis-cursos', 'Cursos que imparto', 'layers', 'docente/cursos.php'];
    }
    if ($usuario['rol'] === 'estudiante' && tiene_permiso($usuario, 'ver_reportes')) {
        $menu[] = ['reportes', 'Reportes', 'bar-chart', 'reportes.php'];
    }
    return $menu;
}

// Campana: [cantidad, ruta, texto]
function notificaciones(array $usuario): array
{
    global $pdo;
    if ($usuario['rol'] === 'estudiante') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tareas t
            JOIN inscripciones i ON i.curso_id = t.curso_id AND i.alumno_id = ?
            JOIN cursos c ON c.id = t.curso_id AND c.estado = 'publicado' AND c.eliminado_en IS NULL
            LEFT JOIN entregas en ON en.tarea_id = t.id AND en.alumno_id = ?
            WHERE en.id IS NULL AND t.fecha_entrega BETWEEN NOW() AND NOW() + INTERVAL 7 DAY");
        $stmt->execute([$usuario['id'], $usuario['id']]);
        return [(int) $stmt->fetchColumn(), 'alumno/tareas.php', 'tareas por entregar esta semana'];
    }
    if ($usuario['rol'] === 'ponente') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM entregas en
            JOIN tareas t ON t.id = en.tarea_id
            JOIN cursos c ON c.id = t.curso_id AND c.ponente_id = ? AND c.eliminado_en IS NULL
            WHERE en.calificacion IS NULL");
        $stmt->execute([$usuario['id']]);
        return [(int) $stmt->fetchColumn(), 'docente/cursos.php', 'entregas sin calificar'];
    }
    return [0, ruta_panel($usuario), ''];
}

function ruta_busqueda(array $usuario): string
{
    return match (true) {
        puede_gestionar_usuarios($usuario) => 'admin/usuarios.php',
        $usuario['rol'] === 'director' => 'admin/cursos.php',
        $usuario['rol'] === 'ponente' => 'docente/cursos.php',
        default => 'alumno/cursos.php',
    };
}

function cabecera_html(string $titulo, string $clase_body): void
{
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo) ?> — <?= e(config('nombre_plataforma', 'Cursos Online')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=DM+Mono:wght@400;500&family=DM+Sans:opsz,wght@9..40,400..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>?v=<?= filemtime(dirname(__DIR__) . '/assets/css/app.css') ?>">
</head>
<body class="<?= $clase_body ?>">
    <?php
}

function script_html(string $ruta): string
{
    return '<script src="' . url($ruta) . '?v=' . filemtime(dirname(__DIR__) . '/' . $ruta) . '"></script>';
}

function panel_inicio(array $usuario, string $titulo, string $activo): void
{
    $rol = ROLES[$usuario['rol']];
    [$avisos, $ruta_avisos, $texto_avisos] = notificaciones($usuario);
    cabecera_html($titulo, 'panel');
    ?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo"><?= logo_html() ?></div>
    <nav class="sidebar-nav">
        <?php foreach (menu_panel($usuario) as [$clave, $texto, $ico, $ruta]): ?>
            <a href="<?= url($ruta) ?>" class="sidebar-link<?= $clave === $activo ? ' activo' : '' ?>"<?= $clave === $activo ? ' aria-current="page"' : '' ?>><?= icono($ico, 18) ?><span><?= e($texto) ?></span></a>
        <?php endforeach; ?>
    </nav>
</aside>
<div class="sidebar-fondo" id="sidebar-fondo"></div>

<div class="panel-principal">
    <header class="topbar">
        <button class="btn-icono menu-movil" id="btn-menu" aria-label="Abrir menú"><?= icono('menu', 22) ?></button>
        <form class="buscador-global" action="<?= url(ruta_busqueda($usuario)) ?>" method="get" role="search">
            <?= icono('search', 17) ?>
            <input type="search" name="buscar" id="buscador-global" placeholder="Buscar usuarios, cursos, contenidos…" value="<?= e($_GET['buscar'] ?? '') ?>">
        </form>
        <div class="topbar-acciones">
            <a href="<?= url($ruta_avisos) ?>" class="btn-icono notificaciones" aria-label="Notificaciones" title="<?= $avisos ? e("$avisos $texto_avisos") : 'Sin novedades' ?>">
                <?= icono('bell', 19) ?><?php if ($avisos): ?><span class="contador"><?= $avisos > 9 ? '9+' : $avisos ?></span><?php endif; ?>
            </a>
            <details class="menu-usuario">
                <summary>
                    <?= avatar_html($usuario) ?>
                    <span class="menu-usuario-texto">
                        <strong><?= e($usuario['nombre']) ?></strong>
                        <small><?= e($rol['nombre']) ?></small>
                    </span>
                    <?= icono('chevron-down', 16) ?>
                </summary>
                <div class="menu-desplegable">
                    <a href="<?= url('perfil.php') ?>" class="menu-item"><?= icono('user-circle', 17) ?> Mi perfil</a>
                    <form action="<?= url('logout.php') ?>" method="post">
                        <?= campo_csrf() ?>
                        <button type="submit" class="menu-item"><?= icono('log-out', 17) ?> Cerrar sesión</button>
                    </form>
                </div>
            </details>
        </div>
    </header>
    <main class="contenido">
    <?php
}

function panel_fin(array $scripts = []): void
{
    $aviso = flash();
    ?>
    </main>
</div>
<div class="toasts" id="toasts" aria-live="polite">
    <?php if ($aviso): ?>
        <div class="toast toast-<?= e($aviso['tipo']) ?>"><?= icono($aviso['tipo'] === 'exito' ? 'check-circle' : 'alert-circle', 19) ?><span><?= e($aviso['mensaje']) ?></span></div>
    <?php endif; ?>
</div>
<dialog class="dialogo dialogo-chico" id="dlg-confirmar">
    <form method="dialog">
        <header class="dialogo-encabezado"><h2 id="confirmar-titulo">Confirmar</h2></header>
        <div class="dialogo-cuerpo"><p id="confirmar-texto"></p></div>
        <footer class="dialogo-pie">
            <button value="no" class="btn btn-contorno">Cancelar</button>
            <button value="si" class="btn btn-peligro">Confirmar</button>
        </footer>
    </form>
</dialog>
<?= script_html('assets/js/panel.js') ?>
<?php foreach ($scripts as $script): ?>
<?= script_html($script) ?>
<?php endforeach; ?>
</body>
</html>
    <?php
}

// Encabezado de página: migas de pan, título (puede llevar <span>), subtítulo y botones
function encabezado(string $titulo_html, string $subtitulo = '', array $migas = [], string $acciones = ''): void
{
    ?>
    <nav class="breadcrumb" aria-label="Ruta">
        <a href="<?= url('dashboard.php') ?>"><?= icono('home', 14) ?> Inicio</a>
        <?php foreach ($migas as $texto => $ruta): ?>
            <?= icono('chevron-right', 13) ?>
            <?php if ($ruta): ?><a href="<?= url($ruta) ?>"><?= e($texto) ?></a><?php else: ?><span><?= e($texto) ?></span><?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <div class="encabezado-pagina">
        <div>
            <h1><?= $titulo_html ?></h1>
            <?php if ($subtitulo): ?><p class="texto-secundario"><?= e($subtitulo) ?></p><?php endif; ?>
        </div>
        <?php if ($acciones): ?><div class="encabezado-acciones"><?= $acciones ?></div><?php endif; ?>
    </div>
    <?php
}

// Páginas de acceso (registro, recuperar, restablecer): header + tarjeta centrada
function acceso_inicio(string $titulo): void
{
    cabecera_html($titulo, 'acceso');
    ?>
<header class="header-publico">
    <?= logo_html() ?>
    <a href="<?= url('index.php') ?>" class="btn btn-contorno">Iniciar sesión</a>
</header>
<main class="acceso-centro">
    <?php
}

function acceso_fin(): void
{
    ?>
</main>
<?= script_html('assets/js/acceso.js') ?>
</body>
</html>
    <?php
}

// Selector "Iniciar sesión | Crear cuenta" en la parte superior de las tarjetas de acceso
function selector_acceso(string $activo): string
{
    if (config('registro_abierto', '1') !== '1') {
        return '';
    }
    $opciones = ['login' => ['Iniciar sesión', 'index.php#login'], 'registro' => ['Crear cuenta', 'registro.php']];
    $html = '<nav class="selector-acceso" aria-label="Acceso">';
    foreach ($opciones as $clave => [$texto, $ruta]) {
        $html .= '<a href="' . url($ruta) . '"' . ($clave === $activo ? ' class="activo" aria-current="page"' : '') . '>' . $texto . '</a>';
    }
    return $html . '</nav>';
}

function alerta_html(?array $alerta): string
{
    if (!$alerta || !$alerta['mensaje']) {
        return '';
    }
    $ico = $alerta['tipo'] === 'exito' ? 'check-circle' : 'alert-circle';
    // 'extra' es HTML ya preparado (ej. el enlace de recuperación en modo desarrollo)
    return '<div class="alerta alerta-' . e($alerta['tipo']) . '" role="alert">' . icono($ico, 18)
        . '<span>' . e($alerta['mensaje']) . ($alerta['extra'] ?? '') . '</span></div>';
}
