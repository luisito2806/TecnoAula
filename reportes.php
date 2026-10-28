<?php
require_once __DIR__ . '/includes/cursos.php';

$usuario = requiere_login();
if (!puede_ver_reportes($usuario)) {
    acceso_denegado();
}
// Docentes sin permiso extra ven solo sus cursos; el resto ve todos
$todos = puede_supervisar_cursos($usuario) || tiene_permiso($usuario, 'ver_reportes');
$cursos = $todos ? listar_cursos() : listar_cursos(['docente' => $usuario['id']]);

$filas = [];
foreach ($cursos as $c) {
    $progresos = progreso_alumnos((int) $c['id']);
    $stmt = $pdo->prepare("SELECT COUNT(e.id) AS entregas, SUM(e.calificacion IS NULL) AS pendientes,
            AVG(e.calificacion / t.puntos * 100) AS promedio, (SELECT COUNT(*) FROM tareas WHERE curso_id = ?) AS tareas
        FROM tareas t LEFT JOIN entregas e ON e.tarea_id = t.id WHERE t.curso_id = ?");
    $stmt->execute([$c['id'], $c['id']]);
    $f = $stmt->fetch();
    $filas[] = $c + [
        'avance' => $progresos ? (int) round(array_sum(array_column($progresos, 'porcentaje')) / count($progresos)) : 0,
        'completaron' => count(array_filter($progresos, fn ($p) => $p['porcentaje'] === 100)),
        'entregas' => (int) $f['entregas'],
        'pendientes' => (int) $f['pendientes'],
        'promedio' => $f['promedio'],
        'tareas' => (int) $f['tareas'],
    ];
}

// Detalle por alumno de un curso
$detalle = null;
if ($id = (int) ($_GET['curso'] ?? 0)) {
    foreach ($filas as $fila) {
        if ((int) $fila['id'] === $id) {
            $detalle = $fila;
        }
    }
}

$con_alumnos = array_filter($filas, fn ($f) => $f['total_alumnos'] > 0);
$promedios = array_filter(array_column($filas, 'promedio'), fn ($p) => $p !== null);
$titulo = $detalle ? $detalle['titulo'] : 'Reportes';

panel_inicio($usuario, $titulo, 'reportes');
encabezado($detalle ? e($detalle['titulo']) : 'Reportes', $todos ? 'Desempeño de todos los cursos de la plataforma.' : 'Desempeño de los cursos que impartes.',
    $detalle ? ['Reportes' => 'reportes.php', $detalle['titulo'] => null] : ['Reportes' => null]);
?>
<?php if (!$detalle): ?>
    <section class="metricas">
        <?php foreach ([
            ['Cursos', count($filas), 'book-open', count(array_filter($filas, fn ($f) => $f['estado'] === 'publicado')) . ' publicados'],
            ['Inscripciones', array_sum(array_column($filas, 'total_alumnos')), 'users', 'alumnos en cursos'],
            ['Avance promedio', $con_alumnos ? round(array_sum(array_column($con_alumnos, 'avance')) / count($con_alumnos)) . '%' : '—', 'trending-up', 'en cursos con alumnos'],
            ['Calificación promedio', $promedios ? round(array_sum($promedios) / count($promedios)) . '%' : '—', 'award', array_sum(array_column($filas, 'pendientes')) . ' entregas por calificar'],
        ] as [$t, $v, $ico, $nota]): ?>
            <article class="tarjeta metrica"><span class="metrica-icono"><?= icono($ico, 20) ?></span><div><p class="metrica-titulo"><?= $t ?></p><p class="metrica-valor"><?= $v ?></p><p class="metrica-tendencia"><?= e($nota) ?></p></div></article>
        <?php endforeach; ?>
    </section>
    <section class="tarjeta">
        <?php if (!$filas): ?><div class="estado-vacio"><h3>Sin cursos para reportar</h3></div><?php else: ?>
        <div class="tabla-scroll"><table class="tabla">
            <thead><tr><th>Curso</th><th>Estado</th><th>Alumnos</th><th>Avance promedio</th><th>Entregas</th><th>Calificación</th><th class="col-acciones"></th></tr></thead>
            <tbody>
            <?php foreach ($filas as $f): ?>
                <tr>
                    <td><strong><?= e($f['titulo']) ?></strong><span class="celda-sub"><?= e($f['docente_nombre'] ?? 'Sin docente') ?></span></td>
                    <td><?= badge_estado_curso($f['estado']) ?></td>
                    <td><?= (int) $f['total_alumnos'] ?><span class="celda-sub"><?= $f['completaron'] ?> terminaron</span></td>
                    <td><?= barra_progreso($f['avance']) ?></td>
                    <td><?= $f['entregas'] ?><span class="celda-sub"><?= $f['pendientes'] ?> por calificar</span></td>
                    <td><?= $f['promedio'] !== null ? round($f['promedio']) . '%' : '—' ?></td>
                    <td class="col-acciones"><a class="btn btn-contorno btn-chico" href="?curso=<?= $f['id'] ?>">Detalle</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </section>
<?php else: ?>
    <?php
    $progresos = progreso_alumnos((int) $detalle['id']);
    $stmt = $pdo->prepare("SELECT u.id, u.nombre, u.email, u.avatar_url, u.ultimo_acceso,
            (SELECT COUNT(*) FROM entregas e JOIN tareas t ON t.id = e.tarea_id WHERE t.curso_id = i.curso_id AND e.alumno_id = u.id) AS entregas,
            (SELECT AVG(e.calificacion / t.puntos * 100) FROM entregas e JOIN tareas t ON t.id = e.tarea_id WHERE t.curso_id = i.curso_id AND e.alumno_id = u.id AND e.calificacion IS NOT NULL) AS promedio
        FROM inscripciones i JOIN usuarios u ON u.id = i.alumno_id WHERE i.curso_id = ? AND u.eliminado_en IS NULL ORDER BY u.nombre");
    $stmt->execute([$detalle['id']]);
    $alumnos = $stmt->fetchAll();
    ?>
    <section class="metricas">
        <article class="tarjeta metrica"><span class="metrica-icono"><?= icono('users', 20) ?></span><div><p class="metrica-titulo">Alumnos</p><p class="metrica-valor"><?= count($alumnos) ?></p></div></article>
        <article class="tarjeta metrica"><span class="metrica-icono"><?= icono('trending-up', 20) ?></span><div><p class="metrica-titulo">Avance promedio</p><p class="metrica-valor"><?= $detalle['avance'] ?>%</p></div></article>
        <article class="tarjeta metrica"><span class="metrica-icono"><?= icono('clipboard-list', 20) ?></span><div><p class="metrica-titulo">Evaluaciones</p><p class="metrica-valor"><?= $detalle['tareas'] ?></p></div></article>
        <article class="tarjeta metrica"><span class="metrica-icono"><?= icono('award', 20) ?></span><div><p class="metrica-titulo">Calificación promedio</p><p class="metrica-valor"><?= $detalle['promedio'] !== null ? round($detalle['promedio']) . '%' : '—' ?></p></div></article>
    </section>
    <section class="tarjeta">
        <?php if (!$alumnos): ?><div class="estado-vacio"><h3>Sin alumnos inscritos</h3></div><?php else: ?>
        <div class="tabla-scroll"><table class="tabla">
            <thead><tr><th>Alumno</th><th>Avance</th><th>Entregas</th><th>Promedio</th><th>Último acceso</th></tr></thead>
            <tbody>
            <?php foreach ($alumnos as $a): ?>
                <tr><td><div class="celda-usuario"><?= avatar_html($a) ?><div><strong><?= e($a['nombre']) ?></strong><small><?= e($a['email']) ?></small></div></div></td>
                    <td><?= barra_progreso($progresos[$a['id']]['porcentaje'] ?? 0) ?></td>
                    <td><?= (int) $a['entregas'] ?>/<?= $detalle['tareas'] ?></td>
                    <td><?= $a['promedio'] !== null ? round($a['promedio']) . '%' : '—' ?></td>
                    <td class="celda-fecha"><?= fecha_corta($a['ultimo_acceso']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php panel_fin(); ?>
