<?php
require_once __DIR__ . '/../includes/cursos.php';

$usuario = requiere_login();
$curso = curso_de_url($usuario, true);
$cid = $curso['id'];
$pestanas = ['estructura' => 'Estructura', 'info' => 'Información', 'evaluaciones' => 'Evaluaciones', 'alumnos' => 'Alumnos', 'reportes' => 'Reportes'];
$tab = isset($pestanas[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'estructura';

// Comprueba que un registro hijo pertenezca a este curso
function del_curso(string $tipo, int $id, int $curso_id): bool
{
    global $pdo;
    $sql = [
        'bloque' => "SELECT 1 FROM bloques WHERE id = ? AND curso_id = ?",
        'tema' => "SELECT 1 FROM temas t JOIN bloques b ON b.id = t.bloque_id WHERE t.id = ? AND b.curso_id = ?",
        'material' => "SELECT 1 FROM materiales m JOIN temas t ON t.id = m.tema_id JOIN bloques b ON b.id = t.bloque_id WHERE m.id = ? AND b.curso_id = ?",
        'tarea' => "SELECT 1 FROM tareas WHERE id = ? AND curso_id = ?",
        'entrega' => "SELECT 1 FROM entregas e JOIN tareas t ON t.id = e.tarea_id WHERE e.id = ? AND t.curso_id = ?",
    ][$tipo];
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id, $curso_id]);
    return (bool) $stmt->fetchColumn();
}

function exigir_del_curso(string $tipo, int $id, int $curso_id): void
{
    if (!del_curso($tipo, $id, $curso_id)) {
        error_formulario('El elemento no pertenece a este curso.');
    }
}

