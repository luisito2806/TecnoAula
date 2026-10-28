<?php
require_once __DIR__ . '/../includes/cursos.php';

$usuario = requiere_rol('estudiante', 'superadmin');

$stmt = $pdo->prepare("SELECT c.id, c.titulo, t.titulo AS tarea, t.puntos, e.calificacion, e.comentario, e.calificado_en
    FROM inscripciones i
    JOIN cursos c ON c.id = i.curso_id AND c.estado = 'publicado' AND c.eliminado_en IS NULL
    LEFT JOIN tareas t ON t.curso_id = c.id
    LEFT JOIN entregas e ON e.tarea_id = t.id AND e.alumno_id = i.alumno_id
    WHERE i.alumno_id = ? ORDER BY c.titulo, t.fecha_entrega");
$stmt->execute([$usuario['id']]);
$cursos = [];
foreach ($stmt->fetchAll() as $fila) {
    $cursos[$fila['id']] ??= ['titulo' => $fila['titulo'], 'tareas' => []];
    if ($fila['tarea'] !== null) {
        $cursos[$fila['id']]['tareas'][] = $fila;
    }
}

$obtenidos = $posibles = 0;
foreach ($cursos as $c) {
    foreach ($c['tareas'] as $t) {
        if ($t['calificacion'] !== null) {
            $obtenidos += $t['calificacion'];
            $posibles += $t['puntos'];
        }
    }
}

panel_inicio($usuario, 'Calificaciones', 'calificaciones');
encabezado('Calificaciones', 'Tus resultados en cada curso.', ['Calificaciones' => null]);
?>
<section class="metricas">
    <article class="tarjeta metrica"><span class="metrica-icono"><?= icono('award', 20) ?></span><div><p class="metrica-titulo">Promedio general</p><p class="metrica-valor"><?= $posibles ? round($obtenidos / $posibles * 100) . '%' : '—' ?></p></div></article>
    <article class="tarjeta metrica"><span class="metrica-icono"><?= icono('book-open', 20) ?></span><div><p class="metrica-titulo">Cursos</p><p class="metrica-valor"><?= count($cursos) ?></p></div></article>
</section>
<?php if (!$cursos): ?>
    <div class="tarjeta estado-vacio"><span class="estado-vacio-icono"><?= icono('award', 24) ?></span><h3>Sin calificaciones</h3><p>Inscríbete en un curso para empezar.</p></div>
<?php endif; ?>
<div style="display: grid; gap: 16px">
    <?php foreach ($cursos as $id => $c):
        $calif = array_filter($c['tareas'], fn ($t) => $t['calificacion'] !== null);
        $pts = array_sum(array_column($calif, 'puntos')); ?>
        <section class="tarjeta">
            <div class="encabezado-tarjeta"><h2><a href="<?= url("curso.php?id=$id&tab=calificaciones") ?>" style="text-decoration:none"><?= e($c['titulo']) ?></a></h2>
                <span class="badge badge-claro"><?= $pts ? round(array_sum(array_column($calif, 'calificacion')) / $pts * 100) . '%' : 'Sin calificar' ?></span></div>
            <?php if (!$c['tareas']): ?><p class="vacio-mini">Este curso no tiene evaluaciones.</p><?php else: ?>
            <div class="tabla-scroll"><table class="tabla compacta">
                <thead><tr><th>Evaluación</th><th>Calificación</th><th>Comentario</th></tr></thead>
                <tbody>
                <?php foreach ($c['tareas'] as $t): ?>
                    <tr><td><?= e($t['tarea']) ?></td>
                        <td><?= $t['calificacion'] !== null ? '<strong>' . rtrim(rtrim($t['calificacion'], '0'), '.') . '</strong> / ' . (int) $t['puntos'] : '<span class="texto-tenue">—</span>' ?></td>
                        <td class="texto-secundario"><?= e($t['comentario'] ?? '') ?: '—' ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
<?php panel_fin(); ?>
