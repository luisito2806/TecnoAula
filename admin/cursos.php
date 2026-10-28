<?php
// Todos los cursos: el Director y el Super Admin asignan docente e inscriben alumnos
require_once __DIR__ . '/../includes/cursos.php';

$usuario = requiere_login();
if (!puede_supervisar_cursos($usuario)) {
    acceso_denegado();
}

switch (accion_post()) {
    case null:
        break;
    case 'asignar':
        $curso = obtener_curso(entero_post('curso_id'));
        if (!$curso) {
            error_formulario('El curso no existe.');
        }
        $docente = entero_post('ponente_id');
        if ($docente && $docente !== (int) $curso['ponente_id'] && !in_array($docente, array_map('intval', array_column(docentes_disponibles(), 'id')), true)) {
            error_formulario('El docente elegido no es válido.');
        }
        if ($docente !== (int) $curso['ponente_id']) {
            $pdo->prepare("UPDATE cursos SET ponente_id = ? WHERE id = ?")->execute([$docente ?: null, $curso['id']]);
        }
        $validos = array_map('intval', array_column(alumnos_disponibles($curso['id']), 'id'));
        $nuevos = 0;
        foreach (array_intersect(array_map('intval', (array) ($_POST['alumnos'] ?? [])), $validos) as $alumno_id) {
            $nuevos += inscribir($curso['id'], $alumno_id, $usuario['id']) ? 1 : 0;
        }
        hecho('Asignación guardada' . ($nuevos ? " · $nuevos alumnos inscritos" : ''));
    default:
        error_formulario('Acción no válida.');
}

$buscar = trim($_GET['buscar'] ?? '');
$estado = in_array($_GET['estado'] ?? '', ['publicado', 'borrador'], true) ? $_GET['estado'] : '';
$cursos = listar_cursos(['buscar' => $buscar, 'estado' => $estado]);
$docentes = docentes_disponibles();
$alumnos = $pdo->query("SELECT u.id, u.nombre, u.email FROM usuarios u WHERE u.eliminado_en IS NULL AND u.estado = 'activo'
    AND (u.rol = 'estudiante' OR EXISTS (SELECT 1 FROM usuario_roles r WHERE r.usuario_id = u.id AND r.rol = 'estudiante')) ORDER BY u.nombre")->fetchAll();
$es_admin = tiene_rol($usuario, 'superadmin');

panel_inicio($usuario, 'Cursos', 'cursos');
encabezado('Cursos', 'Todos los cursos de la plataforma. Asigna docentes e inscribe alumnos.', ['Cursos' => null],
    $es_admin ? '<a class="btn btn-primario" href="' . url('docente/cursos.php') . '">' . icono('plus', 17) . ' Crear curso</a>' : '');
?>
<form class="barra-filtros" method="get">
    <label class="input-icono"><?= icono('search', 17) ?><input type="search" name="buscar" value="<?= e($buscar) ?>" placeholder="Buscar por curso, categoría o docente…" aria-label="Buscar curso"></label>
    <select name="estado" class="select" aria-label="Estado" onchange="this.form.submit()">
        <option value="">Todos los estados</option>
        <option value="publicado"<?= $estado === 'publicado' ? ' selected' : '' ?>>Publicado</option>
        <option value="borrador"<?= $estado === 'borrador' ? ' selected' : '' ?>>Borrador</option>
    </select>
    <button class="btn btn-contorno">Buscar</button>
</form>
<section class="tarjeta">
    <?php if (!$cursos): ?><div class="estado-vacio"><span class="estado-vacio-icono"><?= icono('book-open', 24) ?></span><h3>No hay cursos</h3><p>Los docentes crean cursos desde su panel.</p></div><?php else: ?>
    <div class="tabla-scroll"><table class="tabla">
        <thead><tr><th>Curso</th><th>Docente</th><th>Estado</th><th>Alumnos</th><th>Contenido</th><th>Inicio</th><th class="col-acciones">Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($cursos as $c): ?>
            <tr>
                <td><strong><?= e($c['titulo']) ?></strong><span class="celda-sub"><?= e($c['categoria'] ?: 'Sin categoría') ?> · <?= MODALIDADES[$c['modalidad']] ?></span></td>
                <td><?= $c['docente_nombre'] ? e($c['docente_nombre']) : '<span class="texto-tenue">Sin asignar</span>' ?></td>
                <td><?= badge_estado_curso($c['estado']) ?></td>
                <td><?= (int) $c['total_alumnos'] ?></td>
                <td><?= (int) $c['total_bloques'] ?> bloques · <?= (int) $c['total_temas'] ?> temas</td>
                <td class="celda-fecha"><?= fecha_corta($c['fecha_inicio']) ?></td>
                <td class="col-acciones"><div class="acciones">
                    <button class="btn btn-contorno btn-chico" data-abrir="dlg-asignar" data-titulo="Asignar · <?= e($c['titulo']) ?>" data-valores='<?= e(json_encode(['curso_id' => $c['id'], 'ponente_id' => $c['ponente_id'] ?? ''])) ?>'>Asignar</button>
                    <a class="btn-icono" href="<?= url('curso.php?id=' . $c['id']) ?>" title="Ver curso" aria-label="Ver curso"><?= icono('eye', 16) ?></a>
                    <a class="btn-icono" href="<?= url('reportes.php?curso=' . $c['id']) ?>" title="Reporte" aria-label="Reporte"><?= icono('bar-chart', 16) ?></a>
                    <?php if ($es_admin): ?><a class="btn-icono" href="<?= url('docente/curso.php?id=' . $c['id']) ?>" title="Editar" aria-label="Editar"><?= icono('pencil', 16) ?></a><?php endif; ?>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</section>

<dialog class="dialogo" id="dlg-asignar">
    <form method="post">
        <?= campo_csrf() ?><input type="hidden" name="accion" value="asignar"><input type="hidden" name="curso_id">
        <header class="dialogo-encabezado"><h2>Asignar curso</h2><button type="button" class="btn-icono" data-cerrar aria-label="Cerrar"><?= icono('x', 18) ?></button></header>
        <div class="dialogo-cuerpo">
            <div class="campo"><label for="a-docente">Docente responsable</label>
                <select class="select" id="a-docente" name="ponente_id"><option value="">Sin docente</option>
                    <?php foreach ($docentes as $d): ?><option value="<?= $d['id'] ?>"><?= e($d['nombre']) ?></option><?php endforeach; ?></select></div>
            <div class="campo"><label for="a-alumnos">Inscribir alumnos</label>
                <select class="select" id="a-alumnos" name="alumnos[]" multiple size="9">
                    <?php foreach ($alumnos as $a): ?><option value="<?= $a['id'] ?>"><?= e($a['nombre']) ?> — <?= e($a['email']) ?></option><?php endforeach; ?></select>
                <small class="ayuda">Ctrl (o ⌘) para elegir varios. Los que ya están inscritos se ignoran.</small></div>
        </div>
        <footer class="dialogo-pie"><button type="button" class="btn btn-contorno" data-cerrar>Cancelar</button><button class="btn btn-primario"><span class="spinner"></span>Guardar</button></footer>
    </form>
</dialog>
<?php panel_fin(); ?>
