<?php
require_once __DIR__ . '/includes/cursos.php';

if ($usuario = usuario_actual()) {
    redirigir(ruta_panel($usuario));
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $recordar = !empty($_POST['remember']);

    if (!csrf_valido()) {
        $error = 'Tu sesión expiró. Recarga la página e inténtalo de nuevo.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Ingresa un correo válido y tu contraseña.';
    } elseif (demasiados_intentos($email)) {
        $error = 'Demasiados intentos fallidos. Espera ' . VENTANA_INTENTOS_MIN . ' minutos e inténtalo de nuevo.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND eliminado_en IS NULL");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($password, $usuario['password'])) {
            if ($usuario['estado'] !== 'activo') {
                $error = 'Tu cuenta está desactivada, contacta al administrador.';
            } else {
                limpiar_intentos($email);
                if (password_needs_rehash($usuario['password'], PASSWORD_BCRYPT)) {
                    $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?")
                        ->execute([password_hash($password, PASSWORD_BCRYPT), $usuario['id']]);
                }
                iniciar_sesion($usuario, $recordar);
                redirigir(ruta_panel($usuario));
            }
        } else {
            registrar_intento_fallido($email);
            $error = 'Correo o contraseña incorrectos.';
        }
    }
}

$alerta = $error ? ['tipo' => 'error', 'mensaje' => $error] : flash();
$registro_abierto = config('registro_abierto', '1') === '1';
$publicados = listar_cursos(['publicados' => true]);
$catalogo = config('catalogo_publico', '1') === '1' ? array_slice($publicados, 0, 8) : [];
$nombre = config('nombre_plataforma', 'Cursos Online');
[$palabra1, $palabra2] = array_pad(explode(' ', $nombre, 2), 2, '');
$cifras = [
    [count($publicados), count($publicados) === 1 ? 'curso publicado' : 'cursos publicados'],
    [array_sum(array_column($publicados, 'total_temas')), 'temas para estudiar'],
    [(int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'estudiante' AND estado = 'activo' AND eliminado_en IS NULL")->fetchColumn(), 'alumnos aprendiendo'],
];
// Cinta animada: títulos de cursos si el catálogo es público; si no, el lema
$cinta = $catalogo ? array_column($catalogo, 'titulo') : ['Aprender', 'Enseñar', 'Crecer', 'Bloques y temas', 'Foros y chat', 'Tareas y calificaciones'];

cabecera_html('Tu plataforma de aprendizaje', 'acceso');
?>
<header class="header-publico">
    <?= logo_html() ?>
    <nav class="nav-publica">
        <?php if ($catalogo): ?><a href="#catalogo">Catálogo</a><?php endif; ?>
        <a href="#contacto">Contacto</a>
    </nav>
    <?php if ($registro_abierto): ?><a href="<?= url('registro.php') ?>" class="btn btn-contorno">Crear cuenta</a><?php endif; ?>
</header>

<main class="entrada">
    <section class="entrada-intro">
        <span class="en-vivo"><i></i><?= $registro_abierto ? 'Inscripciones abiertas' : 'Plataforma educativa' ?></span>
        <h1 class="titular" aria-label="<?= e($nombre) ?>"><span><?= e($palabra1) ?></span><span class="contorno"><?= e($palabra2) ?></span></h1>
        <p class="entrada-texto">Crea, gestiona y cursa programas educativos organizados en bloques y temas. Avanza a tu ritmo, entrega tareas y conversa con tu grupo en un solo lugar.</p>
        <dl class="cifras">
            <?php foreach ($cifras as [$valor, $texto]): ?>
                <div><dt><?= e($texto) ?></dt><dd><?= number_format($valor) ?></dd></div>
            <?php endforeach; ?>
        </dl>
    </section>

    <section class="tarjeta tarjeta-login" id="login">
        <?= selector_acceso('login') ?>
        <h2>Bienvenido(a) de vuelta</h2>
        <p class="texto-secundario">Ingresa con tu correo para continuar donde te quedaste.</p>

        <?= alerta_html($alerta) ?>

        <form method="post" action="<?= url('login.php') ?>" class="form-acceso" novalidate>
            <?= campo_csrf() ?>
            <div class="campo">
                <label for="email">Correo electrónico</label>
                <div class="input-icono">
                    <?= icono('mail', 18) ?>
                    <input type="email" id="email" name="email" placeholder="tu@correo.com" autocomplete="email" required value="<?= e($email) ?>">
                </div>
                <small class="campo-error" data-error-de="email"></small>
            </div>
            <div class="campo">
                <label for="password">Contraseña</label>
                <div class="input-icono">
                    <?= icono('lock', 18) ?>
                    <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
                    <button type="button" class="ver-password" aria-label="Mostrar contraseña"><?= icono('eye', 18, 'ojo-abierto') . icono('eye-off', 18, 'ojo-cerrado') ?></button>
                </div>
                <small class="campo-error" data-error-de="password"></small>
            </div>
            <div class="fila-opciones">
                <label class="checkbox"><input type="checkbox" name="remember" value="1"<?= !empty($_POST['remember']) ? ' checked' : '' ?>><span>Recordarme</span></label>
                <a href="<?= url('recuperar.php') ?>" class="enlace">¿Olvidaste tu contraseña?</a>
            </div>
            <button type="submit" class="btn btn-primario btn-bloque">
                <span class="spinner"></span>
                <span>Entrar</span><?= icono('arrow-right', 18) ?>
            </button>
        </form>

        <?php if ($registro_abierto): ?>
            <div class="nuevo-aqui">
                <span><strong>¿Primera vez aquí?</strong><small>Tu cuenta de alumno es gratis.</small></span>
                <a href="<?= url('registro.php') ?>" class="btn btn-gradiente">Crear cuenta <?= icono('arrow-right', 16) ?></a>
            </div>
        <?php endif; ?>
    </section>
</main>

<div class="cinta" aria-hidden="true">
    <div class="cinta-pista">
        <?php for ($vuelta = 0; $vuelta < 2; $vuelta++): ?>
            <?php foreach ($cinta as $texto): ?><span><?= e($texto) ?></span><?php endforeach; ?>
        <?php endfor; ?>
    </div>
</div>

<?php if ($catalogo): ?>
    <section class="publico" id="catalogo">
        <div class="titulo-seccion"><h2>Catálogo</h2><span class="texto-tenue"><?= count($publicados) ?> cursos disponibles</span></div>
        <div class="grid-cursos">
            <?php foreach ($catalogo as $curso): ?>
                <?= tarjeta_curso($curso, url('index.php') . '#login', '<p class="tarjeta-curso-pie"><span>' . e($curso['categoria'] ?: 'General') . '</span><span>' . (int) $curso['total_temas'] . ' temas</span></p>') ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<footer class="footer-publico" id="contacto">
    <span>© <?= date('Y') ?> <?= e($nombre) ?> · TecnoAula</span>
    <span>Contacto: <a href="mailto:contacto@tecnoaula.com" class="enlace">contacto@tecnoaula.com</a></span>
</footer>

<?= script_html('assets/js/acceso.js') ?>
</body>
</html>