switch (accion_post()) {
    case null:
        break;

    case 'bloque_guardar':
        $titulo = texto_post('titulo');
        if ($titulo === '') {
            error_formulario('Escribe el título del bloque.');
        }
        if ($id = entero_post('id')) {
            exigir_del_curso('bloque', $id, $cid);
            $pdo->prepare("UPDATE bloques SET titulo = ?, descripcion = ? WHERE id = ?")->execute([$titulo, texto_post('descripcion', 1000) ?: null, $id]);
            hecho('Bloque actualizado');
        }
        $pdo->prepare("INSERT INTO bloques (curso_id, titulo, descripcion, orden) SELECT ?, ?, ?, COALESCE(MAX(orden), 0) + 1 FROM bloques WHERE curso_id = ?")
            ->execute([$cid, $titulo, texto_post('descripcion', 1000) ?: null, $cid]);
        hecho('Bloque agregado');

    case 'bloque_eliminar':
        exigir_del_curso('bloque', entero_post('id'), $cid);
        $pdo->prepare("DELETE FROM bloques WHERE id = ?")->execute([entero_post('id')]);
        hecho('Bloque eliminado con sus temas');

    case 'tema_guardar':
        $titulo = texto_post('titulo');
        $bloque_id = entero_post('bloque_id');
        if ($titulo === '') {
            error_formulario('Escribe el título del tema.');
        }
        exigir_del_curso('bloque', $bloque_id, $cid);
        $datos = [$titulo, texto_post('contenido', 20000) ?: null, max(0, min(1440, entero_post('duracion_min')))];
        if ($id = entero_post('id')) {
            exigir_del_curso('tema', $id, $cid);
            $actual = $pdo->prepare("SELECT bloque_id FROM temas WHERE id = ?");
            $actual->execute([$id]);
            if ((int) $actual->fetchColumn() !== $bloque_id) {
                // Al cambiar de bloque, el tema pasa al final del nuevo bloque
                $pdo->prepare("UPDATE temas SET bloque_id = ?, orden = (SELECT o FROM (SELECT COALESCE(MAX(orden), 0) + 1 AS o FROM temas WHERE bloque_id = ?) x) WHERE id = ?")
                    ->execute([$bloque_id, $bloque_id, $id]);
            }
            $pdo->prepare("UPDATE temas SET titulo = ?, contenido = ?, duracion_min = ? WHERE id = ?")->execute([...$datos, $id]);
            hecho('Tema actualizado');
        }
        $pdo->prepare("INSERT INTO temas (bloque_id, titulo, contenido, duracion_min, orden) SELECT ?, ?, ?, ?, COALESCE(MAX(orden), 0) + 1 FROM temas WHERE bloque_id = ?")
            ->execute([$bloque_id, ...$datos, $bloque_id]);
        hecho('Tema agregado');

    case 'tema_eliminar':
        exigir_del_curso('tema', entero_post('id'), $cid);
        $pdo->prepare("DELETE FROM temas WHERE id = ?")->execute([entero_post('id')]);
        hecho('Tema eliminado');

    case 'material_guardar':
        $tema_id = entero_post('tema_id');
        exigir_del_curso('tema', $tema_id, $cid);
        $url_material = trim($_POST['url'] ?? '');
        $tipo = isset(TIPOS_MATERIAL[$_POST['tipo'] ?? '']) ? $_POST['tipo'] : 'enlace';
        if (texto_post('titulo') === '' || !url_valida($url_material)) {
            error_formulario('El material necesita un título y una URL válida (http/https).');
        }
        $pdo->prepare("INSERT INTO materiales (tema_id, tipo, titulo, url, orden) SELECT ?, ?, ?, ?, COALESCE(MAX(orden), 0) + 1 FROM materiales WHERE tema_id = ?")
            ->execute([$tema_id, $tipo, texto_post('titulo'), $url_material, $tema_id]);
        hecho('Material agregado');

    case 'material_eliminar':
        exigir_del_curso('material', entero_post('id'), $cid);
        $pdo->prepare("DELETE FROM materiales WHERE id = ?")->execute([entero_post('id')]);
        hecho('Material eliminado');

    case 'estado':
        $nuevo = $curso['estado'] === 'publicado' ? 'borrador' : 'publicado';
        if ($nuevo === 'publicado' && total_temas($cid) === 0) {
            error_formulario('Agrega al menos un tema antes de publicar el curso.');
        }
        $pdo->prepare("UPDATE cursos SET estado = ? WHERE id = ?")->execute([$nuevo, $cid]);
        hecho($nuevo === 'publicado' ? 'Curso publicado: ya lo ven sus alumnos' : 'El curso volvió a borrador');

    case 'info_guardar':
        $titulo = texto_post('titulo');
        $imagen = trim($_POST['imagen_url'] ?? '');
        if ($titulo === '') {
            error_formulario('El curso necesita un título.');
        }
        if ($imagen !== '' && !url_valida($imagen)) {
            error_formulario('La URL de la imagen no es válida (http/https).');
        }
        $estado = ($_POST['estado'] ?? '') === 'publicado' ? 'publicado' : 'borrador';
        if ($estado === 'publicado' && total_temas($cid) === 0) {
            error_formulario('Agrega al menos un tema antes de publicar el curso.');
        }
        $pdo->prepare("UPDATE cursos SET titulo = ?, descripcion = ?, categoria = ?, modalidad = ?, fecha_inicio = ?, imagen_url = ?, estado = ? WHERE id = ?")
            ->execute([$titulo, texto_post('descripcion', 2000), texto_post('categoria', 100) ?: null,
                isset(MODALIDADES[$_POST['modalidad'] ?? '']) ? $_POST['modalidad'] : 'en_linea',
                fecha_valida($_POST['fecha_inicio'] ?? ''), $imagen ?: null, $estado, $cid]);
        hecho('Cambios guardados');

    case 'curso_eliminar':
        $pdo->prepare("UPDATE cursos SET eliminado_en = NOW() WHERE id = ?")->execute([$cid]);
        hecho('Curso eliminado', 'docente/cursos.php');

    case 'tarea_guardar':
        $titulo = texto_post('titulo');
        if ($titulo === '') {
            error_formulario('Escribe el título de la evaluación.');
        }
        $bloque_id = entero_post('bloque_id') ?: null;
        if ($bloque_id) {
            exigir_del_curso('bloque', $bloque_id, $cid);
        }
        $fecha = fecha_valida($_POST['fecha_entrega'] ?? '', 'Y-m-d\TH:i');
        $datos = [$titulo, texto_post('descripcion', 5000) ?: null, $fecha ? str_replace('T', ' ', $fecha) . ':00' : null, max(1, min(1000, entero_post('puntos') ?: 100)), $bloque_id];
        if ($id = entero_post('id')) {
            exigir_del_curso('tarea', $id, $cid);
            $pdo->prepare("UPDATE tareas SET titulo = ?, descripcion = ?, fecha_entrega = ?, puntos = ?, bloque_id = ? WHERE id = ?")->execute([...$datos, $id]);
            hecho('Evaluación actualizada');
        }
        $pdo->prepare("INSERT INTO tareas (titulo, descripcion, fecha_entrega, puntos, bloque_id, curso_id) VALUES (?, ?, ?, ?, ?, ?)")->execute([...$datos, $cid]);
        hecho('Evaluación agregada');

    case 'tarea_eliminar':
        exigir_del_curso('tarea', entero_post('id'), $cid);
        $pdo->prepare("DELETE FROM tareas WHERE id = ?")->execute([entero_post('id')]);
        hecho('Evaluación eliminada', "docente/curso.php?id=$cid&tab=evaluaciones");

    case 'calificar':
        $entrega_id = entero_post('id');
        exigir_del_curso('entrega', $entrega_id, $cid);
        $stmt = $pdo->prepare("SELECT t.puntos, e.alumno_id, t.titulo FROM entregas e JOIN tareas t ON t.id = e.tarea_id WHERE e.id = ?");
        $stmt->execute([$entrega_id]);
        $info = $stmt->fetch();
        $nota = str_replace(',', '.', trim($_POST['calificacion'] ?? ''));
        if (!is_numeric($nota) || $nota < 0 || $nota > $info['puntos']) {
            error_formulario("La calificación debe estar entre 0 y {$info['puntos']}.");
        }
        $pdo->prepare("UPDATE entregas SET calificacion = ?, comentario = ?, calificado_en = NOW() WHERE id = ?")
            ->execute([round((float) $nota, 2), texto_post('comentario', 2000) ?: null, $entrega_id]);
        registrar_actividad((int) $info['alumno_id'], 'calificacion', "Calificación en «{$info['titulo']}»: $nota/{$info['puntos']}", $usuario['id']);
        hecho('Calificación guardada');

    case 'inscribir':
        $agregados = 0;
        $validos = array_map('intval', array_column(alumnos_disponibles($cid), 'id'));
        foreach (array_intersect(array_map('intval', (array) ($_POST['alumnos'] ?? [])), $validos) as $alumno_id) {
            $agregados += inscribir($cid, $alumno_id, $usuario['id']) ? 1 : 0;
        }
        $agregados ? hecho($agregados === 1 ? '1 alumno inscrito' : "$agregados alumnos inscritos") : error_formulario('Selecciona al menos un alumno.');

    case 'quitar_alumno':
        $pdo->prepare("DELETE FROM inscripciones WHERE curso_id = ? AND alumno_id = ?")->execute([$cid, entero_post('alumno_id')]);
        hecho('Alumno dado de baja del curso');

    default:
        error_formulario('Acción no válida.');
}

$estructura = estructura_curso($cid);
$temas = temas_en_orden($estructura);
$duracion = array_sum(array_column($temas, 'duracion_min'));
$progresos = progreso_alumnos($cid);

