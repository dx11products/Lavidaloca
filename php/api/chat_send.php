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

$channel = $input['channel'] ?? 'global';
$body    = $input['body'] ?? '';

$familyId = null;
if ($channel === 'family') {
    $fam = getUserFamily($pdo, $user['id']);
    if (!$fam) {
        echo json_encode(['error' => 'Je zit niet in een familie']);
        exit;
    }
    $familyId = (int)$fam['id'];
}

$res = sendChatMessage($pdo, $user['id'], $channel, $familyId, $body);

if (isset($res['error'])) {
    echo json_encode($res);
    exit;
}

// Haal het nieuwe bericht op voor weergave
$stmt = $pdo->prepare("
    SELECT cm.*, u.username, u.xp, u.rank_title
    FROM chat_messages cm
    JOIN users u ON u.id = cm.user_id
    WHERE cm.id = ? LIMIT 1
");
$stmt->execute([$res['id']]);
$m = $stmt->fetch();

echo json_encode([
    'success' => true,
    'message' => [
        'id'         => (int)$m['id'],
        'user_id'    => (int)$m['user_id'],
        'username'   => $m['username'],
        'rank_title' => $m['rank_title'],
        'xp'         => (int)$m['xp'],
        'body'       => $m['body'],
        'created_at' => $m['created_at'],
        'is_mine'    => true,
    ],
]);