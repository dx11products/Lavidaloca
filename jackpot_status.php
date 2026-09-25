<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$jackpots = getAllJackpots($pdo);

$out = [];
foreach ($jackpots as $j) {
    $out[] = [
        'key'    => $j['key'],
        'name'   => $j['name'],
        'icon'   => $j['icon'],
        'color'  => $j['color'],
        'amount' => (int)$j['current_amount'],
    ];
}

echo json_encode(['jackpots' => $out, 'time' => time()]);