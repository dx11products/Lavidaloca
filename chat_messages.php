<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$user = currentUser($pdo);
$channel = $_GET['channel'] ?? 'global';
$afterId = (int)($_GET['after'] ?? 0);

$familyId = null;
if ($channel === 'family') {
    $fam = getUserFamily($pdo, $user['id']);
    if (!$fam) {
        echo json_encode(['error' => 'Geen familie']);
        exit;
    }
    $familyId = (int)$fam['id'];
}

updateChatOnline($pdo, $user['id']);

$messages = getChatMessages($pdo, $channel, $familyId, $afterId);

// Als afterId = 0, geef de laatste 50 in omgekeerde volgorde
$isInitialLoad = ($afterId === 0);

$out = [];
foreach ($messages as $m) {
    $out[] = [
        'id'         => (int)$m['id'],
        'user_id'    => (int)$m['user_id'],
        'username'   => $m['username'],
        'rank_title' => $m['rank_title'],
        'xp'         => (int)$m['xp'],
        'body'       => $m['body'],
        'created_at' => $m['created_at'],
        'is_mine'    => (int)$m['user_id'] === (int)$user['id'],
    ];
}

echo json_encode([
    'success'      => true,
    'messages'     => $out,
    'initial_load' => $isInitialLoad,
]);