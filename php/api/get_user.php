<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

$user = currentUser($pdo);

$unread = 0;
try { $unread = unreadNotifications($pdo, $user['id']); } catch (Exception $e) {}

echo json_encode([
    'money'         => (int)$user['money'],
    'bank_money'    => (int)($user['bank_money'] ?? 0),
    'energy'        => (int)$user['energy'],
    'max_energy'    => (int)$user['max_energy'],
    'health'        => (int)$user['health'],
    'max_health'    => (int)$user['max_health'],
    'xp'            => (int)$user['xp'],
    'rank_title'    => $user['rank_title'],
    'unread'        => (int)$unread,
    'in_hospital'   => isInHospital($user),
    'hospital_until'=> $user['hospital_until'],
    'in_prison'     => function_exists('isInPrison') ? isInPrison($user) : false,
    'time'          => time(),
]);