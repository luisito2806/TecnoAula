<?php
// Vista del curso (alumno inscrito; docente y supervisores la ven como vista previa)
require_once __DIR__ . '/includes/cursos.php';

$usuario = requiere_login();
$curso = curso_de_url($usuario);
$cid = $curso['id'];
$es_alumno = esta_inscrito($usuario['id'], $cid) && $curso['estado'] === 'publicado';
$es_editor = puede_editar_curso($usuario, $curso);
$pestanas = ['general' => 'Vista general', 'bloques' => 'Bloques y temas', 'materiales' => 'Materiales', 'foros' => 'Foros', 'chat' => 'Chat', 'tareas' => 'Tareas', 'calificaciones' => 'Calificaciones'];
$tab = isset($pestanas[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'general';
$tema_id = (int) ($_GET['tema'] ?? 0);
if ($tema_id) {
    $tab = 'bloques';
}

$estructura = estructura_curso($cid, $es_alumno ? $usuario['id'] : null);
$temas = temas_en_orden($estructura);

switch (accion_post()) {
    case null:
        break;

    case 'completar':
    case 'pendiente':
        if (!$es_alumno) {
            error_formulario('Solo los alumnos inscritos registran avance.');
        }
        $id = entero_post('tema_id');
        if (!in_array($id, array_map(fn ($t) => (int) $t['id'], $temas), true)) {
            error_formulario('El tema no pertenece a este curso.');
        }
        if ($_POST['accion'] === 'completar') {
            $pdo->prepare("INSERT IGNORE INTO progreso_temas (alumno_id, tema_id) VALUES (?, ?)")->execute([$usuario['id'], $id]);
            $siguiente = entero_post('siguiente');
            hecho('Tema completado', $siguiente ? "curso.php?id=$cid&tema=$siguiente" : "curso.php?id=$cid&tema=$id");
        }
        $pdo->prepare("DELETE FROM progreso_temas WHERE alumno_id = ? AND tema_id = ?")->execute([$usuario['id'], $id]);
        hecho('Tema marcado como pendiente');

    case 'entregar':
        if (!$es_alumno) {
            error_formulario('Solo los alumnos inscritos pueden entregar tareas.');
        }
        $stmt = $pdo->prepare("SELECT t.id, t.titulo, e.calificacion, e.id AS entrega_id FROM tareas t
            LEFT JOIN entregas e ON e.tarea_id = t.id AND e.alumno_id = ? WHERE t.id = ? AND t.curso_id = ?");
        $stmt->execute([$usuario['id'], entero_post('tarea_id'), $cid]);
        $tarea = $stmt->fetch();
        $contenido = texto_post('contenido', 5000);
        $enlace = trim($_POST['url'] ?? '');
        if (!$tarea) {
            error_formulario('La tarea no existe.');
        }
        if ($tarea['calificacion'] !== null) {
            error_formulario('Esta tarea ya fue calificada; no se puede modificar la entrega.');
        }
        if ($contenido === '' && $enlace === '') {
            error_formulario('Escribe tu respuesta o agrega un enlace.');
        }
        if ($enlace !== '' && !url_valida($enlace)) {
            error_formulario('El enlace no es válido (debe empezar con http:// o https://).');
        }
        $pdo->prepare("INSERT INTO entregas (tarea_id, alumno_id, contenido, url) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE contenido = VALUES(contenido), url = VALUES(url), entregado_en = NOW()")
            ->execute([$tarea['id'], $usuario['id'], $contenido ?: null, $enlace ?: null]);
        registrar_actividad($usuario['id'], 'entrega', "Entregó «{$tarea['titulo']}»");
        hecho($tarea['entrega_id'] ? 'Entrega actualizada' : 'Tarea entregada');

    case 'foro_crear':
        if (!$es_editor) {
            error_formulario('Solo el docente puede abrir foros.');
        }
        if (texto_post('titulo') === '') {
            error_formulario('El foro necesita un título.');
        }
        $pdo->prepare("INSERT INTO foros (curso_id, titulo, descripcion, creado_por) VALUES (?, ?, ?, ?)")
            ->execute([$cid, texto_post('titulo'), texto_post('descripcion', 2000) ?: null, $usuario['id']]);
        hecho('Foro creado', 'foro.php?id=' . $pdo->lastInsertId());

    case 'foro_eliminar':
        if (!$es_editor) {
            error_formulario('Solo el docente puede eliminar foros.');
        }
        $pdo->prepare("DELETE FROM foros WHERE id = ? AND curso_id = ?")->execute([entero_post('id'), $cid]);
        hecho('Foro eliminado');

    default:
        error_formulario('Acción no válida.');
}

// Avance y siguiente tema por estudiar
$completados = count(array_filter($temas, fn ($t) => $t['completado']));
$porcentaje = $temas ? (int) round($completados / count($temas) * 100) : 0;
$siguiente_tema = null;
foreach ($temas as $t) {
    if (!$t['completado']) {
        $siguiente_tema = $t;
        break;
    }
}

$stmt = $pdo->prepare("SELECT t.*, e.id AS entrega_id, e.contenido, e.url, e.entregado_en, e.calificacion, e.comentario
    FROM tareas t LEFT JOIN entregas e ON e.tarea_id = t.id AND e.alumno_id = ?
    WHERE t.curso_id = ? ORDER BY t.fecha_entrega IS NULL, t.fecha_entrega, t.id");
$stmt->execute([$usuario['id'], $cid]);
$tareas = $stmt->fetchAll();
$pendientes = array_filter($tareas, fn ($t) => !$t['entrega_id']);
$calificadas = array_filter($tareas, fn ($t) => $t['calificacion'] !== null);

function estado_tarea(array $t): array
{
    if ($t['calificacion'] !== null) {
        return ['Calificada', 'badge-claro'];
    }
    if ($t['entrega_id']) {
        return ['Entregada', ''];
    }
    return [$t['fecha_entrega'] && strtotime($t['fecha_entrega']) < time() ? 'Vencida' : 'Pendiente', 'badge-tenue'];
}

function icono_tema(array $tema, ?array $siguiente): string
{
    if ($tema['completado']) {
        return icono('check-circle', 17, 'estado-tema hecho');
    }
    return icono($siguiente && $siguiente['id'] === $tema['id'] ? 'circle-dot' : 'circle', 17, 'estado-tema');
}

$migas = $es_alumno ? ['Mis cursos' => 'alumno/cursos.php'] : ($es_editor ? ['Editar curso' => "docente/curso.php?id=$cid"] : ['Cursos' => 'admin/cursos.php']);
panel_inicio($usuario, $curso['titulo'], 'cursos');
encabezado(e($curso['titulo']), '', $migas + [$curso['titulo'] => null],
    $es_editor ? '<a class="btn btn-contorno" href="' . url("docente/curso.php?id=$cid") . '">' . icono('pencil', 16) . ' Editar curso</a>' : '');

if (!$es_alumno): ?>
    <div class="alerta"><?= icono('eye', 17) ?><span>Estás viendo el curso como <strong>vista previa</strong>. El avance, las entregas y las calificaciones solo se registran para los alumnos inscritos<?= $curso['estado'] === 'borrador' ? ', y el curso sigue en borrador' : '' ?>.</span></div>
<?php endif; ?>

<section class="tarjeta curso-cabecera">
    <?= miniatura_html($curso, $temas ? duracion_texto((int) array_sum(array_column($temas, 'duracion_min'))) : '') ?>
    <div>
        <?= badge_estado_curso($curso['estado']) ?>
        <p style="margin-top: 12px"><?= e($curso['descripcion']) ?></p>
        <div class="datos-curso">
            <span><?= icono('user', 16) ?> <?= e($curso['docente_nombre'] ?? 'Sin docente') ?></span>
            <span><?= icono('calendar', 16) ?> Inicio: <?= fecha_corta($curso['fecha_inicio']) ?></span>
            <span><?= icono('globe', 16) ?> <?= MODALIDADES[$curso['modalidad']] ?></span>
            <span><?= icono('layers', 16) ?> <?= count($estructura) ?> bloques · <?= count($temas) ?> temas</span>
        </div>
        <?php if ($es_alumno): ?><?= barra_progreso($porcentaje) ?><?php endif; ?>
    </div>
</section>

<nav class="tabs">
    <?php foreach ($pestanas as $clave => $texto): ?>
        <a href="?id=<?= $cid ?>&tab=<?= $clave ?>" class="<?= $tab === $clave ? 'activa' : '' ?>"><?= $texto ?>
            <?php if ($clave === 'tareas' && $es_alumno && $pendientes): ?><span class="badge"><?= count($pendientes) ?></span><?php endif; ?>
            <?php if ($clave === 'chat' && ($n = chat_no_leidos($usuario['id'], [$cid])[$cid] ?? 0) && $tab !== 'chat'): ?><span class="badge"><?= $n ?></span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($tab === 'general'): ?>
    <?php if ($es_alumno && $siguiente_tema): ?>
        <section class="banner-promo banner-panel">
            <span class="banner-promo-destacado"><?= $porcentaje ?>% completado</span>
            <span class="banner-promo-texto">Sigue con: <?= e($siguiente_tema['titulo']) ?></span>
            <a class="btn btn-gradiente" href="?id=<?= $cid ?>&tema=<?= $siguiente_tema['id'] ?>">Continuar aprendiendo <?= icono('arrow-right', 16) ?></a>
        </section>
    <?php elseif ($es_alumno && $temas): ?>
        <div class="alerta alerta-exito"><?= icono('award', 18) ?><span>¡Completaste todos los temas del curso!</span></div>
    <?php endif; ?>
    <div class="columnas">
        <section class="tarjeta">
            <div class="encabezado-tarjeta"><h2>Contenido del curso</h2><?php if ($es_alumno): ?><span class="texto-tenue"><?= $completados ?> de <?= count($temas) ?> temas completados</span><?php endif; ?></div>
            <?php if (!$estructura): ?><p class="vacio-mini">El docente aún no agrega contenido.</p><?php endif; ?>
            <ul class="lista">
                <?php foreach ($estructura as $n => $b):
                    $hechos = count(array_filter($b['temas'], fn ($t) => $t['completado'])); ?>
                    <li>
                        <span class="lista-icono"><?= $n + 1 ?></span>
                        <div class="lista-cuerpo"><strong><?= e($b['titulo']) ?></strong>
                            <small><?= count($b['temas']) ?> temas · <?= duracion_texto((int) array_sum(array_column($b['temas'], 'duracion_min'))) ?><?= $es_alumno ? " · $hechos completados" : '' ?></small></div>
                        <?php if ($es_alumno && $b['temas']): ?><div style="width: 160px"><?= barra_progreso((int) round($hechos / count($b['temas']) * 100)) ?></div><?php endif; ?>
                        <a class="btn btn-contorno btn-chico" href="?id=<?= $cid ?>&tab=bloques#bloque-<?= $b['id'] ?>">Ver temas</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <aside>
            <section class="tarjeta">
                <div class="encabezado-tarjeta"><h3>Próximas tareas</h3><a class="enlace" href="?id=<?= $cid ?>&tab=tareas">Ver todas</a></div>
                <?php $proximas = array_slice(array_filter($tareas, fn ($t) => !$t['entrega_id'] || !$es_alumno), 0, 3); ?>
                <?php if (!$proximas): ?><p class="vacio-mini">No tienes tareas pendientes.</p><?php endif; ?>
                <ul class="lista">
                    <?php foreach ($proximas as $t): ?>
                        <li><span class="lista-icono"><?= icono('clipboard-list', 16) ?></span><div class="lista-cuerpo"><strong><?= e($t['titulo']) ?></strong><small><?= plazo_texto($t['fecha_entrega']) ?></small></div></li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php if ($es_alumno): ?>
                <section class="tarjeta">
                    <div class="encabezado-tarjeta"><h3>Calificaciones recientes</h3><a class="enlace" href="?id=<?= $cid ?>&tab=calificaciones">Ver todas</a></div>
                    <?php if (!$calificadas): ?><p class="vacio-mini">Aún no tienes calificaciones.</p><?php endif; ?>
                    <ul class="lista">
                        <?php foreach (array_slice($calificadas, 0, 3) as $t): ?>
                            <li><div class="lista-cuerpo"><strong><?= e($t['titulo']) ?></strong><small><?= fecha_corta($t['entregado_en']) ?></small></div><span class="badge badge-claro"><?= rtrim(rtrim($t['calificacion'], '0'), '.') ?>/<?= (int) $t['puntos'] ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
            <section class="tarjeta cuerpo-tarjeta">
                <h3 style="margin-bottom: 12px">Accesos rápidos</h3>
                <div class="materiales">
                    <a class="chip" href="?id=<?= $cid ?>&tab=materiales"><?= icono('file-text', 14) ?><span>Materiales</span></a>
                    <a class="chip" href="?id=<?= $cid ?>&tab=foros"><?= icono('message-circle', 14) ?><span>Foros</span></a>
                    <a class="chip" href="?id=<?= $cid ?>&tab=chat"><?= icono('message-square', 14) ?><span>Chat</span></a>
                    <a class="chip" href="?id=<?= $cid ?>&tab=tareas"><?= icono('clipboard-list', 14) ?><span>Tareas</span></a>
                </div>
            </section>
        </aside>
    </div>

<?php elseif ($tab === 'bloques' && $tema_id): ?>
    <?php
    $actual = null;
    foreach ($temas as $i => $t) {
        if ((int) $t['id'] === $tema_id) {
            $actual = $t;
            $anterior = $temas[$i - 1] ?? null;
            $proximo = $temas[$i + 1] ?? null;
        }
    }
    if (!$actual) {
        echo '<div class="tarjeta estado-vacio"><h3>Tema no encontrado</h3></div>';
        panel_fin();
        exit;
    }
    $bloque_actual = array_values(array_filter($estructura, fn ($b) => (int) $b['id'] === (int) $actual['bloque_id']))[0];
    ?>
    <div class="curso-layout">
        <nav class="tarjeta temario" aria-label="Temario">
            <?php foreach ($estructura as $b): ?>
                <div class="temario-bloque"><strong><?= e(strtoupper($b['titulo'])) ?></strong>
                    <?php foreach ($b['temas'] as $t): ?>
                        <a href="?id=<?= $cid ?>&tema=<?= $t['id'] ?>" class="<?= (int) $t['id'] === $tema_id ? 'activo' : '' ?>"><?= icono_tema($t, $siguiente_tema) ?><span><?= e($t['titulo']) ?></span><?php if ($t['duracion_min']): ?><small><?= duracion_texto((int) $t['duracion_min']) ?></small><?php endif; ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>
        <article class="tarjeta tarjeta-relleno contenido-tema">
            <span class="bloque-numero"><?= e($bloque_actual['titulo']) ?><?= $actual['duracion_min'] ? ' · ' . duracion_texto((int) $actual['duracion_min']) : '' ?></span>
            <h2><?= e($actual['titulo']) ?></h2>
            <?php if ($actual['completado']): ?><span class="badge badge-claro"><?= icono('check', 13) ?> Completado</span><?php endif; ?>
            <?php foreach ($actual['materiales'] as $m): ?>
                <?php if ($m['tipo'] === 'video' && ($embed = video_embebido($m['url']))): ?>
                    <div class="video-embebido"><iframe src="<?= e($embed) ?>" title="<?= e($m['titulo']) ?>" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe></div>
                <?php endif; ?>
            <?php endforeach; ?>
            <div class="texto-largo" style="margin-top: 16px"><?= $actual['contenido'] ? texto_html($actual['contenido']) : '<span class="texto-tenue">Este tema no tiene texto; revisa sus materiales.</span>' ?></div>
            <?php if ($actual['materiales']): ?>
                <h3 style="margin: 24px 0 8px">Materiales</h3>
                <div class="materiales">
                    <?php foreach ($actual['materiales'] as $m): ?>
                        <a class="chip" href="<?= e($m['url']) ?>" target="_blank" rel="noopener"><?= icono(TIPOS_MATERIAL[$m['tipo']][1], 14) ?><span><?= e($m['titulo']) ?></span><?= icono('external-link', 12) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="navegacion-tema">
                <?php if ($anterior): ?><a class="btn btn-contorno" href="?id=<?= $cid ?>&tema=<?= $anterior['id'] ?>"><?= icono('chevron-left', 16) ?> Anterior</a><?php else: ?><span></span><?php endif; ?>
                <div class="encabezado-acciones">
                    <?php if ($es_alumno): ?>
                        <form method="post"><?= campo_csrf() ?><input type="hidden" name="tema_id" value="<?= $actual['id'] ?>">
                            <?php if ($actual['completado']): ?>
                                <button class="btn btn-contorno" name="accion" value="pendiente">Marcar como pendiente</button>
                            <?php else: ?>
                                <input type="hidden" name="siguiente" value="<?= $proximo['id'] ?? 0 ?>">
                                <button class="btn btn-primario" name="accion" value="completar"><span class="spinner"></span><?= icono('check', 16) ?> Marcar como completado<?= $proximo ? ' y seguir' : '' ?></button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                    <?php if ($proximo && (!$es_alumno || $actual['completado'])): ?><a class="btn btn-contorno" href="?id=<?= $cid ?>&tema=<?= $proximo['id'] ?>">Siguiente <?= icono('chevron-right', 16) ?></a><?php endif; ?>
                </div>
            </div>
        </article>
    </div>

<?php elseif ($tab === 'bloques'): ?>
    <div class="estructura">
        <?php if (!$estructura): ?><div class="tarjeta estado-vacio"><h3>Sin contenido todavía</h3></div><?php endif; ?>
        <?php foreach ($estructura as $n => $b): ?>
            <section class="bloque" id="bloque-<?= $b['id'] ?>">
                <header class="bloque-encabezado"><h3><span class="bloque-numero">Bloque <?= $n + 1 ?></span><br><?= e($b['titulo']) ?><?php if ($b['descripcion']): ?><small><?= e($b['descripcion']) ?></small><?php endif; ?></h3>
                    <span class="texto-tenue"><?= count($b['temas']) ?> temas</span></header>
                <ul class="lista lista-temas">
                    <?php foreach ($b['temas'] as $t): ?>
                        <li><?= icono_tema($t, $es_alumno ? $siguiente_tema : null) ?>
                            <a class="lista-cuerpo" href="?id=<?= $cid ?>&tema=<?= $t['id'] ?>"><strong><?= e($t['titulo']) ?></strong>
                                <small><?= $es_alumno ? ($t['completado'] ? 'Completado' : ($siguiente_tema && $siguiente_tema['id'] === $t['id'] ? 'En curso' : 'Pendiente')) . ' · ' : '' ?><?= $t['duracion_min'] ? duracion_texto((int) $t['duracion_min']) : 'Sin duración' ?> · <?= count($t['materiales']) ?> materiales</small></a>
                            <a class="btn-icono" href="?id=<?= $cid ?>&tema=<?= $t['id'] ?>" aria-label="Abrir tema"><?= icono('chevron-right', 17) ?></a></li>
                    <?php endforeach; ?>
                    <?php if (!$b['temas']): ?><li class="texto-tenue">Sin temas.</li><?php endif; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </div>

<?php elseif ($tab === 'materiales'): ?>
    <section class="tarjeta">
        <?php $hay = false; ?>
        <ul class="lista">
            <?php foreach ($temas as $t): foreach ($t['materiales'] as $m): $hay = true; ?>
                <li><span class="lista-icono"><?= icono(TIPOS_MATERIAL[$m['tipo']][1], 16) ?></span>
                    <div class="lista-cuerpo"><strong><?= e($m['titulo']) ?></strong><small><?= TIPOS_MATERIAL[$m['tipo']][0] ?> · <a class="enlace" href="?id=<?= $cid ?>&tema=<?= $t['id'] ?>"><?= e($t['titulo']) ?></a></small></div>
                    <a class="btn btn-contorno btn-chico" href="<?= e($m['url']) ?>" target="_blank" rel="noopener">Abrir <?= icono('external-link', 14) ?></a></li>
            <?php endforeach; endforeach; ?>
        </ul>
        <?php if (!$hay): ?><div class="estado-vacio"><span class="estado-vacio-icono"><?= icono('file-text', 24) ?></span><h3>Sin materiales</h3><p>El docente aún no comparte materiales en este curso.</p></div><?php endif; ?>
    </section>

<?php elseif ($tab === 'foros'): ?>
    <?php
    $stmt = $pdo->prepare("SELECT f.*, u.nombre AS autor, COUNT(m.id) AS mensajes, MAX(m.creado_en) AS ultima
        FROM foros f LEFT JOIN usuarios u ON u.id = f.creado_por LEFT JOIN foro_mensajes m ON m.foro_id = f.id
        WHERE f.curso_id = ? GROUP BY f.id ORDER BY COALESCE(MAX(m.creado_en), f.creado_en) DESC");
    $stmt->execute([$cid]);
    $foros = $stmt->fetchAll();
    ?>
    <?php if ($es_editor): ?><div class="barra-filtros"><button class="btn btn-primario" data-abrir="dlg-foro"><?= icono('plus', 17) ?> Nuevo foro</button></div><?php endif; ?>
    <section class="tarjeta">
        <?php if (!$foros): ?><div class="estado-vacio"><span class="estado-vacio-icono"><?= icono('message-circle', 24) ?></span><h3>No hay foros</h3><p><?= $es_editor ? 'Abre un foro para que tus alumnos pregunten y debatan.' : 'El docente aún no abre foros en este curso.' ?></p></div><?php endif; ?>
        <ul class="lista">
            <?php foreach ($foros as $f): ?>
                <li><span class="lista-icono"><?= icono('message-circle', 16) ?></span>
                    <a class="lista-cuerpo" href="<?= url('foro.php?id=' . $f['id']) ?>" style="text-decoration:none"><strong><?= e($f['titulo']) ?></strong><small><?= (int) $f['mensajes'] ?> mensajes · <?= $f['ultima'] ? 'última actividad ' . fecha_hora_texto($f['ultima']) : 'sin mensajes' ?></small></a>
                    <?php if ($es_editor): ?>
                        <form method="post" data-confirmar="Se eliminará el foro «<?= e($f['titulo']) ?>» con todos sus mensajes."><?= campo_csrf() ?><input type="hidden" name="accion" value="foro_eliminar"><input type="hidden" name="id" value="<?= $f['id'] ?>"><button class="btn-icono peligro" aria-label="Eliminar foro"><?= icono('trash', 16) ?></button></form>
                    <?php endif; ?>
                    <a class="btn-icono" href="<?= url('foro.php?id=' . $f['id']) ?>" aria-label="Abrir foro"><?= icono('chevron-right', 17) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php if ($es_editor): ?>
        <dialog class="dialogo" id="dlg-foro"><form method="post">
            <?= campo_csrf() ?><input type="hidden" name="accion" value="foro_crear">
            <header class="dialogo-encabezado"><h2>Nuevo foro</h2><button type="button" class="btn-icono" data-cerrar aria-label="Cerrar"><?= icono('x', 18) ?></button></header>
            <div class="dialogo-cuerpo">
                <div class="campo"><label for="f-titulo">Tema del foro</label><input class="input" id="f-titulo" name="titulo" maxlength="150" required placeholder="Ej. Dudas del bloque 2"></div>
                <div class="campo"><label for="f-desc">Descripción</label><textarea class="textarea" id="f-desc" name="descripcion" maxlength="2000"></textarea></div>
            </div>
            <footer class="dialogo-pie"><button type="button" class="btn btn-contorno" data-cerrar>Cancelar</button><button class="btn btn-primario"><span class="spinner"></span>Crear foro</button></footer>
        </form></dialog>
    <?php endif; ?>

<?php elseif ($tab === 'chat'): ?>
    <?php
    $stmt = $pdo->prepare("SELECT * FROM (SELECT m.id, m.contenido, m.creado_en, m.usuario_id, u.nombre FROM chat_mensajes m JOIN usuarios u ON u.id = m.usuario_id
        WHERE m.curso_id = ? ORDER BY m.id DESC LIMIT 100) x ORDER BY id");
    $stmt->execute([$cid]);
    $mensajes = $stmt->fetchAll();
    $ultimo = $mensajes ? (int) end($mensajes)['id'] : 0;
    $pdo->prepare("INSERT INTO chat_lecturas (usuario_id, curso_id, ultimo_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE ultimo_id = GREATEST(ultimo_id, VALUES(ultimo_id))")
        ->execute([$usuario['id'], $cid, $ultimo]);
    ?>
    <section class="tarjeta chat" id="chat" data-api="<?= url('api/chat.php') ?>" data-curso="<?= $cid ?>" data-ultimo="<?= $ultimo ?>">
        <div class="encabezado-tarjeta"><h2>Chat del curso</h2><span class="texto-tenue">Se actualiza solo</span></div>
        <div class="chat-mensajes" id="chat-mensajes">
            <?php if (!$mensajes): ?><p class="vacio-mini" id="chat-vacio">Aún no hay mensajes. ¡Saluda a tu grupo!</p><?php endif; ?>
            <?php foreach ($mensajes as $m): $propio = (int) $m['usuario_id'] === $usuario['id']; ?>
                <div class="chat-mensaje<?= $propio ? ' propio' : '' ?>"><span class="chat-meta"><?= $propio ? 'Tú' : e($m['nombre']) ?> · <?= fecha_hora_texto($m['creado_en']) ?></span><div class="chat-burbuja"><?= texto_html($m['contenido']) ?></div></div>
            <?php endforeach; ?>
        </div>
        <form class="form-respuesta" id="chat-form" data-sin-carga>
            <textarea class="textarea" name="contenido" maxlength="2000" required placeholder="Escribe un mensaje… (Enter para enviar)" aria-label="Mensaje"></textarea>
            <button class="btn btn-primario" aria-label="Enviar"><?= icono('send', 17) ?></button>
        </form>
    </section>

<?php elseif ($tab === 'tareas'): ?>
    <section class="tarjeta">
        <?php if (!$tareas): ?><div class="estado-vacio"><span class="estado-vacio-icono"><?= icono('clipboard-list', 24) ?></span><h3>Sin tareas</h3><p>Este curso todavía no tiene evaluaciones.</p></div><?php endif; ?>
        <?php foreach ($tareas as $t): [$texto_estado, $clase] = estado_tarea($t); ?>
            <article class="mensaje">
                <span class="lista-icono"><?= icono('clipboard-list', 16) ?></span>
                <div class="mensaje-cuerpo">
                    <div class="mensaje-autor"><strong><?= e($t['titulo']) ?></strong>
                        <?php if ($es_alumno): ?><span class="badge <?= $clase ?>"><i class="punto"></i><?= $texto_estado ?></span><?php endif; ?>
                        <small><?= plazo_texto($t['fecha_entrega']) ?> · <?= fecha_hora_texto($t['fecha_entrega']) ?> · <?= (int) $t['puntos'] ?> puntos</small></div>
                    <?php if ($t['descripcion']): ?><p class="mensaje-texto"><?= texto_html($t['descripcion']) ?></p><?php endif; ?>
                    <?php if ($es_alumno && $t['entrega_id']): ?>
                        <div class="alerta" style="margin: 12px 0 0"><?= icono('check-circle', 17) ?><span>Entregaste el <?= fecha_hora_texto($t['entregado_en']) ?><?= $t['url'] ? ' · <a class="enlace" target="_blank" rel="noopener" href="' . e($t['url']) . '">ver enlace</a>' : '' ?>
                            <?php if ($t['calificacion'] !== null): ?><br><strong>Calificación: <?= rtrim(rtrim($t['calificacion'], '0'), '.') ?>/<?= (int) $t['puntos'] ?></strong><?= $t['comentario'] ? ' — ' . e($t['comentario']) : '' ?><?php endif; ?></span></div>
                    <?php endif; ?>
                    <?php if ($es_alumno && $t['calificacion'] === null): ?>
                        <p style="margin-top: 12px"><button class="btn <?= $t['entrega_id'] ? 'btn-contorno' : 'btn-primario' ?> btn-chico" data-abrir="dlg-entrega" data-titulo="<?= e($t['titulo']) ?>"
                            data-valores='<?= e(json_encode(['tarea_id' => $t['id'], 'contenido' => $t['contenido'] ?? '', 'url' => $t['url'] ?? ''])) ?>'><?= $t['entrega_id'] ? 'Editar entrega' : 'Entregar tarea' ?></button></p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
    <?php if ($es_alumno): ?>
        <dialog class="dialogo" id="dlg-entrega"><form method="post">
            <?= campo_csrf() ?><input type="hidden" name="accion" value="entregar"><input type="hidden" name="tarea_id">
            <header class="dialogo-encabezado"><h2>Entregar tarea</h2><button type="button" class="btn-icono" data-cerrar aria-label="Cerrar"><?= icono('x', 18) ?></button></header>
            <div class="dialogo-cuerpo">
                <div class="campo"><label for="en-cont">Tu respuesta</label><textarea class="textarea" id="en-cont" name="contenido" rows="6" maxlength="5000"></textarea></div>
                <div class="campo"><label for="en-url">Enlace (opcional)</label><input class="input" type="url" id="en-url" name="url" maxlength="500" placeholder="https://drive.google.com/…"><small class="ayuda">Comparte tu archivo en Drive, GitHub u otro servicio y pega aquí el enlace.</small></div>
            </div>
            <footer class="dialogo-pie"><button type="button" class="btn btn-contorno" data-cerrar>Cancelar</button><button class="btn btn-primario"><span class="spinner"></span><?= icono('send', 16) ?> Enviar entrega</button></footer>
        </form></dialog>
    <?php endif; ?>

<?php else: ?>
    <?php if (!$es_alumno): ?>
        <div class="tarjeta estado-vacio"><span class="estado-vacio-icono"><?= icono('award', 24) ?></span><h3>Calificaciones de los alumnos</h3><p>Consulta y captura calificaciones desde el editor del curso.</p>
            <?php if ($es_editor): ?><a class="btn btn-primario" href="<?= url("docente/curso.php?id=$cid&tab=evaluaciones") ?>">Ir a evaluaciones</a><?php endif; ?></div>
    <?php else: ?>
        <?php
        $obtenidos = array_sum(array_column($calificadas, 'calificacion'));
        $posibles = array_sum(array_column($calificadas, 'puntos'));
        ?>
        <section class="metricas">
            <article class="tarjeta metrica"><span class="metrica-icono"><?= icono('award', 20) ?></span><div><p class="metrica-titulo">Promedio</p><p class="metrica-valor"><?= $posibles ? round($obtenidos / $posibles * 100) . '%' : '—' ?></p></div></article>
            <article class="tarjeta metrica"><span class="metrica-icono"><?= icono('check-circle', 20) ?></span><div><p class="metrica-titulo">Calificadas</p><p class="metrica-valor"><?= count($calificadas) ?>/<?= count($tareas) ?></p></div></article>
            <article class="tarjeta metrica"><span class="metrica-icono"><?= icono('trending-up', 20) ?></span><div><p class="metrica-titulo">Avance</p><p class="metrica-valor"><?= $porcentaje ?>%</p></div></article>
        </section>
        <section class="tarjeta">
            <div class="tabla-scroll"><table class="tabla compacta">
                <thead><tr><th>Tarea</th><th>Estado</th><th>Calificación</th><th>Comentario del docente</th></tr></thead>
                <tbody>
                <?php foreach ($tareas as $t): [$texto_estado, $clase] = estado_tarea($t); ?>
                    <tr><td><strong><?= e($t['titulo']) ?></strong><span class="celda-sub"><?= fecha_corta($t['fecha_entrega']) ?></span></td>
                        <td><span class="badge <?= $clase ?>"><i class="punto"></i><?= $texto_estado ?></span></td>
                        <td><?= $t['calificacion'] !== null ? '<strong>' . rtrim(rtrim($t['calificacion'], '0'), '.') . '</strong> / ' . (int) $t['puntos'] : '—' ?></td>
                        <td class="texto-secundario"><?= e($t['comentario'] ?? '') ?: '—' ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$tareas): ?><tr><td colspan="4" class="vacio-mini">Sin tareas en este curso.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php panel_fin($tab === 'chat' ? ['assets/js/chat.js'] : []); ?>
