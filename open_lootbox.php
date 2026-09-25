<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$user = currentUser($pdo);

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$csrf  = $input['csrf'] ?? '';
$boxKey = $input['box'] ?? '';
$method = $input['method'] ?? 'eur';

if (!hash_equals(csrf_token(), $csrf)) {
    echo json_encode(['error' => 'Ongeldige sessie']);
    exit;
}

$result = openLootbox($pdo, $user['id'], $boxKey, $method);

if (isset($result['error'])) {
    echo json_encode($result);
    exit;
}

$user = currentUser($pdo);

$result['user'] = [
    'money'  => (int)$user['money'],
    'btc'    => (float)($user['btc'] ?? 0),
    'clicks' => (int)($user['clicks'] ?? 0),
    'pity'   => (int)($user['lootbox_pity'] ?? 0),
];
$result['rarity_info'] = LOOTBOX_RARITIES[$result['best_rarity']] ?? LOOTBOX_RARITIES['common'];
$result['pity_threshold'] = LOOTBOX_PITY_THRESHOLD;

echo json_encode($result);