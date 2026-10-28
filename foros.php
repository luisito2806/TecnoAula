<?php
require_once __DIR__ . '/includes/cursos.php';

$usuario = requiere_login();

// Cursos donde participa: inscrito, los que imparte o todos si supervisa
$ids = array_column(listar_cursos(['alumno' => $usuario['id']]), 'id');
if (puede_crear_cursos($usuario)) {
    $ids = array_merge($ids, array_column(listar_cursos(['docente' => $usuario['id']]), 'id'));
}
if (puede_supervisar_cursos($usuario)) {
    $ids = array_column(listar_cursos(), 'id');
}
$ids = array_map('intval', array_unique($ids));

$foros = [];
if ($ids) {
    $foros = $pdo->query("SELECT f.*, c.titulo AS curso, COUNT(m.id) AS mensajes, MAX(m.creado_en) AS ultima
        FROM foros f JOIN cursos c ON c.id = f.curso_id LEFT JOIN foro_mensajes m ON m.foro_id = f.id
        WHERE f.curso_id IN (" . implode(',', $ids) . ") GROUP BY f.id
        ORDER BY COALESCE(MAX(m.creado_en), f.creado_en) DESC")->fetchAll();
}

panel_inicio($usuario, 'Foros', 'foros');
encabezado('Foros', 'Debates y dudas de tus cursos.', ['Foros' => null]);
?>
<section class="tarjeta">
    <?php if (!$foros): ?>
        <div class="estado-vacio"><span class="estado-vacio-icono"><?= icono('message-circle', 24) ?></span><h3>No hay foros todavía</h3><p>Cuando los docentes abran foros en tus cursos aparecerán aquí.</p></div>
    <?php endif; ?>
    <ul class="lista">
        <?php foreach ($foros as $f): ?>
            <li><span class="lista-icono"><?= icono('message-circle', 16) ?></span>
                <a class="lista-cuerpo" href="<?= url('foro.php?id=' . $f['id']) ?>" style="text-decoration:none"><strong><?= e($f['titulo']) ?></strong>
                    <small><?= e($f['curso']) ?> · <?= (int) $f['mensajes'] ?> mensajes<?= $f['ultima'] ? ' · última actividad ' . fecha_hora_texto($f['ultima']) : '' ?></small></a>
                <a class="btn-icono" href="<?= url('foro.php?id=' . $f['id']) ?>" aria-label="Abrir foro"><?= icono('chevron-right', 17) ?></a></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php panel_fin(); ?>
