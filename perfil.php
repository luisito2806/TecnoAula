<?php
require_once __DIR__ . '/includes/cursos.php';

$usuario = requiere_login();

switch (accion_post()) {
    case null:
        break;
    case 'datos':
        $nombre = texto_post('nombre', 100);
        $avatar = trim($_POST['avatar_url'] ?? '');
        if ($nombre === '') {
            error_formulario('Escribe tu nombre.');
        }
        if ($avatar !== '' && (!url_valida($avatar) || strlen($avatar) > 255)) {
            error_formulario('La URL de la foto no es válida (http/https).');
        }
        $pdo->prepare("UPDATE usuarios SET nombre = ?, cargo = ?, avatar_url = ? WHERE id = ?")
            ->execute([$nombre, texto_post('cargo', 100) ?: null, $avatar ?: null, $usuario['id']]);
        registrar_actividad($usuario['id'], 'perfil', 'Actualizó su perfil');
        hecho('Perfil actualizado');
    case 'password':
        $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario['id']]);
        $nueva = $_POST['nueva'] ?? '';
        if (!password_verify($_POST['actual'] ?? '', $stmt->fetchColumn())) {
            error_formulario('Tu contraseña actual no es correcta.');
        }
        if (strlen($nueva) < 8) {
            error_formulario('La nueva contraseña debe tener al menos 8 caracteres.');
        }
        if ($nueva !== ($_POST['confirmar'] ?? '')) {
            error_formulario('Las contraseñas no coinciden.');
        }
        $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?")->execute([password_hash($nueva, PASSWORD_BCRYPT), $usuario['id']]);
        // Cierra las otras sesiones "Recordarme"; esta sigue abierta
        cerrar_sesiones_usuario($usuario['id']);
        registrar_actividad($usuario['id'], 'password_cambiado', 'Cambió su contraseña');
        hecho('Contraseña actualizada');
    default:
        error_formulario('Acción no válida.');
}

$stmt = $pdo->prepare("SELECT detalle, accion, creado_en FROM actividad_log WHERE usuario_id = ? ORDER BY creado_en DESC, id DESC LIMIT 8");
$stmt->execute([$usuario['id']]);
$actividad = $stmt->fetchAll();

panel_inicio($usuario, 'Mi perfil', 'perfil');
encabezado('Mi perfil', 'Tu información personal y seguridad de la cuenta.', ['Mi perfil' => null]);
?>
<div class="columnas">
    <div style="display: grid; gap: 16px">
        <section class="tarjeta">
            <div class="encabezado-tarjeta"><h2>Información personal</h2></div>
            <form method="post" class="cuerpo-tarjeta formulario-rejilla">
                <?= campo_csrf() ?><input type="hidden" name="accion" value="datos">
                <div class="campo"><label for="p-nombre">Nombre completo</label><input class="input" id="p-nombre" name="nombre" maxlength="100" required value="<?= e($usuario['nombre']) ?>"></div>
                <div class="campo"><label for="p-email">Correo electrónico</label><input class="input" id="p-email" value="<?= e($usuario['email']) ?>" disabled><small class="ayuda">Para cambiarlo, pídeselo a un administrador.</small></div>
                <div class="campo"><label for="p-cargo">Cargo / carrera</label><input class="input" id="p-cargo" name="cargo" maxlength="100" value="<?= e($usuario['cargo']) ?>" placeholder="Ej. Ingeniería en Sistemas"></div>
                <div class="campo"><label for="p-avatar">Foto (URL)</label><input class="input" type="url" id="p-avatar" name="avatar_url" maxlength="255" value="<?= e($usuario['avatar_url']) ?>" placeholder="https://…"></div>
                <div class="completo"><button class="btn btn-primario"><span class="spinner"></span>Guardar cambios</button></div>
            </form>
        </section>
        <section class="tarjeta">
            <div class="encabezado-tarjeta"><h2>Cambiar contraseña</h2></div>
            <form method="post" class="cuerpo-tarjeta formulario-rejilla">
                <?= campo_csrf() ?><input type="hidden" name="accion" value="password">
                <div class="campo completo"><label for="p-actual">Contraseña actual</label><input class="input" type="password" id="p-actual" name="actual" required autocomplete="current-password"></div>
                <div class="campo"><label for="p-nueva">Nueva contraseña</label><input class="input" type="password" id="p-nueva" name="nueva" minlength="8" required autocomplete="new-password"></div>
                <div class="campo"><label for="p-conf">Confirmar</label><input class="input" type="password" id="p-conf" name="confirmar" minlength="8" required autocomplete="new-password"></div>
                <div class="completo"><button class="btn btn-contorno"><span class="spinner"></span><?= icono('key', 16) ?> Actualizar contraseña</button></div>
            </form>
        </section>
    </div>
    <aside>
        <section class="tarjeta cuerpo-tarjeta" style="text-align: center">
            <?= avatar_html($usuario, 'grande') ?>
            <h2 style="margin-top: 12px; font-size: 18px"><?= e($usuario['nombre']) ?></h2>
            <p class="texto-secundario"><?= e($usuario['cargo'] ?: $usuario['email']) ?></p>
            <p style="margin-top: 12px; display: flex; gap: 6px; justify-content: center; flex-wrap: wrap">
                <?= badge_rol($usuario['rol']) ?><?php foreach ($usuario['roles'] as $r): ?><?= badge_rol($r) ?><?php endforeach; ?>
            </p>
            <dl class="kv" style="margin-top: 20px; text-align: left">
                <div><dt>Miembro desde</dt><dd><?= fecha_corta($usuario['creado_en']) ?></dd></div>
                <div><dt>Último acceso</dt><dd><?= fecha_hora_texto($usuario['ultimo_acceso']) ?></dd></div>
            </dl>
        </section>
        <section class="tarjeta">
            <div class="encabezado-tarjeta"><h3>Mi actividad</h3></div>
            <?php if (!$actividad): ?><p class="vacio-mini">Sin actividad registrada.</p><?php endif; ?>
            <div class="cuerpo-tarjeta"><ol class="linea-tiempo">
                <?php foreach ($actividad as $a): ?><li><strong><?= e($a['detalle'] ?: $a['accion']) ?></strong><small><?= fecha_hora_texto($a['creado_en']) ?></small></li><?php endforeach; ?>
            </ol></div>
        </section>
    </aside>
</div>
<?php panel_fin(); ?>
