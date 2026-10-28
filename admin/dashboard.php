<?php
require_once __DIR__ . '/../includes/cursos.php';

$usuario = requiere_rol('superadmin');

$m = $pdo->query("SELECT
    (SELECT COUNT(*) FROM usuarios WHERE eliminado_en IS NULL AND estado = 'activo') AS usuarios_activos,
    (SELECT COUNT(*) FROM usuarios WHERE eliminado_en IS NULL AND creado_en >= NOW() - INTERVAL 30 DAY) AS usuarios_nuevos,
    (SELECT COUNT(*) FROM cursos WHERE eliminado_en IS NULL AND estado = 'publicado') AS cursos_publicados,
    (SELECT COUNT(*) FROM cursos WHERE eliminado_en IS NULL AND estado = 'borrador') AS cursos_borrador,
    (SELECT COUNT(*) FROM inscripciones i JOIN cursos c ON c.id = i.curso_id AND c.eliminado_en IS NULL) AS inscripciones,
    (SELECT COUNT(*) FROM entregas WHERE calificacion IS NULL) AS por_calificar,
    (SELECT COUNT(*) FROM usuarios WHERE eliminado_en IS NULL AND ultimo_acceso >= NOW() - INTERVAL 7 DAY) AS activos_semana")->fetch();

$por_rol = $pdo->query("SELECT rol, COUNT(*) FROM usuarios WHERE eliminado_en IS NULL GROUP BY rol")->fetchAll(PDO::FETCH_KEY_PAIR);
$total_usuarios = array_sum($por_rol) ?: 1;

$actividad = $pdo->query("SELECT a.detalle, a.accion, a.creado_en, u.nombre, u.id, u.avatar_url, act.nombre AS actor
    FROM actividad_log a JOIN usuarios u ON u.id = a.usuario_id LEFT JOIN usuarios act ON act.id = a.actor_id
    ORDER BY a.creado_en DESC, a.id DESC LIMIT 10")->fetchAll();
$cursos = array_slice(listar_cursos(), 0, 5);

panel_inicio($usuario, 'Dashboard', 'dashboard');
encabezado('Dashboard', 'Resumen general de la plataforma.', ['Dashboard' => null],
    '<a class="btn btn-primario" href="' . url('admin/usuarios.php') . '">' . icono('users', 17) . ' Gestionar usuarios</a>');
?>
<section class="metricas">
    <?php foreach ([
        ['Usuarios activos', $m['usuarios_activos'], 'users', $m['usuarios_nuevos'] . ' nuevos en 30 días'],
        ['Cursos publicados', $m['cursos_publicados'], 'book-open', $m['cursos_borrador'] . ' en borrador'],
        ['Inscripciones', $m['inscripciones'], 'layers', $m['activos_semana'] . ' usuarios entraron esta semana'],
        ['Entregas por calificar', $m['por_calificar'], 'clipboard-list', 'en todos los cursos'],
    ] as [$t, $v, $ico, $nota]): ?>
        <article class="tarjeta metrica"><span class="metrica-icono"><?= icono($ico, 20) ?></span><div><p class="metrica-titulo"><?= $t ?></p><p class="metrica-valor"><?= (int) $v ?></p><p class="metrica-tendencia"><?= e($nota) ?></p></div></article>
    <?php endforeach; ?>
</section>
<div class="columnas">
    <div style="display: grid; gap: 16px">
        <section class="tarjeta">
            <div class="encabezado-tarjeta"><h2>Cursos recientes</h2><a class="enlace" href="<?= url('admin/cursos.php') ?>">Ver todos</a></div>
            <?php if (!$cursos): ?><p class="vacio-mini">Aún no hay cursos.</p><?php endif; ?>
            <ul class="lista">
                <?php foreach ($cursos as $c): ?>
                    <li><span class="lista-icono"><?= icono('book-open', 16) ?></span>
                        <div class="lista-cuerpo"><strong><?= e($c['titulo']) ?></strong><small><?= e($c['docente_nombre'] ?? 'Sin docente') ?> · <?= (int) $c['total_alumnos'] ?> alumnos · <?= (int) $c['total_temas'] ?> temas</small></div>
                        <?= badge_estado_curso($c['estado']) ?>
                        <a class="btn-icono" href="<?= url('curso.php?id=' . $c['id']) ?>" aria-label="Ver curso"><?= icono('chevron-right', 17) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <section class="tarjeta">
            <div class="encabezado-tarjeta"><h2>Actividad reciente</h2></div>
            <?php if (!$actividad): ?><p class="vacio-mini">Sin actividad.</p><?php endif; ?>
            <ul class="lista">
                <?php foreach ($actividad as $a): ?>
                    <li><?= avatar_html($a, 'chico') ?><div class="lista-cuerpo"><strong><?= e($a['nombre']) ?></strong><small><?= e($a['detalle'] ?: $a['accion']) ?><?= $a['actor'] ? ' · por ' . e($a['actor']) : '' ?></small></div>
                        <span class="texto-tenue celda-fecha"><?= fecha_hora_texto($a['creado_en']) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
    <aside>
        <section class="tarjeta">
            <div class="encabezado-tarjeta"><h3>Usuarios por rol</h3><a class="enlace" href="<?= url('admin/roles.php') ?>">Roles</a></div>
            <ul class="lista">
                <?php foreach (ROLES as $clave => $rol): $n = (int) ($por_rol[$clave] ?? 0); ?>
                    <li><span class="rol-circulo"><?= icono($rol['icono'], 16) ?></span>
                        <div class="lista-cuerpo"><strong><?= $rol['nombre'] ?></strong><?= barra_progreso((int) round($n / $total_usuarios * 100), false) ?></div><strong><?= $n ?></strong></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <section class="tarjeta cuerpo-tarjeta">
            <h3 style="margin-bottom: 12px">Accesos rápidos</h3>
            <div class="materiales">
                <a class="chip" href="<?= url('admin/usuarios.php') ?>"><?= icono('users', 14) ?><span>Usuarios</span></a>
                <a class="chip" href="<?= url('admin/cursos.php') ?>"><?= icono('book-open', 14) ?><span>Cursos</span></a>
                <a class="chip" href="<?= url('reportes.php') ?>"><?= icono('bar-chart', 14) ?><span>Reportes</span></a>
                <a class="chip" href="<?= url('admin/configuracion.php') ?>"><?= icono('settings', 14) ?><span>Configuración</span></a>
            </div>
        </section>
    </aside>
</div>
<?php panel_fin(); ?>
