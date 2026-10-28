<?php
require_once __DIR__ . '/includes/layout.php';

$token = $_GET['token'] ?? '';
$error = '';

$stmt = $pdo->prepare("SELECT r.id, r.usuario_id FROM password_resets r
                       JOIN usuarios u ON u.id = r.usuario_id AND u.eliminado_en IS NULL
                       WHERE r.token_hash = ? AND r.usado_en IS NULL AND r.expira_en > NOW()");
$stmt->execute([hash('sha256', is_string($token) ? $token : '')]);
$reset = $stmt->fetch();

if ($reset && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!csrf_valido()) {
        $error = 'Tu sesión expiró. Recarga la página e inténtalo de nuevo.';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?")
            ->execute([password_hash($password, PASSWORD_BCRYPT), $reset['usuario_id']]);
        // Invalida este y cualquier otro enlace pendiente, y las sesiones "Recordarme"
        $pdo->prepare("UPDATE password_resets SET usado_en = NOW() WHERE usuario_id = ? AND usado_en IS NULL")
            ->execute([$reset['usuario_id']]);
        cerrar_sesiones_usuario($reset['usuario_id']);
        registrar_actividad($reset['usuario_id'], 'password_cambiado', 'Restableció su contraseña');
        flash('exito', 'Tu contraseña se actualizó. Ya puedes iniciar sesión.');
        redirigir('index.php');
    }
}

acceso_inicio('Nueva contraseña');
?>
<section class="tarjeta tarjeta-login">
    <?php if (!$reset): ?>
        <h2>Enlace no válido</h2>
        <?= alerta_html(['tipo' => 'error', 'mensaje' => 'El enlace expiró o ya fue utilizado. Solicita uno nuevo.']) ?>
        <a href="<?= url('recuperar.php') ?>" class="btn btn-primario btn-bloque">Solicitar nuevo enlace</a>
    <?php else: ?>
        <h2>Crea tu nueva contraseña</h2>
        <p class="texto-secundario">Debe tener al menos 8 caracteres.</p>

        <?= alerta_html($error ? ['tipo' => 'error', 'mensaje' => $error] : null) ?>

        <form method="post" class="form-acceso" novalidate>
            <?= campo_csrf() ?>
            <div class="campo">
                <label for="password">Nueva contraseña</label>
                <div class="input-icono">
                    <?= icono('lock', 18) ?>
                    <input type="password" id="password" name="password" autocomplete="new-password" minlength="8" required>
                    <button type="button" class="ver-password" aria-label="Mostrar contraseña"><?= icono('eye', 18, 'ojo-abierto') . icono('eye-off', 18, 'ojo-cerrado') ?></button>
                </div>
                <small class="campo-error" data-error-de="password"></small>
            </div>
            <div class="campo">
                <label for="confirm_password">Confirmar contraseña</label>
                <div class="input-icono">
                    <?= icono('lock', 18) ?>
                    <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required data-igual-a="password">
                </div>
                <small class="campo-error" data-error-de="confirm_password"></small>
            </div>
            <button type="submit" class="btn btn-primario btn-bloque">
                <span class="spinner"></span><span>Guardar contraseña</span>
            </button>
        </form>
    <?php endif; ?>
</section>
<?php acceso_fin(); ?>
