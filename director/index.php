<?php
require_once __DIR__ . '/../includes/cursos.php';

$usuario = requiere_rol('director', 'superadmin');

$buscar = trim($_GET['buscar'] ?? '');
$curso_id = (int) ($_GET['curso'] ?? 0);
$cursos = listar_cursos();

$where = "u.eliminado_en IS NULL AND (u.rol = 'estudiante' OR EXISTS (SELECT 1 FROM usuario_roles r WHERE r.usuario_id = u.id AND r.rol = 'estudiante'))";
$params = [];
if ($buscar !== '') {
    $where .= ' AND (u.nombre LIKE ? OR u.email LIKE ?)';
    $comodin = '%' . addcslashes($buscar, '%_\\') . '%';
    array_push($params, $comodin, $comodin);
}
if ($curso_id) {
    $where .= ' AND u.id IN (SELECT alumno_id FROM inscripciones WHERE curso_id = ?)';
    $params[] = $curso_id;
}
$stmt = $pdo->prepare("SELECT u.id, u.nombre, u.email, u.avatar_url, u.estado, u.ultimo_acceso, u.cargo,
        (SELECT COUNT(*) FROM inscripciones i WHERE i.alumno_id = u.id) AS cursos,
        (SELECT COUNT(*) FROM entregas e WHERE e.alumno_id = u.id) AS entregas,
        (SELECT AVG(e.calificacion / t.puntos * 100) FROM entregas e JOIN tareas t ON t.id = e.tarea_id WHERE e.alumno_id = u.id AND e.calificacion IS NOT NULL) AS promedio
    FROM usuarios u WHERE $where ORDER BY u.nombre");
$stmt->execute($params);
$alumnos = $stmt->fetchAll();

// Avance promedio de cada alumno en todos sus cursos
$avance = [];
foreach ($cursos as $c) {
    foreach (progreso_alumnos((int) $c['id']) as $alumno_id => $p) {
        $avance[$alumno_id][] = $p['porcentaje'];
    }
}

$activos = count(array_filter($alumnos, fn ($a) => $a['estado'] === 'activo'));
$sin_cursos = count(array_filter($alumnos, fn ($a) => !$a['cursos']));

panel_inicio($usuario, 'Alumnos', 'alumnos');
encabezado('Alumnos', 'Monitorea el avance y desempeño de los alumnos de la plataforma.', ['Alumnos' => null],
    '<a class="btn btn-primario" href="' . url('admin/cursos.php') . '">' . icono('book-open', 17) . ' Asignar cursos</a>');
?>
<section class="metricas">
    <article class="tarjeta metrica"><span class="metrica-icono"><?= icono('users', 20) ?></span><div><p class="metrica-titulo">Alumnos</p><p class="metrica-valor"><?= count($alumnos) ?></p><p class="metrica-tendencia"><?= $activos ?> activos</p></div></article>
    <article class="tarjeta metrica"><span class="metrica-icono"><?= icono('book-open', 20) ?></span><div><p class="metrica-titulo">Sin cursos asignados</p><p class="metrica-valor"><?= $sin_cursos ?></p></div></article>
    <article class="tarjeta metrica"><span class="metrica-icono"><?= icono('layers', 20) ?></span><div><p class="metrica-titulo">Cursos</p><p class="metrica-valor"><?= count($cursos) ?></p></div></article>
    <article class="tarjeta metrica"><span class="metrica-icono"><?= icono('bar-chart', 20) ?></span><div><p class="metrica-titulo">Reportes</p><p class="metrica-valor"><a class="enlace" href="<?= url('reportes.php') ?>" style="font-size: 15px">Ver reportes</a></p></div></article>
</section>
<form class="barra-filtros" method="get">
    <label class="input-icono"><?= icono('search', 17) ?><input type="search" name="buscar" value="<?= e($buscar) ?>" placeholder="Buscar por nombre o correo…" aria-label="Buscar alumno"></label>
    <select name="curso" class="select" aria-label="Curso" onchange="this.form.submit()">
        <option value="">Todos los cursos</option>
        <?php foreach ($cursos as $c): ?><option value="<?= $c['id'] ?>"<?= $curso_id === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['titulo']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-contorno">Buscar</button>
</form>
<section class="tarjeta">
    <?php if (!$alumnos): ?><div class="estado-vacio"><h3>No se encontraron alumnos</h3></div><?php else: ?>
    <div class="tabla-scroll"><table class="tabla">
        <thead><tr><th>Alumno</th><th>Estado</th><th>Cursos</th><th>Avance promedio</th><th>Entregas</th><th>Calificación</th><th>Último acceso</th></tr></thead>
        <tbody>
        <?php foreach ($alumnos as $a): $av = $avance[$a['id']] ?? []; ?>
            <tr>
                <td><div class="celda-usuario"><?= avatar_html($a) ?><div><strong><?= e($a['nombre']) ?></strong><small><?= e($a['email']) ?></small></div></div></td>
                <td><span class="badge estado-<?= $a['estado'] ?>"><i class="punto"></i><?= $a['estado'] === 'activo' ? 'Activo' : 'Inactivo' ?></span></td>
                <td><?= (int) $a['cursos'] ?></td>
                <td><?= $av ? barra_progreso((int) round(array_sum($av) / count($av))) : '<span class="texto-tenue">—</span>' ?></td>
                <td><?= (int) $a['entregas'] ?></td>
                <td><?= $a['promedio'] !== null ? round($a['promedio']) . '%' : '—' ?></td>
                <td class="celda-fecha"><?= fecha_corta($a['ultimo_acceso']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php panel_fin(); ?>
