<?php
require_once __DIR__ . '/../includes/cursos.php';

$usuario = requiere_rol('estudiante', 'superadmin');

if (accion_post() === 'inscribirse') {
    $curso = obtener_curso(entero_post('curso_id'));
    if (!$curso || $curso['estado'] !== 'publicado') {
        error_formulario('El curso no está disponible.');
    }
    inscribir($curso['id'], $usuario['id']);
    hecho('Te inscribiste en ' . $curso['titulo'], 'curso.php?id=' . $curso['id']);
}

$buscar = trim($_GET['buscar'] ?? '');
$mis_cursos = listar_cursos(['alumno' => $usuario['id'], 'buscar' => $buscar]);
$inscritos = array_column(listar_cursos(['alumno' => $usuario['id']]), 'id');
$disponibles = array_filter(listar_cursos(['publicados' => true, 'buscar' => $buscar]), fn ($c) => !in_array($c['id'], $inscritos));

foreach ($mis_cursos as &$c) {
    $c['progreso'] = progreso_alumno($usuario['id'], (int) $c['id']);
}
unset($c);

// Curso para "Continuar aprendiendo": el último en el que avanzó y que no ha terminado
$stmt = $pdo->prepare("SELECT b.curso_id FROM progreso_temas p JOIN temas t ON t.id = p.tema_id JOIN bloques b ON b.id = t.bloque_id
    WHERE p.alumno_id = ? ORDER BY p.completado_en DESC LIMIT 20");
$stmt->execute([$usuario['id']]);
$recientes = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
$continuar = null;
foreach (array_merge($recientes, array_map(fn ($c) => (int) $c['id'], $mis_cursos)) as $id) {
    foreach ($mis_cursos as $c) {
        if ((int) $c['id'] === $id && $c['progreso']['porcentaje'] < 100 && $c['progreso']['total'] > 0) {
            $continuar = $c;
            break 2;
        }
    }
}
if ($continuar) {
    foreach (temas_en_orden(estructura_curso((int) $continuar['id'], $usuario['id'])) as $t) {
        if (!$t['completado']) {
            $continuar['siguiente'] = $t;
            break;
        }
    }
}

panel_inicio($usuario, 'Mis cursos', 'cursos');
encabezado('Hola, ' . e(explode(' ', $usuario['nombre'])[0]), 'Revisa tus cursos, tu avance y sigue aprendiendo.', ['Mis cursos' => null]);
?>
<?php if ($continuar): ?>
    <section class="banner-promo banner-panel">
        <span class="banner-promo-destacado"><?= $continuar['progreso']['porcentaje'] ?>% completado</span>
        <span class="banner-promo-texto"><?= e($continuar['titulo']) ?> · <?= e($continuar['siguiente']['titulo']) ?></span>
        <a class="btn btn-gradiente" href="<?= url('curso.php?id=' . $continuar['id'] . '&tema=' . $continuar['siguiente']['id']) ?>">Continuar aprendiendo <?= icono('arrow-right', 16) ?></a>
    </section>
<?php endif; ?>

<form class="barra-filtros" method="get">
    <label class="input-icono"><?= icono('search', 17) ?><input type="search" name="buscar" value="<?= e($buscar) ?>" placeholder="Buscar cursos…" aria-label="Buscar cursos"></label>
    <button class="btn btn-contorno">Buscar</button>
</form>

<section class="seccion">
    <div class="titulo-seccion"><h2>Mis cursos</h2><span class="texto-tenue"><?= count($mis_cursos) ?> inscritos</span></div>
    <?php if (!$mis_cursos): ?>
        <div class="tarjeta estado-vacio"><span class="estado-vacio-icono"><?= icono('book-open', 24) ?></span>
            <h3><?= $buscar ? 'Ningún curso coincide' : 'Aún no estás inscrito en ningún curso' ?></h3><p>Explora los cursos disponibles abajo e inscríbete.</p></div>
    <?php else: ?>
        <div class="grid-cursos">
            <?php foreach ($mis_cursos as $c): ?>
                <?= tarjeta_curso($c, url('curso.php?id=' . $c['id']),
                    barra_progreso($c['progreso']['porcentaje']) . '<p class="tarjeta-curso-pie"><span>' . $c['progreso']['completados'] . ' de ' . $c['progreso']['total'] . ' temas</span><span>' . ($c['progreso']['porcentaje'] === 100 ? 'Terminado' : 'En curso') . '</span></p>') ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($disponibles): ?>
    <section class="seccion">
        <div class="titulo-seccion"><h2>Cursos disponibles</h2><span class="texto-tenue">Inscríbete gratis</span></div>
        <div class="grid-cursos">
            <?php foreach ($disponibles as $c): ?>
                <div class="tarjeta-curso">
                    <?= miniatura_html($c, $c['duracion_total'] ? duracion_texto((int) $c['duracion_total']) : $c['total_temas'] . ' temas') ?>
                    <div class="tarjeta-curso-cuerpo"><h3><?= e($c['titulo']) ?></h3><p><?= e($c['docente_nombre'] ?? 'Sin docente') ?></p></div>
                    <form method="post" style="margin-top: auto"><?= campo_csrf() ?><input type="hidden" name="accion" value="inscribirse"><input type="hidden" name="curso_id" value="<?= $c['id'] ?>">
                        <button class="btn btn-contorno btn-bloque"><span class="spinner"></span><?= icono('plus', 16) ?> Inscribirme</button></form>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
<?php panel_fin(); ?>
