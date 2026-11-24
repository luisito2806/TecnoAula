<?php
// TecnoAula — Crea el primer usuario Super Admin (o uno adicional).
// El registro público solo crea alumnos, así que en una instalación nueva hay
// que crear al administrador desde la terminal:
//
//   php database/crear_superadmin.php "Nombre Apellido" correo@ejemplo.com
//
// Pide la contraseña (mínimo 8 caracteres). Ejecutar después de las migraciones.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../conexion.php';

[, $nombre, $email] = array_pad($argv, 3, '');
$nombre = trim($nombre);
$email = strtolower(trim($email));

if ($nombre === '' || mb_strlen($nombre) > 100) {
    fwrite(STDERR, "Uso: php database/crear_superadmin.php \"Nombre\" correo@ejemplo.com\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
    fwrite(STDERR, "El correo no es válido.\n");
    exit(1);
}

$existe = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
$existe->execute([$email]);
if ($existe->fetch()) {
    fwrite(STDERR, "Ya existe un usuario con ese correo.\n");
    exit(1);
}

echo "Contraseña (mínimo 8 caracteres): ";
$password = rtrim((string) fgets(STDIN), "\r\n");
if (strlen($password) < 8) {
    fwrite(STDERR, "La contraseña debe tener al menos 8 caracteres.\n");
    exit(1);
}

try {
    $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol, estado) VALUES (?, ?, ?, 'superadmin', 'activo')")
        ->execute([$nombre, $email, password_hash($password, PASSWORD_BCRYPT)]);
} catch (PDOException $e) {
    fwrite(STDERR, "No se pudo crear el usuario. ¿Ya ejecutaste las migraciones? Detalle: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Super Admin creado: $nombre <$email>. Ya puedes iniciar sesión.\n";
