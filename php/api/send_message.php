<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$user = currentUser($pdo);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$csrf = $input['csrf'] ?? '';
$conversationId = (int)($input['conversation_id'] ?? 0);
$body = $input['body'] ?? '';

if (!hash_equals(csrf_token(), $csrf)) {
    echo json_encode(['error' => 'Ongeldige sessie']);
    exit;
}

$conversation = getConversation($pdo, $conversationId);
if (!$conversation) {
    echo json_encode(['error' => 'Gesprek niet gevonden']);
    exit;
}

if ((int)$conversation['user_a'] !== (int)$user['id'] && (int)$conversation['user_b'] !== (int)$user['id']) {
    echo json_encode(['error' => 'Geen toegang tot dit gesprek']);
    exit;
}

$otherId = getOtherUser($conversation, (int)$user['id']);

$res = sendPrivateMessage($pdo, (int)$user['id'], $otherId, $body);

if (isset($res['error'])) {
    echo json_encode($res);
    exit;
}

echo json_encode([
    'success'   => true,
    'id'        => $res['message_id'],
    'body'      => $body,
    'created_at'=> date('Y-m-d H:i:s'),
]);