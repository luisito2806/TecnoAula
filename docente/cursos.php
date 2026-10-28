<?php
require_once __DIR__ . '/../includes/cursos.php';

$usuario = requiere_login();
if (!puede_crear_cursos($usuario)) {
    acceso_denegado();
}

if (accion_post() === 'crear') {
    $titulo = texto_post('titulo');
    if ($titulo === '') {
        error_formulario('Escribe el título del curso.');
    }
    $modalidad = isset(MODALIDADES[$_POST['modalidad'] ?? '']) ? $_POST['modalidad'] : 'en_linea';
    $pdo->prepare("INSERT INTO cursos (titulo, descripcion, categoria, ponente_id, estado, modalidad, fecha_inicio) VALUES (?, ?, ?, ?, 'borrador', ?, ?)")
        ->execute([$titulo, texto_post('descripcion', 2000), texto_post('categoria', 100) ?: null, $usuario['id'], $modalidad, fecha_valida($_POST['fecha_inicio'] ?? '')]);
    $id = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO bloques (curso_id, titulo, orden) VALUES (?, 'Bloque 1', 1)")->execute([$id]);
    hecho('Curso creado. Ahora arma su estructura.', "docente/curso.php?id=$id");
}

$buscar = trim($_GET['buscar'] ?? '');
$estado = in_array($_GET['estado'] ?? '', ['publicado', 'borrador'], true) ? $_GET['estado'] : '';
$todos = listar_cursos(['docente' => $usuario['id']]);
$cursos = listar_cursos(['docente' => $usuario['id'], 'buscar' => $buscar, 'estado' => $estado]);
$ids = array_map(fn ($c) => (int) $c['id'], $todos);

// Métricas de todos mis cursos
$alumnos_unicos = 0;
$pendientes = 0;
$promedio = 0;
if ($ids) {
    $marcas = implode(',', $ids);
    $alumnos_unicos = (int) $pdo->query("SELECT COUNT(DISTINCT alumno_id) FROM inscripciones WHERE curso_id IN ($marcas)")->fetchColumn();
    $pendientes = (int) $pdo->query("SELECT COUNT(*) FROM entregas e JOIN tareas t ON t.id = e.tarea_id WHERE t.curso_id IN ($marcas) AND e.calificacion IS NULL")->fetchColumn();
    $porcentajes = [];
    foreach ($ids as $id) {
        foreach (progreso_alumnos($id) as $p) {
            $porcentajes[] = $p['porcentaje'];
        }
    }
    $promedio = $porcentajes ? (int) round(array_sum($porcentajes) / count($porcentajes)) : 0;
}
$sin_leer = array_sum(chat_no_leidos($usuario['id'], $ids));

panel_inicio($usuario, 'Mis cursos', $usuario['rol'] === 'ponente' ? 'cursos' : 'mis-cursos');
encabezado('Mis cursos', 'Crea y administra tus cursos, contenidos, evaluaciones y alumnos.', ['Mis cursos' => null],
    '<button class="btn btn-primario" data-abrir="dlg-curso">' . icono('plus', 18) . ' Crear nuevo curso</button>');
?>
<section class="metricas">
    <?php foreach ([
        ['Total de alumnos', $alumnos_unicos, 'users', 'en ' . count($ids) . ' cursos'],
        ['Tareas por calificar', $pendientes, 'clipboard-list', 'entregas pendientes'],
        ['Mensajes sin leer', $sin_leer, 'message-square', 'en los chats de tus cursos'],
        ['Progreso promedio', $promedio . '%', 'trending-up', 'de tus alumnos'],
    ] as [$titulo, $valor, $ico, $nota]): ?>
        <article class="tarjeta metrica">
            <span class="metrica-icono"><?= icono($ico, 20) ?></span>
            <div><p class="metrica-titulo"><?= e($titulo) ?></p><p class="metrica-valor"><?= e((string) $valor) ?></p><p class="metrica-tendencia"><?= e($nota) ?></p></div>
        </article>
    <?php endforeach; ?>
</section>

<form class="barra-filtros" method="get">
    <label class="input-icono"><?= icono('search', 17) ?><input type="search" name="buscar" value="<?= e($buscar) ?>" placeholder="Buscar curso por título o categoría…" aria-label="Buscar curso"></label>
    <select name="estado" class="select" aria-label="Estado" onchange="this.form.submit()">
        <option value="">Todos los estados</option>
        <option value="publicado"<?= $estado === 'publicado' ? ' selected' : '' ?>>Publicado</option>
        <option value="borrador"<?= $estado === 'borrador' ? ' selected' : '' ?>>Borrador</option>
    </select>
    <button class="btn btn-contorno">Buscar</button>
</form>

<?php if (!$cursos): ?>
    <div class="tarjeta estado-vacio">
        <span class="estado-vacio-icono"><?= icono('book-open', 26) ?></span>
        <h3><?= $todos ? 'No hay cursos con esos filtros' : 'Todavía no tienes cursos' ?></h3>
        <p><?= $todos ? 'Prueba con otra búsqueda.' : 'Crea tu primer curso y organízalo en bloques y temas.' ?></p>
        <?php if (!$todos): ?><button class="btn btn-primario" data-abrir="dlg-curso"><?= icono('plus', 18) ?> Crear nuevo curso</button><?php endif; ?>
    </div>
<?php else: ?>
    <div class="grid-cursos">
        <?php foreach ($cursos as $c): ?>
            <?= tarjeta_curso($c, url('docente/curso.php?id=' . $c['id']),
                '<div class="tarjeta-curso-pie">' . badge_estado_curso($c['estado']) . '<span>' . (int) $c['total_alumnos'] . ' alumnos · ' . (int) $c['total_temas'] . ' temas</span></div>') ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<dialog class="dialogo" id="dlg-curso">
    <form method="post">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="crear">
        <header class="dialogo-encabezado"><h2>Crear nuevo curso</h2><button type="button" class="btn-icono" data-cerrar aria-label="Cerrar"><?= icono('x', 18) ?></button></header>
        <div class="dialogo-cuerpo formulario-rejilla">
            <div class="campo completo"><label for="c-titulo">Título</label><input class="input" id="c-titulo" name="titulo" maxlength="150" required placeholder="Ej. Programación Web con PHP"></div>
            <div class="campo completo"><label for="c-desc">Descripción</label><textarea class="textarea" id="c-desc" name="descripcion" maxlength="2000" placeholder="¿Qué aprenderán los alumnos?"></textarea></div>
            <div class="campo"><label for="c-cat">Categoría</label><input class="input" id="c-cat" name="categoria" maxlength="100" placeholder="Ej. Desarrollo web"></div>
            <div class="campo"><label for="c-mod">Modalidad</label><select class="select" id="c-mod" name="modalidad"><?php foreach (MODALIDADES as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
            <div class="campo"><label for="c-fecha">Fecha de inicio</label><input class="input" type="date" id="c-fecha" name="fecha_inicio"></div>
        </div>
        <footer class="dialogo-pie">
            <button type="button" class="btn btn-contorno" data-cerrar>Cancelar</button>
            <button class="btn btn-primario"><span class="spinner"></span>Crear curso</button>
        </footer>
    </form>
</dialog>
<?php panel_fin(); ?>
