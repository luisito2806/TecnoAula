<?php
require_once __DIR__ . '/includes/cursos.php';

$usuario = requiere_login();
$stmt = $pdo->prepare("SELECT f.*, u.nombre AS autor FROM foros f LEFT JOIN usuarios u ON u.id = f.creado_por WHERE f.id = ?");
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$foro = $stmt->fetch();
$curso = $foro ? obtener_curso((int) $foro['curso_id']) : null;
if (!$curso || !puede_ver_curso($usuario, $curso)) {
    acceso_denegado();
}
$es_editor = puede_editar_curso($usuario, $curso);

switch (accion_post()) {
    case null:
        break;
    case 'responder':
        $contenido = texto_post('contenido', 5000);
        if ($contenido === '') {
            error_formulario('Escribe tu mensaje.');
        }
        $pdo->prepare("INSERT INTO foro_mensajes (foro_id, usuario_id, contenido) VALUES (?, ?, ?)")->execute([$foro['id'], $usuario['id'], $contenido]);
        hecho('Mensaje publicado');
    case 'eliminar':
        // Cada quien borra sus mensajes; el docente modera todo su foro
        $pdo->prepare("DELETE FROM foro_mensajes WHERE id = ? AND foro_id = ?" . ($es_editor ? '' : ' AND usuario_id = ' . $usuario['id']))
            ->execute([entero_post('id'), $foro['id']]);
        hecho('Mensaje eliminado');
    default:
        error_formulario('Acción no válida.');
}

$stmt = $pdo->prepare("SELECT m.*, u.nombre, u.avatar_url, u.rol FROM foro_mensajes m JOIN usuarios u ON u.id = m.usuario_id WHERE m.foro_id = ? ORDER BY m.creado_en, m.id");
$stmt->execute([$foro['id']]);
$mensajes = $stmt->fetchAll();

panel_inicio($usuario, $foro['titulo'], 'foros');
encabezado(e($foro['titulo']), $foro['descripcion'] ?? '', ['Foros' => 'foros.php', $curso['titulo'] => "curso.php?id={$curso['id']}&tab=foros", $foro['titulo'] => null]);
?>
<section class="tarjeta">
    <div class="encabezado-tarjeta"><h2><?= count($mensajes) ?> mensajes</h2><span class="texto-tenue">Abierto por <?= e($foro['autor'] ?? 'el docente') ?> · <?= fecha_corta($foro['creado_en']) ?></span></div>
    <?php if (!$mensajes): ?><p class="vacio-mini">Nadie ha escrito todavía. Inicia la conversación.</p><?php endif; ?>
    <?php foreach ($mensajes as $m): ?>
        <article class="mensaje" id="m<?= $m['id'] ?>">
            <?= avatar_html($m) ?>
            <div class="mensaje-cuerpo">
                <div class="mensaje-autor"><strong><?= e($m['nombre']) ?></strong>
                    <?php if ((int) $m['usuario_id'] === (int) $curso['ponente_id']): ?><span class="badge">Docente</span><?php endif; ?>
                    <small><?= fecha_hora_texto($m['creado_en']) ?></small></div>
                <p class="mensaje-texto"><?= texto_html($m['contenido']) ?></p>
            </div>
            <?php if ($es_editor || (int) $m['usuario_id'] === $usuario['id']): ?>
                <form method="post" data-confirmar="¿Eliminar este mensaje?"><?= campo_csrf() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= $m['id'] ?>">
                    <button class="btn-icono peligro" aria-label="Eliminar mensaje"><?= icono('trash', 15) ?></button></form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
    <form method="post" class="form-respuesta">
        <?= campo_csrf() ?><input type="hidden" name="accion" value="responder">
        <textarea class="textarea" name="contenido" maxlength="5000" required placeholder="Escribe tu respuesta…" aria-label="Respuesta" style="height: 88px"></textarea>
        <button class="btn btn-primario"><span class="spinner"></span><?= icono('send', 16) ?> Publicar</button>
    </form>
</section>
<?php panel_fin(); ?>
