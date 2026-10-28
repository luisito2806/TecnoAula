<?php
// Chat del curso.
//   GET  ?curso=ID&despues=ULTIMO_ID   -> mensajes nuevos (y los marca como leídos)
//   POST JSON { curso_id, contenido }  -> envía un mensaje (cabecera X-CSRF-Token)
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

$es_post = $_SERVER['REQUEST_METHOD'] === 'POST';
$datos = $es_post ? (json_decode(file_get_contents('php://input'), true) ?: []) : $_GET;
$curso = obtener_curso((int) ($datos['curso_id'] ?? $datos['curso'] ?? 0));
if (!$curso || !puede_ver_curso($usuario, $curso)) {
    responder_json(['error' => 'No tienes acceso a este curso.'], 403);
}

if ($es_post) {
    if (!csrf_valido()) {
        responder_json(['error' => 'Tu sesión expiró. Recarga la página.'], 419);
    }
    $contenido = trim((string) ($datos['contenido'] ?? ''));
    if ($contenido === '' || largo_texto($contenido) > 2000) {
        responder_json(['error' => 'El mensaje debe tener entre 1 y 2000 caracteres.'], 422);
    }
    $pdo->prepare("INSERT INTO chat_mensajes (curso_id, usuario_id, contenido) VALUES (?, ?, ?)")->execute([$curso['id'], $usuario['id'], $contenido]);
}

$stmt = $pdo->prepare("SELECT m.id, m.contenido, m.creado_en, m.usuario_id, u.nombre FROM chat_mensajes m JOIN usuarios u ON u.id = m.usuario_id
    WHERE m.curso_id = ? AND m.id > ? ORDER BY m.id LIMIT 100");
$stmt->execute([$curso['id'], (int) ($datos['despues'] ?? 0)]);
$mensajes = array_map(fn ($m) => [
    'id' => (int) $m['id'],
    'propio' => (int) $m['usuario_id'] === $usuario['id'],
    'autor' => $m['nombre'],
    'fecha' => fecha_hora_texto($m['creado_en']),
    'html' => texto_html($m['contenido']),
], $stmt->fetchAll());

if ($mensajes) {
    $pdo->prepare("INSERT INTO chat_lecturas (usuario_id, curso_id, ultimo_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE ultimo_id = GREATEST(ultimo_id, VALUES(ultimo_id))")
        ->execute([$usuario['id'], $curso['id'], end($mensajes)['id']]);
}
responder_json(['mensajes' => $mensajes]);
