<?php
// Guarda el nuevo orden de bloques o temas tras arrastrar y soltar.
// POST JSON { curso_id, tipo: 'bloques'|'temas', bloque_id, ids: [...] } + cabecera X-CSRF-Token
require_once __DIR__ . '/../includes/cursos.php';

header('Content-Type: application/json; charset=utf-8');

function responder_json(array $datos, int $codigo = 200): never
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

$usuario = usuario_actual();
if (!$usuario) {
    responder_json(['error' => 'Tu sesión expiró.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valido()) {
    responder_json(['error' => 'Solicitud no válida.'], 419);
}
$datos = json_decode(file_get_contents('php://input'), true) ?: [];
$curso = obtener_curso((int) ($datos['curso_id'] ?? 0));
if (!$curso || !puede_editar_curso($usuario, $curso)) {
    responder_json(['error' => 'No puedes editar este curso.'], 403);
}
$ids = array_values(array_unique(array_map('intval', (array) ($datos['ids'] ?? []))));

if (($datos['tipo'] ?? '') === 'bloques') {
    $stmt = $pdo->prepare("SELECT id FROM bloques WHERE curso_id = ?");
    $stmt->execute([$curso['id']]);
    $tabla = 'bloques';
} else {
    $stmt = $pdo->prepare("SELECT t.id FROM temas t JOIN bloques b ON b.id = t.bloque_id WHERE t.bloque_id = ? AND b.curso_id = ?");
    $stmt->execute([(int) ($datos['bloque_id'] ?? 0), $curso['id']]);
    $tabla = 'temas';
}
// Deben venir exactamente los mismos elementos que existen, solo en otro orden
$existentes = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
if (count($ids) !== count($existentes) || array_diff($ids, $existentes)) {
    responder_json(['error' => 'La lista no coincide con la estructura actual. Recarga la página.'], 409);
}

$pdo->beginTransaction();
$actualizar = $pdo->prepare("UPDATE $tabla SET orden = ? WHERE id = ?");
foreach ($ids as $posicion => $id) {
    $actualizar->execute([$posicion + 1, $id]);
}
$pdo->commit();
responder_json(['ok' => true]);
