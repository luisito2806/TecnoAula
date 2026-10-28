<?php
$config = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
$host = $config['host'] ?? getenv('DB_HOST') ?: 'localhost';
$dbname = $config['dbname'] ?? getenv('DB_NAME') ?: 'TecnoAula';
$username = $config['username'] ?? getenv('DB_USER') ?: 'root';
$password = $config['password'] ?? getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
?>