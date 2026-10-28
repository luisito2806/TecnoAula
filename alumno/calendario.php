<?php
require_once __DIR__ . '/../includes/cursos.php';

$usuario = requiere_rol('estudiante', 'superadmin');

$mes = DateTime::createFromFormat('!Y-m', $_GET['mes'] ?? '') ?: new DateTime('first day of this month');
$mes->modify('first day of this month')->setTime(0, 0);
$inicio = (clone $mes)->modify('monday this week');
$fin = (clone $mes)->modify('last day of this month')->modify('sunday this week');

$stmt = $pdo->prepare("SELECT t.id, t.titulo, t.fecha_entrega, t.curso_id, c.titulo AS curso, e.id AS entrega_id
    FROM tareas t
    JOIN cursos c ON c.id = t.curso_id AND c.estado = 'publicado' AND c.eliminado_en IS NULL
    JOIN inscripciones i ON i.curso_id = c.id AND i.alumno_id = ?
    LEFT JOIN entregas e ON e.tarea_id = t.id AND e.alumno_id = i.alumno_id
    WHERE t.fecha_entrega BETWEEN ? AND ?");
$stmt->execute([$usuario['id'], $inicio->format('Y-m-d 00:00:00'), $fin->format('Y-m-d 23:59:59')]);
$eventos = [];
foreach ($stmt->fetchAll() as $t) {
    $eventos[substr($t['fecha_entrega'], 0, 10)][] = ['texto' => date('H:i', strtotime($t['fecha_entrega'])) . ' ' . $t['titulo'], 'titulo' => $t['curso'] . ': ' . $t['titulo'],
        'url' => url('curso.php?id=' . $t['curso_id'] . '&tab=tareas'), 'clase' => $t['entrega_id'] ? 'entregada' : ''];
}
// Inicio de cursos
$stmt = $pdo->prepare("SELECT c.id, c.titulo, c.fecha_inicio FROM cursos c JOIN inscripciones i ON i.curso_id = c.id AND i.alumno_id = ?
    WHERE c.estado = 'publicado' AND c.eliminado_en IS NULL AND c.fecha_inicio BETWEEN ? AND ?");
$stmt->execute([$usuario['id'], $inicio->format('Y-m-d'), $fin->format('Y-m-d')]);
foreach ($stmt->fetchAll() as $c) {
    $eventos[$c['fecha_inicio']][] = ['texto' => 'Inicia ' . $c['titulo'], 'titulo' => 'Inicio del curso ' . $c['titulo'], 'url' => url('curso.php?id=' . $c['id']), 'clase' => 'inicio'];
}

$nombres_mes = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$titulo_mes = ucfirst($nombres_mes[$mes->format('n') - 1]) . ' ' . $mes->format('Y');
$anterior = (clone $mes)->modify('-1 month')->format('Y-m');
$siguiente = (clone $mes)->modify('+1 month')->format('Y-m');

panel_inicio($usuario, 'Calendario', 'calendario');
encabezado('Calendario', 'Fechas de entrega e inicio de tus cursos.', ['Calendario' => null],
    '<div class="cal-navegacion"><a class="btn-icono" href="?mes=' . $anterior . '" aria-label="Mes anterior">' . icono('chevron-left', 18) . '</a>'
    . '<strong style="min-width: 140px; text-align: center">' . $titulo_mes . '</strong>'
    . '<a class="btn-icono" href="?mes=' . $siguiente . '" aria-label="Mes siguiente">' . icono('chevron-right', 18) . '</a>'
    . '<a class="btn btn-contorno btn-chico" href="?">Hoy</a></div>');
?>
<section class="tarjeta" style="overflow: hidden">
    <div class="calendario">
        <?php foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $dia): ?><div class="cal-cabecera"><?= $dia ?></div><?php endforeach; ?>
        <?php for ($d = clone $inicio; $d <= $fin; $d->modify('+1 day')):
            $clave = $d->format('Y-m-d');
            $clases = ($d->format('m') !== $mes->format('m') ? ' fuera' : '') . ($clave === date('Y-m-d') ? ' hoy' : ''); ?>
            <div class="cal-dia<?= $clases ?>">
                <span class="cal-numero"><?= $d->format('j') ?></span>
                <?php foreach ($eventos[$clave] ?? [] as $ev): ?>
                    <a class="cal-evento <?= $ev['clase'] ?>" href="<?= e($ev['url']) ?>" title="<?= e($ev['titulo']) ?>"><?= e($ev['texto']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endfor; ?>
    </div>
</section>
<p class="ayuda" style="margin-top: 12px">Borde blanco: tarea por entregar · Borde gris: tarea entregada · Borde punteado: inicio de curso</p>
<?php panel_fin(); ?>
