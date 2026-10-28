<?php
require_once __DIR__ . '/includes/layout.php';

if ($usuario = usuario_actual()) {
    redirigir(ruta_panel($usuario));
}
if (config('registro_abierto', '1') !== '1') {
    flash('error', 'El registro está cerrado. Pide a un administrador que cree tu cuenta.');
    redirigir('index.php');
}

$error = '';
$nombre = trim($_POST['nombre'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!csrf_valido()) {
        $error = 'Tu sesión expiró. Recarga la página e inténtalo de nuevo.';
    } elseif ($nombre === '' || $email === '' || $password === '' || $confirm_password === '') {
        $error = 'Por favor, completa todos los campos.';
    } elseif (largo_texto($nombre) > 100) {
        $error = 'El nombre no puede tener más de 100 caracteres.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $error = 'Ingresa un correo electrónico válido.';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $error = 'Este correo ya está registrado.';
        } else {
            // El registro público siempre crea Alumnos; los demás roles los asigna un administrador
            $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol, estado) VALUES (?, ?, ?, 'estudiante', 'activo')")
                ->execute([$nombre, $email, password_hash($password, PASSWORD_BCRYPT)]);
            $id = (int) $pdo->lastInsertId();
            registrar_actividad($id, 'registro', 'Se registró en la plataforma');
            iniciar_sesion(['id' => $id], false);
            redirigir(ROLES['estudiante']['panel']);
        }
    }
}

acceso_inicio('Crear cuenta');
?>
<section class="tarjeta tarjeta-login">
    <?= selector_acceso('registro') ?>
    <h2>Crea tu cuenta</h2>
    <p class="texto-secundario">Únete como alumno y empieza a aprender</p>

    <?= alerta_html($error ? ['tipo' => 'error', 'mensaje' => $error] : null) ?>

    <form method="post" class="form-acceso" novalidate>
        <?= campo_csrf() ?>
        <div class="campo">
            <label for="nombre">Nombre completo</label>
            <div class="input-icono">
                <?= icono('user', 18) ?>
                <input type="text" id="nombre" name="nombre" placeholder="Ej. Juan Pérez" autocomplete="name" maxlength="100" required value="<?= e($nombre) ?>">
            </div>
            <small class="campo-error" data-error-de="nombre"></small>
        </div>
        <div class="campo">
            <label for="email">Correo electrónico</label>
            <div class="input-icono">
                <?= icono('mail', 18) ?>
                <input type="email" id="email" name="email" placeholder="tu@correo.com" autocomplete="email" maxlength="100" required value="<?= e($email) ?>">
            </div>
            <small class="campo-error" data-error-de="email"></small>
        </div>
        <div class="campo">
            <label for="password">Contraseña</label>
            <div class="input-icono">
                <?= icono('lock', 18) ?>
                <input type="password" id="password" name="password" placeholder="Mínimo 8 caracteres" autocomplete="new-password" minlength="8" required>
                <button type="button" class="ver-password" aria-label="Mostrar contraseña"><?= icono('eye', 18, 'ojo-abierto') . icono('eye-off', 18, 'ojo-cerrado') ?></button>
            </div>
            <small class="campo-error" data-error-de="password"></small>
        </div>
        <div class="campo">
            <label for="confirm_password">Confirmar contraseña</label>
            <div class="input-icono">
                <?= icono('lock', 18) ?>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Repite tu contraseña" autocomplete="new-password" required data-igual-a="password">
                <button type="button" class="ver-password" aria-label="Mostrar contraseña"><?= icono('eye', 18, 'ojo-abierto') . icono('eye-off', 18, 'ojo-cerrado') ?></button>
            </div>
            <small class="campo-error" data-error-de="confirm_password"></small>
        </div>
        <button type="submit" class="btn btn-primario btn-bloque">
            <span class="spinner"></span><?= icono('user-plus', 18) ?><span>Registrarme</span>
        </button>
    </form>
    <p class="pie-tarjeta">¿Ya tienes una cuenta? <a href="<?= url('index.php') ?>" class="enlace">Inicia sesión</a></p>
</section>
<?php acceso_fin(); ?>
