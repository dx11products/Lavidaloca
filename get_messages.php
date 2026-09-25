<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$user = currentUser($pdo);

$conversationId = (int)($_GET['conversation'] ?? 0);
$afterId = (int)($_GET['after'] ?? 0);

$conversation = getConversation($pdo, $conversationId);
if (!$conversation) {
    echo json_encode(['error' => 'Gesprek niet gevonden']);
    exit;
}

if ((int)$conversation['user_a'] !== (int)$user['id'] && (int)$conversation['user_b'] !== (int)$user['id']) {
    echo json_encode(['error' => 'Geen toegang']);
    exit;
}

$messages = getNewMessages($pdo, $conversationId, (int)$user['id'], $afterId);

// Markeer als gelezen
markConversationRead($pdo, $conversationId, (int)$user['id']);

echo json_encode([
    'success' => true,
    'messages' => array_map(function($m) {
        return [
            'id'         => (int)$m['id'],
            'sender_id'  => (int)$m['sender_id'],
            'body'       => $m['body'],
            'created_at' => $m['created_at'],
        ];
    }, $messages),
]);