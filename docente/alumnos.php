<?php
require_once __DIR__ . '/../includes/cursos.php';

$usuario = requiere_login();
if (!puede_crear_cursos($usuario)) {
    acceso_denegado();
}

$cursos = listar_cursos(['docente' => $usuario['id']]);
$curso_id = (int) ($_GET['curso'] ?? 0);
$buscar = trim($_GET['buscar'] ?? '');

// Un renglón por alumno con el avance en cada uno de mis cursos
$alumnos = [];
foreach ($cursos as $c) {
    if ($curso_id && (int) $c['id'] !== $curso_id) {
        continue;
    }
    $progresos = progreso_alumnos((int) $c['id']);
    $stmt = $pdo->prepare("SELECT u.id, u.nombre, u.email, u.avatar_url, u.ultimo_acceso FROM inscripciones i JOIN usuarios u ON u.id = i.alumno_id
        WHERE i.curso_id = ? AND u.eliminado_en IS NULL");
    $stmt->execute([$c['id']]);
    foreach ($stmt->fetchAll() as $a) {
        if ($buscar !== '' && stripos($a['nombre'] . ' ' . $a['email'], $buscar) === false) {
            continue;
        }
        $alumnos[$a['id']] ??= $a + ['cursos' => []];
        $alumnos[$a['id']]['cursos'][] = ['id' => $c['id'], 'titulo' => $c['titulo'], 'porcentaje' => $progresos[$a['id']]['porcentaje'] ?? 0];
    }
}
uasort($alumnos, fn ($a, $b) => strcmp($a['nombre'], $b['nombre']));

panel_inicio($usuario, 'Alumnos', $usuario['rol'] === 'ponente' ? 'alumnos' : 'mis-cursos');
encabezado('Alumnos', 'Alumnos inscritos en tus cursos y su avance.', ['Alumnos' => null]);
?>
<form class="barra-filtros" method="get">
    <label class="input-icono"><?= icono('search', 17) ?><input type="search" name="buscar" value="<?= e($buscar) ?>" placeholder="Buscar por nombre o correo…" aria-label="Buscar alumno"></label>
    <select name="curso" class="select" aria-label="Curso" onchange="this.form.submit()">
        <option value="">Todos mis cursos</option>
        <?php foreach ($cursos as $c): ?><option value="<?= $c['id'] ?>"<?= $curso_id === (int) $c['id'] ? ' selected' : '' ?>><?= e($c['titulo']) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-contorno">Buscar</button>
</form>
<section class="tarjeta">
    <?php if (!$alumnos): ?>
        <div class="estado-vacio"><span class="estado-vacio-icono"><?= icono('users', 24) ?></span><h3>Sin alumnos</h3><p>Inscribe alumnos desde la pestaña Alumnos de cada curso.</p></div>
    <?php else: ?>
    <div class="tabla-scroll"><table class="tabla">
        <thead><tr><th>Alumno</th><th>Cursos y avance</th><th>Último acceso</th></tr></thead>
        <tbody>
        <?php foreach ($alumnos as $a): ?>
            <tr>
                <td><div class="celda-usuario"><?= avatar_html($a) ?><div><strong><?= e($a['nombre']) ?></strong><small><?= e($a['email']) ?></small></div></div></td>
                <td><?php foreach ($a['cursos'] as $c): ?>
                    <div style="display: grid; grid-template-columns: minmax(140px, 1fr) 180px; gap: 12px; align-items: center; padding: 2px 0">
                        <a class="texto-secundario" style="text-decoration:none" href="<?= url('docente/curso.php?id=' . $c['id'] . '&tab=alumnos') ?>"><?= e($c['titulo']) ?></a><?= barra_progreso($c['porcentaje']) ?></div>
                <?php endforeach; ?></td>
                <td class="celda-fecha"><?= fecha_corta($a['ultimo_acceso']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php panel_fin(); ?>
