<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$user = currentUser($pdo);
$input = json_decode(file_get_contents('php://input'), true) ?: [];

if (!hash_equals(csrf_token(), $input['csrf'] ?? '')) {
    echo json_encode(['error' => 'Ongeldige sessie']);
    exit;
}

$messageId = (int)($input['id'] ?? 0);
$isAdmin = !empty($user['is_admin']);

if (deleteChatMessage($pdo, $messageId, (int)$user['id'], $isAdmin)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Je kunt dit bericht niet verwijderen.']);
}