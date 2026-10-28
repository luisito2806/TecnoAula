<?php
require_once __DIR__ . '/includes/layout.php';

$alerta = null;
$email = strtolower(trim($_POST['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        $alerta = ['tipo' => 'error', 'mensaje' => 'Tu sesión expiró. Recarga la página e inténtalo de nuevo.'];
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $alerta = ['tipo' => 'error', 'mensaje' => 'Ingresa un correo electrónico válido.'];
    } elseif (demasiados_intentos($email)) {
        $alerta = ['tipo' => 'error', 'mensaje' => 'Demasiadas solicitudes. Espera unos minutos e inténtalo de nuevo.'];
    } else {
        // Cuenta como intento para limitar el envío masivo de correos
        registrar_intento_fallido($email);
        $stmt = $pdo->prepare("SELECT id, nombre, email FROM usuarios WHERE email = ? AND estado = 'activo' AND eliminado_en IS NULL");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        // Mismo mensaje exista o no la cuenta, para no revelar qué correos están registrados
        $alerta = ['tipo' => 'exito', 'mensaje' => 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña. El enlace expira en 1 hora.'];
        if ($usuario) {
            $enlace = crear_enlace_restablecer($usuario);
            registrar_actividad($usuario['id'], 'recuperar_password', 'Solicitó restablecer su contraseña');
            if (MODO_DESARROLLO) {
                $alerta['extra'] = '<br><small>Modo desarrollo: <a class="enlace" href="' . e($enlace) . '">abrir enlace</a></small>';
            }
        }
    }
}

acceso_inicio('Recuperar contraseña');
?>
<section class="tarjeta tarjeta-login">
    <h2>¿Olvidaste tu contraseña?</h2>
    <p class="texto-secundario">Escribe tu correo y te enviaremos un enlace para crear una nueva.</p>

    <?= alerta_html($alerta) ?>

    <form method="post" class="form-acceso" novalidate>
        <?= campo_csrf() ?>
        <div class="campo">
            <label for="email">Correo electrónico</label>
            <div class="input-icono">
                <?= icono('mail', 18) ?>
                <input type="email" id="email" name="email" placeholder="tu@correo.com" autocomplete="email" required value="<?= e($email) ?>">
            </div>
            <small class="campo-error" data-error-de="email"></small>
        </div>
        <button type="submit" class="btn btn-primario btn-bloque">
            <span class="spinner"></span><span>Enviar enlace</span><?= icono('arrow-right', 18) ?>
        </button>
    </form>
    <p class="pie-tarjeta"><a href="<?= url('index.php') ?>" class="enlace"><?= icono('arrow-left', 16) ?> Volver a iniciar sesión</a></p>
</section>
<?php acceso_fin(); ?>
