<?php
require_once __DIR__ . '/includes/cursos.php';

$usuario = requiere_login();

$cursos = listar_cursos(['alumno' => $usuario['id']]);
if (puede_crear_cursos($usuario)) {
    $cursos = array_merge($cursos, listar_cursos(['docente' => $usuario['id']]));
}
$cursos = array_values(array_column($cursos, null, 'id'));
$ids = array_map(fn ($c) => (int) $c['id'], $cursos);
$no_leidos = chat_no_leidos($usuario['id'], $ids);

$ultimos = [];
if ($ids) {
    $ultimos = $pdo->query("SELECT m.curso_id, m.contenido, m.creado_en, u.nombre FROM chat_mensajes m JOIN usuarios u ON u.id = m.usuario_id
        WHERE m.id IN (SELECT MAX(id) FROM chat_mensajes WHERE curso_id IN (" . implode(',', $ids) . ") GROUP BY curso_id)")->fetchAll();
    $ultimos = array_column($ultimos, null, 'curso_id');
}
// Primero los chats con actividad más reciente
usort($cursos, fn ($a, $b) => strcmp($ultimos[$b['id']]['creado_en'] ?? '', $ultimos[$a['id']]['creado_en'] ?? ''));

panel_inicio($usuario, 'Mensajes', 'mensajes');
encabezado('Mensajes', 'Chats grupales de tus cursos.', ['Mensajes' => null]);
?>
<section class="tarjeta">
    <?php if (!$cursos): ?>
        <div class="estado-vacio"><span class="estado-vacio-icono"><?= icono('message-square', 24) ?></span><h3>Sin conversaciones</h3><p>Cada curso tiene su chat; aparecerán aquí cuando estés inscrito o impartas uno.</p></div>
    <?php endif; ?>
    <ul class="lista">
        <?php foreach ($cursos as $c): $u = $ultimos[$c['id']] ?? null; $n = $no_leidos[$c['id']] ?? 0; ?>
            <li><span class="lista-icono"><?= icono('message-square', 16) ?></span>
                <a class="lista-cuerpo" href="<?= url('curso.php?id=' . $c['id'] . '&tab=chat') ?>" style="text-decoration:none"><strong><?= e($c['titulo']) ?></strong>
                    <small><?= $u ? e($u['nombre'] . ': ' . recortar_texto($u['contenido'], 80)) . ' · ' . fecha_hora_texto($u['creado_en']) : 'Sin mensajes todavía' ?></small></a>
                <?php if ($n): ?><span class="badge badge-claro"><?= $n ?> nuevos</span><?php endif; ?>
                <a class="btn-icono" href="<?= url('curso.php?id=' . $c['id'] . '&tab=chat') ?>" aria-label="Abrir chat"><?= icono('chevron-right', 17) ?></a></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php panel_fin(); ?>