$stmt = $pdo->prepare("SELECT t.*, b.titulo AS bloque_titulo,
        (SELECT COUNT(*) FROM entregas e WHERE e.tarea_id = t.id) AS entregas,
        (SELECT COUNT(*) FROM entregas e WHERE e.tarea_id = t.id AND e.calificacion IS NOT NULL) AS calificadas,
        (SELECT AVG(e.calificacion / t.puntos * 100) FROM entregas e WHERE e.tarea_id = t.id AND e.calificacion IS NOT NULL) AS promedio
    FROM tareas t LEFT JOIN bloques b ON b.id = t.bloque_id WHERE t.curso_id = ? ORDER BY t.fecha_entrega IS NULL, t.fecha_entrega, t.id");
$stmt->execute([$cid]);
$tareas = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT u.id, u.nombre, u.email, u.avatar_url, u.ultimo_acceso, i.inscrito_en,
        (SELECT COUNT(*) FROM entregas e JOIN tareas t ON t.id = e.tarea_id WHERE t.curso_id = i.curso_id AND e.alumno_id = u.id) AS entregas,
        (SELECT AVG(e.calificacion / t.puntos * 100) FROM entregas e JOIN tareas t ON t.id = e.tarea_id WHERE t.curso_id = i.curso_id AND e.alumno_id = u.id AND e.calificacion IS NOT NULL) AS promedio
    FROM inscripciones i JOIN usuarios u ON u.id = i.alumno_id WHERE i.curso_id = ? AND u.eliminado_en IS NULL ORDER BY u.nombre");
$stmt->execute([$cid]);
$alumnos = $stmt->fetchAll();

$pendientes = array_sum(array_map(fn ($t) => $t['entregas'] - $t['calificadas'], $tareas));
$promedio_avance = $progresos ? (int) round(array_sum(array_column($progresos, 'porcentaje')) / count($progresos)) : 0;

$opciones_bloques = fn (bool $con_ninguno) => ($con_ninguno ? '<option value="">Sin bloque (todo el curso)</option>' : '')
    . implode('', array_map(fn ($b) => '<option value="' . $b['id'] . '">' . e($b['titulo']) . '</option>', $estructura));

$volver = $curso['ponente_id'] == $usuario['id'] ? ['Mis cursos' => 'docente/cursos.php'] : ['Cursos' => 'admin/cursos.php'];
panel_inicio($usuario, $curso['titulo'], $curso['ponente_id'] == $usuario['id'] ? ($usuario['rol'] === 'ponente' ? 'cursos' : 'mis-cursos') : 'cursos');
encabezado(e($curso['titulo']), '', $volver + [$curso['titulo'] => null],
    '<a class="btn btn-contorno" href="' . url('curso.php?id=' . $cid) . '">' . icono('eye', 17) . ' Vista previa</a>'
    . '<form method="post">' . campo_csrf() . '<input type="hidden" name="accion" value="estado"><button class="btn ' . ($curso['estado'] === 'publicado' ? 'btn-contorno' : 'btn-primario') . '">'
    . ($curso['estado'] === 'publicado' ? 'Pasar a borrador' : icono('check', 17) . ' Publicar curso') . '</button></form>');
?>
<section class="tarjeta curso-cabecera">
    <?= miniatura_html($curso, $duracion ? duracion_texto($duracion) : '') ?>
    <div>
        <?= badge_estado_curso($curso['estado']) ?>
        <p style="margin-top: 12px"><?= e($curso['descripcion'] ?: 'Sin descripción. Agrégala en la pestaña Información.') ?></p>
        <div class="datos-curso">
            <span><?= icono('layers', 16) ?> <?= count($estructura) ?> bloques · <?= count($temas) ?> temas</span>
            <span><?= icono('users', 16) ?> <?= count($alumnos) ?> alumnos</span>
            <span><?= icono('globe', 16) ?> <?= MODALIDADES[$curso['modalidad']] ?></span>
            <span><?= icono('calendar', 16) ?> Inicia <?= fecha_corta($curso['fecha_inicio']) ?></span>
            <span><?= icono('user', 16) ?> <?= e($curso['docente_nombre'] ?? 'Sin docente') ?></span>
        </div>
    </div>
</section>

<nav class="tabs">
    <?php foreach ($pestanas as $clave => $texto): ?>
        <a href="?id=<?= $cid ?>&tab=<?= $clave ?>" class="<?= $tab === $clave ? 'activa' : '' ?>"><?= $texto ?>
            <?php if ($clave === 'evaluaciones' && $pendientes): ?><span class="badge" title="Entregas por calificar"><?= $pendientes ?></span><?php endif; ?>
            <?php if ($clave === 'alumnos'): ?><span class="badge"><?= count($alumnos) ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($tab === 'estructura'): ?>
    <div class="barra-filtros">
        <button class="btn btn-primario" data-abrir="dlg-bloque" data-titulo="Agregar bloque"><?= icono('plus', 17) ?> Agregar bloque</button>
        <?php if ($estructura): ?>
            <button class="btn btn-contorno" data-abrir="dlg-tema" data-titulo="Agregar tema" data-valores='<?= e(json_encode(['bloque_id' => $estructura[array_key_last($estructura)]['id']])) ?>'><?= icono('plus', 17) ?> Agregar tema</button>
        <?php endif; ?>
        <a class="btn btn-contorno" href="?id=<?= $cid ?>&tab=evaluaciones&nueva=1"><?= icono('clipboard-list', 17) ?> Agregar evaluación</a>
        <a class="btn btn-contorno" href="?id=<?= $cid ?>&tab=alumnos"><?= icono('users', 17) ?> Administrar alumnos</a>
        <a class="btn btn-contorno" href="?id=<?= $cid ?>&tab=info"><?= icono('settings', 17) ?> Configuración del curso</a>
    </div>
    <?php if (!$estructura): ?>
        <div class="tarjeta estado-vacio">
            <span class="estado-vacio-icono"><?= icono('layers', 26) ?></span>
            <h3>El curso aún no tiene bloques</h3>
            <p>Organiza el contenido en bloques y, dentro de cada uno, agrega temas y materiales.</p>
        </div>
    <?php endif; ?>
    <p class="ayuda" style="margin-bottom: 12px"><?= icono('grip-vertical', 14) ?> Arrastra bloques y temas desde el asa para cambiar su orden.</p>
    <div class="estructura" id="estructura" data-curso="<?= $cid ?>" data-api="<?= url('api/estructura.php') ?>">
        <?php foreach ($estructura as $n => $bloque): ?>
            <section class="bloque" data-id="<?= $bloque['id'] ?>">
                <header class="bloque-encabezado">
                    <span class="asa" title="Arrastrar para reordenar"><?= icono('grip-vertical', 18) ?></span>
                    <h3><span class="bloque-numero">Bloque <?= $n + 1 ?></span><br><?= e($bloque['titulo']) ?>
                        <?php if ($bloque['descripcion']): ?><small><?= e($bloque['descripcion']) ?></small><?php endif; ?></h3>
                    <span class="texto-tenue"><?= count($bloque['temas']) ?> temas · <?= duracion_texto((int) array_sum(array_column($bloque['temas'], 'duracion_min'))) ?></span>
                    <div class="acciones">
                        <button class="btn-icono" title="Agregar tema" aria-label="Agregar tema" data-abrir="dlg-tema" data-titulo="Agregar tema" data-valores='<?= e(json_encode(['bloque_id' => $bloque['id']])) ?>'><?= icono('plus', 17) ?></button>
                        <button class="btn-icono" title="Editar bloque" aria-label="Editar bloque" data-abrir="dlg-bloque" data-titulo="Editar bloque" data-valores='<?= e(json_encode(['id' => $bloque['id'], 'titulo' => $bloque['titulo'], 'descripcion' => $bloque['descripcion']])) ?>'><?= icono('pencil', 16) ?></button>
                        <form method="post" data-confirmar="Se eliminará el bloque «<?= e($bloque['titulo']) ?>» con todos sus temas y materiales." data-confirmar-titulo="Eliminar bloque">
                            <?= campo_csrf() ?><input type="hidden" name="accion" value="bloque_eliminar"><input type="hidden" name="id" value="<?= $bloque['id'] ?>">
                            <button class="btn-icono peligro" title="Eliminar bloque" aria-label="Eliminar bloque"><?= icono('trash', 16) ?></button>
                        </form>
                    </div>
                </header>
                <ul class="temas" data-bloque="<?= $bloque['id'] ?>">
                    <?php foreach ($bloque['temas'] as $tema): ?>
                        <li class="tema-fila" data-id="<?= $tema['id'] ?>">
                            <span class="asa" title="Arrastrar para reordenar"><?= icono('grip-vertical', 16) ?></span>
                            <div class="lista-cuerpo">
                                <strong><?= e($tema['titulo']) ?></strong>
                                <small><?= $tema['duracion_min'] ? duracion_texto((int) $tema['duracion_min']) . ' · ' : '' ?><?= count($tema['materiales']) ?> materiales</small>
                                <?php if ($tema['materiales']): ?>
                                    <div class="materiales">
                                        <?php foreach ($tema['materiales'] as $m): ?>
                                            <span class="chip"><?= icono(TIPOS_MATERIAL[$m['tipo']][1], 13) ?><a href="<?= e($m['url']) ?>" target="_blank" rel="noopener" class="texto-secundario" style="text-decoration:none"><span><?= e($m['titulo']) ?></span></a>
                                                <form method="post" data-confirmar="¿Eliminar el material «<?= e($m['titulo']) ?>»?">
                                                    <?= campo_csrf() ?><input type="hidden" name="accion" value="material_eliminar"><input type="hidden" name="id" value="<?= $m['id'] ?>">
                                                    <button aria-label="Eliminar material"><?= icono('x', 13) ?></button>
                                                </form>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="acciones">
                                <button class="btn-icono" title="Agregar material" aria-label="Agregar material" data-abrir="dlg-material" data-valores='<?= e(json_encode(['tema_id' => $tema['id']])) ?>'><?= icono('link', 16) ?></button>
                                <button class="btn-icono" title="Editar tema" aria-label="Editar tema" data-abrir="dlg-tema" data-titulo="Editar tema" data-valores='<?= e(json_encode(['id' => $tema['id'], 'bloque_id' => $tema['bloque_id'], 'titulo' => $tema['titulo'], 'contenido' => $tema['contenido'], 'duracion_min' => $tema['duracion_min']])) ?>'><?= icono('pencil', 16) ?></button>
                                <form method="post" data-confirmar="Se eliminará el tema «<?= e($tema['titulo']) ?>» y sus materiales." data-confirmar-titulo="Eliminar tema">
                                    <?= campo_csrf() ?><input type="hidden" name="accion" value="tema_eliminar"><input type="hidden" name="id" value="<?= $tema['id'] ?>">
                                    <button class="btn-icono peligro" title="Eliminar tema" aria-label="Eliminar tema"><?= icono('trash', 16) ?></button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (!$bloque['temas']): ?>
                    <div class="pie-bloque"><button class="enlace" data-abrir="dlg-tema" data-titulo="Agregar tema" data-valores='<?= e(json_encode(['bloque_id' => $bloque['id']])) ?>'><?= icono('plus', 15) ?> Agregar el primer tema</button></div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>

    <dialog class="dialogo" id="dlg-bloque">
        <form method="post">
            <?= campo_csrf() ?><input type="hidden" name="accion" value="bloque_guardar"><input type="hidden" name="id">
            <header class="dialogo-encabezado"><h2>Agregar bloque</h2><button type="button" class="btn-icono" data-cerrar aria-label="Cerrar"><?= icono('x', 18) ?></button></header>
            <div class="dialogo-cuerpo">
                <div class="campo"><label for="b-titulo">Título del bloque</label><input class="input" id="b-titulo" name="titulo" maxlength="150" required placeholder="Ej. Fundamentos"></div>
                <div class="campo"><label for="b-desc">Descripción (opcional)</label><textarea class="textarea" id="b-desc" name="descripcion" maxlength="1000"></textarea></div>
            </div>
            <footer class="dialogo-pie"><button type="button" class="btn btn-contorno" data-cerrar>Cancelar</button><button class="btn btn-primario"><span class="spinner"></span>Guardar</button></footer>
        </form>
    </dialog>

    <dialog class="dialogo dialogo-ancho" id="dlg-tema">
        <form method="post">
            <?= campo_csrf() ?><input type="hidden" name="accion" value="tema_guardar"><input type="hidden" name="id">
            <header class="dialogo-encabezado"><h2>Agregar tema</h2><button type="button" class="btn-icono" data-cerrar aria-label="Cerrar"><?= icono('x', 18) ?></button></header>
            <div class="dialogo-cuerpo formulario-rejilla">
                <div class="campo completo"><label for="t-titulo">Título del tema</label><input class="input" id="t-titulo" name="titulo" maxlength="150" required></div>
                <div class="campo"><label for="t-bloque">Bloque</label><select class="select" id="t-bloque" name="bloque_id"><?= $opciones_bloques(false) ?></select></div>
                <div class="campo"><label for="t-dur">Duración estimada (minutos)</label><input class="input" type="number" min="0" max="1440" id="t-dur" name="duracion_min" value="0"></div>
                <div class="campo completo"><label for="t-cont">Contenido</label><textarea class="textarea" id="t-cont" name="contenido" rows="10" maxlength="20000" placeholder="Explicación del tema. Los enlaces se vuelven clicables."></textarea></div>
            </div>
            <footer class="dialogo-pie"><button type="button" class="btn btn-contorno" data-cerrar>Cancelar</button><button class="btn btn-primario"><span class="spinner"></span>Guardar tema</button></footer>
        </form>
    </dialog>

    <dialog class="dialogo" id="dlg-material">
        <form method="post">
            <?= campo_csrf() ?><input type="hidden" name="accion" value="material_guardar"><input type="hidden" name="tema_id">
            <header class="dialogo-encabezado"><h2>Agregar material</h2><button type="button" class="btn-icono" data-cerrar aria-label="Cerrar"><?= icono('x', 18) ?></button></header>
            <div class="dialogo-cuerpo">
                <div class="campo"><label for="m-tipo">Tipo</label><select class="select" id="m-tipo" name="tipo"><?php foreach (TIPOS_MATERIAL as $k => [$texto]): ?><option value="<?= $k ?>"><?= $texto ?></option><?php endforeach; ?></select></div>
                <div class="campo"><label for="m-titulo">Título</label><input class="input" id="m-titulo" name="titulo" maxlength="150" required></div>
                <div class="campo"><label for="m-url">URL</label><input class="input" type="url" id="m-url" name="url" maxlength="500" required placeholder="https://…">
                    <small class="ayuda">Los videos de YouTube se muestran dentro del tema.</small></div>
            </div>
            <footer class="dialogo-pie"><button type="button" class="btn btn-contorno" data-cerrar>Cancelar</button><button class="btn btn-primario"><span class="spinner"></span>Agregar</button></footer>
        </form>
    </dialog>

<?php elseif ($tab === 'info'): ?>
    <div class="columnas">
        <form method="post" class="tarjeta cuerpo-tarjeta formulario-rejilla">
            <?= campo_csrf() ?><input type="hidden" name="accion" value="info_guardar">
            <div class="campo completo"><label for="i-titulo">Título</label><input class="input" id="i-titulo" name="titulo" maxlength="150" required value="<?= e($curso['titulo']) ?>"></div>
            <div class="campo completo"><label for="i-desc">Descripción</label><textarea class="textarea" id="i-desc" name="descripcion" rows="5" maxlength="2000"><?= e($curso['descripcion']) ?></textarea></div>
            <div class="campo"><label for="i-cat">Categoría</label><input class="input" id="i-cat" name="categoria" maxlength="100" value="<?= e($curso['categoria']) ?>"></div>
            <div class="campo"><label for="i-mod">Modalidad</label><select class="select" id="i-mod" name="modalidad"><?php foreach (MODALIDADES as $k => $v): ?><option value="<?= $k ?>"<?= $curso['modalidad'] === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
            <div class="campo"><label for="i-fecha">Fecha de inicio</label><input class="input" type="date" id="i-fecha" name="fecha_inicio" value="<?= e($curso['fecha_inicio']) ?>"></div>
            <div class="campo"><label for="i-estado">Estado</label><select class="select" id="i-estado" name="estado"><option value="borrador">Borrador (solo tú lo ves)</option><option value="publicado"<?= $curso['estado'] === 'publicado' ? ' selected' : '' ?>>Publicado</option></select></div>
            <div class="campo completo"><label for="i-img">Imagen de portada (URL, opcional)</label><input class="input" type="url" id="i-img" name="imagen_url" maxlength="255" value="<?= e($curso['imagen_url']) ?>" placeholder="https://…"></div>
            <div class="completo"><button class="btn btn-primario"><span class="spinner"></span>Guardar cambios</button></div>
        </form>
        <aside>
            <section class="tarjeta cuerpo-tarjeta">
                <h3 style="margin-bottom: 12px">Resumen</h3>
                <dl class="kv">
                    <div><dt>Creado</dt><dd><?= fecha_corta($curso['creado_en']) ?></dd></div>
                    <div><dt>Última edición</dt><dd><?= fecha_corta($curso['actualizado_en']) ?></dd></div>
                    <div><dt>Duración total</dt><dd><?= duracion_texto($duracion) ?></dd></div>
                    <div><dt>Evaluaciones</dt><dd><?= count($tareas) ?></dd></div>
                </dl>
            </section>
            <section class="tarjeta cuerpo-tarjeta">
                <h3>Eliminar curso</h3>
                <p class="texto-secundario" style="margin: 6px 0 12px">Los alumnos dejarán de verlo. Esta acción no se puede deshacer desde la plataforma.</p>
                <form method="post" data-confirmar="Se eliminará el curso «<?= e($curso['titulo']) ?>» para todos sus alumnos." data-confirmar-titulo="Eliminar curso">
                    <?= campo_csrf() ?><input type="hidden" name="accion" value="curso_eliminar">
                    <button class="btn btn-peligro"><?= icono('trash', 16) ?> Eliminar curso</button>
                </form>
            </section>
        </aside>
    </div>

<?php elseif ($tab === 'evaluaciones'): ?>
    <?php
    $tarea_sel = null;
    foreach ($tareas as $t) {
        if ((int) $t['id'] === (int) ($_GET['tarea'] ?? 0)) {
            $tarea_sel = $t;
        }
    }
    ?>
    <?php if ($tarea_sel): ?>
        <?php
        $stmt = $pdo->prepare("SELECT u.id AS alumno_id, u.nombre, u.avatar_url, e.id, e.contenido, e.url, e.entregado_en, e.calificacion, e.comentario
            FROM inscripciones i JOIN usuarios u ON u.id = i.alumno_id
            LEFT JOIN entregas e ON e.alumno_id = u.id AND e.tarea_id = ?
            WHERE i.curso_id = ? AND u.eliminado_en IS NULL ORDER BY e.id IS NULL, e.calificacion IS NOT NULL, u.nombre");
        $stmt->execute([$tarea_sel['id'], $cid]);
        $entregas = $stmt->fetchAll();
        ?>
        <p style="margin-bottom: 16px"><a class="enlace" href="?id=<?= $cid ?>&tab=evaluaciones"><?= icono('arrow-left', 15) ?> Todas las evaluaciones</a></p>
        <section class="tarjeta" style="margin-bottom: 16px">
            <div class="encabezado-tarjeta"><div><h2><?= e($tarea_sel['titulo']) ?></h2><small class="texto-tenue"><?= plazo_texto($tarea_sel['fecha_entrega']) ?> · <?= fecha_hora_texto($tarea_sel['fecha_entrega']) ?> · <?= (int) $tarea_sel['puntos'] ?> puntos</small></div>
                <span class="badge"><?= (int) $tarea_sel['calificadas'] ?>/<?= (int) $tarea_sel['entregas'] ?> calificadas</span></div>
            <?php if ($tarea_sel['descripcion']): ?><div class="cuerpo-tarjeta texto-secundario"><?= texto_html($tarea_sel['descripcion']) ?></div><?php endif; ?>
        </section>
        <section class="tarjeta">
            <?php if (!$entregas): ?><p class="vacio-mini">No hay alumnos inscritos.</p><?php endif; ?>
            <?php foreach ($entregas as $en): ?>
                <article class="mensaje">
                    <?= avatar_html(['id' => $en['alumno_id'], 'nombre' => $en['nombre'], 'avatar_url' => $en['avatar_url']]) ?>
                    <div class="mensaje-cuerpo">
                        <div class="mensaje-autor"><strong><?= e($en['nombre']) ?></strong>
                            <?php if (!$en['id']): ?><span class="badge badge-tenue"><i class="punto"></i>Sin entregar</span>
                            <?php elseif ($en['calificacion'] === null): ?><span class="badge"><i class="punto"></i>Por calificar</span>
                            <?php else: ?><span class="badge badge-claro"><?= icono('check', 13) ?><?= rtrim(rtrim($en['calificacion'], '0'), '.') ?>/<?= (int) $tarea_sel['puntos'] ?></span><?php endif; ?>
                            <?php if ($en['id']): ?><small>Entregado <?= fecha_hora_texto($en['entregado_en']) ?></small><?php endif; ?>
                        </div>
                        <?php if ($en['id']): ?>
                            <?php if ($en['contenido']): ?><p class="mensaje-texto"><?= texto_html($en['contenido']) ?></p><?php endif; ?>
                            <?php if ($en['url']): ?><p style="margin-top: 6px"><a class="chip" href="<?= e($en['url']) ?>" target="_blank" rel="noopener"><?= icono('external-link', 13) ?><span><?= e($en['url']) ?></span></a></p><?php endif; ?>
                            <form method="post" class="barra-filtros" style="margin: 12px 0 0">
                                <?= campo_csrf() ?><input type="hidden" name="accion" value="calificar"><input type="hidden" name="id" value="<?= $en['id'] ?>">
                                <input class="input" style="width: 110px" type="number" step="0.5" min="0" max="<?= (int) $tarea_sel['puntos'] ?>" name="calificacion" required value="<?= e($en['calificacion'] !== null ? rtrim(rtrim($en['calificacion'], '0'), '.') : '') ?>" placeholder="/ <?= (int) $tarea_sel['puntos'] ?>" aria-label="Calificación">
                                <input class="input" style="flex: 1; min-width: 200px" name="comentario" maxlength="2000" value="<?= e($en['comentario']) ?>" placeholder="Comentario para el alumno (opcional)" aria-label="Comentario">
                                <button class="btn btn-primario"><span class="spinner"></span><?= $en['calificacion'] === null ? 'Calificar' : 'Actualizar' ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php else: ?>
        <div class="barra-filtros">
            <button class="btn btn-primario" data-abrir="dlg-tarea" data-titulo="Agregar evaluación" id="btn-nueva-tarea"><?= icono('plus', 17) ?> Agregar evaluación</button>
        </div>
        <section class="tarjeta">
            <?php if (!$tareas): ?>
                <div class="estado-vacio"><span class="estado-vacio-icono"><?= icono('clipboard-list', 26) ?></span><h3>Sin evaluaciones</h3><p>Crea tareas con fecha de entrega y puntaje para tus alumnos.</p></div>
            <?php else: ?>
                <div class="tabla-scroll"><table class="tabla">
                    <thead><tr><th>Evaluación</th><th>Fecha de entrega</th><th>Puntos</th><th>Entregas</th><th>Promedio</th><th class="col-acciones">Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($tareas as $t): ?>
                        <tr>
                            <td><a href="?id=<?= $cid ?>&tab=evaluaciones&tarea=<?= $t['id'] ?>" class="celda-usuario" style="text-decoration:none"><span class="lista-icono"><?= icono('clipboard-list', 16) ?></span><span><strong><?= e($t['titulo']) ?></strong><small><?= e($t['bloque_titulo'] ?? 'Todo el curso') ?></small></span></a></td>
                            <td><?= fecha_hora_texto($t['fecha_entrega']) ?><span class="celda-sub"><?= plazo_texto($t['fecha_entrega']) ?></span></td>
                            <td><?= (int) $t['puntos'] ?></td>
                            <td><?= (int) $t['entregas'] ?>/<?= count($alumnos) ?><?php if ($t['entregas'] - $t['calificadas']): ?><span class="celda-sub"><?= $t['entregas'] - $t['calificadas'] ?> por calificar</span><?php endif; ?></td>
                            <td><?= $t['promedio'] !== null ? round($t['promedio']) . '%' : '—' ?></td>
                            <td class="col-acciones"><div class="acciones">
                                <a class="btn btn-contorno btn-chico" href="?id=<?= $cid ?>&tab=evaluaciones&tarea=<?= $t['id'] ?>">Calificar</a>
                                <button class="btn-icono" aria-label="Editar" title="Editar" data-abrir="dlg-tarea" data-titulo="Editar evaluación" data-valores='<?= e(json_encode(['id' => $t['id'], 'titulo' => $t['titulo'], 'descripcion' => $t['descripcion'], 'puntos' => $t['puntos'], 'bloque_id' => $t['bloque_id'] ?? '', 'fecha_entrega' => $t['fecha_entrega'] ? date('Y-m-d\TH:i', strtotime($t['fecha_entrega'])) : ''])) ?>'><?= icono('pencil', 16) ?></button>
                                <form method="post" data-confirmar="Se eliminará «<?= e($t['titulo']) ?>» con todas sus entregas y calificaciones." data-confirmar-titulo="Eliminar evaluación">
                                    <?= campo_csrf() ?><input type="hidden" name="accion" value="tarea_eliminar"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button class="btn-icono peligro" aria-label="Eliminar" title="Eliminar"><?= icono('trash', 16) ?></button>
                                </form>
                            </div></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </section>
        <dialog class="dialogo" id="dlg-tarea">
            <form method="post">
                <?= campo_csrf() ?><input type="hidden" name="accion" value="tarea_guardar"><input type="hidden" name="id">
                <header class="dialogo-encabezado"><h2>Agregar evaluación</h2><button type="button" class="btn-icono" data-cerrar aria-label="Cerrar"><?= icono('x', 18) ?></button></header>
                <div class="dialogo-cuerpo formulario-rejilla">
                    <div class="campo completo"><label for="e-titulo">Título</label><input class="input" id="e-titulo" name="titulo" maxlength="150" required></div>
                    <div class="campo completo"><label for="e-desc">Instrucciones</label><textarea class="textarea" id="e-desc" name="descripcion" rows="5" maxlength="5000"></textarea></div>
                    <div class="campo"><label for="e-fecha">Fecha de entrega</label><input class="input" type="datetime-local" id="e-fecha" name="fecha_entrega"></div>
                    <div class="campo"><label for="e-puntos">Puntos</label><input class="input" type="number" min="1" max="1000" id="e-puntos" name="puntos" value="100"></div>
                    <div class="campo completo"><label for="e-bloque">Bloque</label><select class="select" id="e-bloque" name="bloque_id"><?= $opciones_bloques(true) ?></select></div>
                </div>
                <footer class="dialogo-pie"><button type="button" class="btn btn-contorno" data-cerrar>Cancelar</button><button class="btn btn-primario"><span class="spinner"></span>Guardar</button></footer>
            </form>
        </dialog>
        <?php if (!empty($_GET['nueva'])): ?><script>addEventListener('load', () => document.getElementById('btn-nueva-tarea').click());</script><?php endif; ?>
    <?php endif; ?>

<?php elseif ($tab === 'alumnos'): ?>
    <?php $disponibles = alumnos_disponibles($cid); ?>
    <div class="barra-filtros">
        <button class="btn btn-primario" data-abrir="dlg-inscribir"<?= $disponibles ? '' : ' disabled title="No hay más alumnos activos para inscribir"' ?>><?= icono('user-plus', 17) ?> Inscribir alumnos</button>
    </div>
    <section class="tarjeta">
        <?php if (!$alumnos): ?>
            <div class="estado-vacio"><span class="estado-vacio-icono"><?= icono('users', 26) ?></span><h3>Sin alumnos inscritos</h3><p>Inscribe alumnos para que puedan ver el curso cuando esté publicado.</p></div>
        <?php else: ?>
            <div class="tabla-scroll"><table class="tabla">
                <thead><tr><th>Alumno</th><th>Avance</th><th>Entregas</th><th>Promedio</th><th>Último acceso</th><th class="col-acciones">Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($alumnos as $a): ?>
                    <tr>
                        <td><div class="celda-usuario"><?= avatar_html($a) ?><div><strong><?= e($a['nombre']) ?></strong><small><?= e($a['email']) ?></small></div></div></td>
                        <td><?= barra_progreso($progresos[$a['id']]['porcentaje'] ?? 0) ?></td>
                        <td><?= (int) $a['entregas'] ?>/<?= count($tareas) ?></td>
                        <td><?= $a['promedio'] !== null ? round($a['promedio']) . '%' : '—' ?></td>
                        <td class="celda-fecha"><?= fecha_corta($a['ultimo_acceso']) ?></td>
                        <td class="col-acciones"><form method="post" data-confirmar="<?= e($a['nombre']) ?> dejará de tener acceso al curso. Su avance se conserva si lo vuelves a inscribir." data-confirmar-titulo="Dar de baja">
                            <?= campo_csrf() ?><input type="hidden" name="accion" value="quitar_alumno"><input type="hidden" name="alumno_id" value="<?= $a['id'] ?>">
                            <button class="btn-icono peligro" title="Dar de baja" aria-label="Dar de baja a <?= e($a['nombre']) ?>"><?= icono('user-minus', 17) ?></button>
                        </form></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </section>
    <dialog class="dialogo" id="dlg-inscribir">
        <form method="post">
            <?= campo_csrf() ?><input type="hidden" name="accion" value="inscribir">
            <header class="dialogo-encabezado"><h2>Inscribir alumnos</h2><button type="button" class="btn-icono" data-cerrar aria-label="Cerrar"><?= icono('x', 18) ?></button></header>
            <div class="dialogo-cuerpo">
                <label class="etiqueta" for="ins-alumnos">Alumnos activos</label>
                <select class="select" id="ins-alumnos" name="alumnos[]" multiple size="10">
                    <?php foreach ($disponibles as $d): ?><option value="<?= $d['id'] ?>"><?= e($d['nombre']) ?> — <?= e($d['email']) ?></option><?php endforeach; ?>
                </select>
                <small class="ayuda">Mantén presionado Ctrl (o ⌘) para elegir varios.</small>
            </div>
            <footer class="dialogo-pie"><button type="button" class="btn btn-contorno" data-cerrar>Cancelar</button><button class="btn btn-primario"><span class="spinner"></span>Inscribir</button></footer>
        </form>
    </dialog>

<?php else: ?>
    <?php
    $con_promedio = array_filter(array_column($alumnos, 'promedio'), fn ($p) => $p !== null);
    $total_entregas = array_sum(array_column($tareas, 'entregas'));
    $esperadas = count($tareas) * count($alumnos);
    ?>
    <section class="metricas">
        <?php foreach ([
            ['Alumnos inscritos', count($alumnos), 'users'],
            ['Avance promedio', $promedio_avance . '%', 'trending-up'],
            ['Entregas recibidas', $esperadas ? round($total_entregas / $esperadas * 100) . '%' : '—', 'clipboard-list'],
            ['Calificación promedio', $con_promedio ? round(array_sum($con_promedio) / count($con_promedio)) . '%' : '—', 'award'],
        ] as [$titulo, $valor, $ico]): ?>
            <article class="tarjeta metrica"><span class="metrica-icono"><?= icono($ico, 20) ?></span><div><p class="metrica-titulo"><?= $titulo ?></p><p class="metrica-valor"><?= $valor ?></p></div></article>
        <?php endforeach; ?>
    </section>
    <div class="columnas">
        <section class="tarjeta">
            <div class="encabezado-tarjeta"><h2>Avance por tema</h2><span class="texto-tenue">alumnos que lo completaron</span></div>
            <?php
            $stmt = $pdo->prepare("SELECT p.tema_id, COUNT(*) FROM progreso_temas p JOIN inscripciones i ON i.alumno_id = p.alumno_id AND i.curso_id = ? GROUP BY p.tema_id");
            $stmt->execute([$cid]);
            $por_tema = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            ?>
            <?php if (!$temas): ?><p class="vacio-mini">El curso no tiene temas.</p><?php endif; ?>
            <ul class="lista">
                <?php foreach ($estructura as $b): foreach ($b['temas'] as $t): $n = (int) ($por_tema[$t['id']] ?? 0); ?>
                    <li><div class="lista-cuerpo"><strong><?= e($t['titulo']) ?></strong><small><?= e($b['titulo']) ?> · <?= $n ?> de <?= count($alumnos) ?></small></div>
                        <div style="width: 180px"><?= barra_progreso($alumnos ? (int) round($n / count($alumnos) * 100) : 0) ?></div></li>
                <?php endforeach; endforeach; ?>
            </ul>
        </section>
        <aside>
            <section class="tarjeta">
                <div class="encabezado-tarjeta"><h3>Evaluaciones</h3></div>
                <?php if (!$tareas): ?><p class="vacio-mini">Sin evaluaciones.</p><?php endif; ?>
                <ul class="lista">
                    <?php foreach ($tareas as $t): ?>
                        <li><div class="lista-cuerpo"><strong><?= e($t['titulo']) ?></strong><small><?= (int) $t['entregas'] ?> entregas · promedio <?= $t['promedio'] !== null ? round($t['promedio']) . '%' : '—' ?></small></div></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </aside>
    </div>
<?php endif; ?>
<?php panel_fin($tab === 'estructura' ? ['assets/js/estructura.js'] : []); ?>
