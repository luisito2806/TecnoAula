<?php
require_once __DIR__ . '/../includes/cursos.php';

$usuario = requiere_rol('estudiante', 'superadmin');

$stmt = $pdo->prepare("SELECT t.*, c.titulo AS curso, e.id AS entrega_id, e.entregado_en, e.calificacion
    FROM tareas t
    JOIN cursos c ON c.id = t.curso_id AND c.estado = 'publicado' AND c.eliminado_en IS NULL
    JOIN inscripciones i ON i.curso_id = c.id AND i.alumno_id = ?
    LEFT JOIN entregas e ON e.tarea_id = t.id AND e.alumno_id = ?
    ORDER BY t.fecha_entrega IS NULL, t.fecha_entrega");
$stmt->execute([$usuario['id'], $usuario['id']]);
$tareas = $stmt->fetchAll();

$grupos = [
    'pendientes' => ['Por entregar', array_filter($tareas, fn ($t) => !$t['entrega_id'] && (!$t['fecha_entrega'] || strtotime($t['fecha_entrega']) >= time()))],
    'vencidas' => ['Vencidas sin entregar', array_filter($tareas, fn ($t) => !$t['entrega_id'] && $t['fecha_entrega'] && strtotime($t['fecha_entrega']) < time())],
    'entregadas' => ['Entregadas', array_filter($tareas, fn ($t) => (bool) $t['entrega_id'])],
];
$filtro = isset($grupos[$_GET['ver'] ?? '']) ? $_GET['ver'] : 'pendientes';

panel_inicio($usuario, 'Tareas', 'tareas');
encabezado('Tareas', 'Todas las evaluaciones de tus cursos.', ['Tareas' => null]);
?>
<nav class="tabs">
    <?php foreach ($grupos as $clave => [$texto, $lista]): ?>
        <a href="?ver=<?= $clave ?>" class="<?= $filtro === $clave ? 'activa' : '' ?>"><?= $texto ?> <span class="badge"><?= count($lista) ?></span></a>
    <?php endforeach; ?>
</nav>
<section class="tarjeta">
    <?php if (!$grupos[$filtro][1]): ?>
        <div class="estado-vacio"><span class="estado-vacio-icono"><?= icono('check-circle', 24) ?></span><h3>Nada por aquí</h3><p><?= $filtro === 'pendientes' ? 'No tienes tareas por entregar. ¡Bien hecho!' : 'No hay tareas en esta sección.' ?></p></div>
    <?php endif; ?>
    <ul class="lista">
        <?php foreach ($grupos[$filtro][1] as $t): ?>
            <li><span class="lista-icono"><?= icono('clipboard-list', 16) ?></span>
                <div class="lista-cuerpo"><strong><?= e($t['titulo']) ?></strong>
                    <small><?= e($t['curso']) ?> · <?= $t['entrega_id'] ? 'Entregada ' . fecha_hora_texto($t['entregado_en']) : plazo_texto($t['fecha_entrega']) . ' · ' . fecha_hora_texto($t['fecha_entrega']) ?></small></div>
                <?php if ($t['calificacion'] !== null): ?><span class="badge badge-claro"><?= rtrim(rtrim($t['calificacion'], '0'), '.') ?>/<?= (int) $t['puntos'] ?></span>
                <?php elseif ($t['entrega_id']): ?><span class="badge"><i class="punto"></i>Por calificar</span><?php endif; ?>
                <a class="btn btn-contorno btn-chico" href="<?= url('curso.php?id=' . $t['curso_id'] . '&tab=tareas') ?>"><?= $t['entrega_id'] ? 'Ver' : 'Entregar' ?></a></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php panel_fin(); ?>
