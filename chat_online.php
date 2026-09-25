<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$user = currentUser($pdo);
updateChatOnline($pdo, $user['id']);

$online = getOnlineUsers($pdo);

$out = [];
foreach ($online as $u) {
    $out[] = [
        'id'       => (int)$u['id'],
        'username' => $u['username'],
        'xp'       => (int)$u['xp'],
    ];
}

echo json_encode([
    'success' => true,
    'online'  => $out,
    'count'   => count($out),
]);