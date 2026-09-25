<?php
// Shop items — wordt automatisch geladen via db.php

$SHOP_CATEGORIES = [
    'weapon'  => ['name' => 'Wapens',      'icon' => '🔫'],
    'armor'   => ['name' => 'Bescherming', 'icon' => '🛡️'],
    'vehicle' => ['name' => 'Voertuigen',  'icon' => '🏎️'],
    'misc'    => ['name' => 'Overig',      'icon' => '📦'],
];

/**
 * Haal alle shop items op, gegroepeerd per categorie.
 */
function getShopItems(PDO $pdo): array {
    $stmt = $pdo->query("SELECT * FROM items ORDER BY category, price ASC");
    $grouped = [];
    foreach ($stmt->fetchAll() as $item) {
        $grouped[$item['category']][] = $item;
    }
    return $grouped;
}

/**
 * Haal items op die een user bezit.
 */
function getUserItems(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT ui.*, i.name, i.description, i.category, i.attack_bonus, i.defense_bonus
        FROM user_items ui
        JOIN items i ON i.`key` = ui.item_key
        WHERE ui.user_id = ?
        ORDER BY ui.equipped DESC, i.category, i.price DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Bereken totale attack & defense bonus van equipped items.
 */
function getEquippedBonuses(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(i.attack_bonus), 0)  AS attack,
            COALESCE(SUM(i.defense_bonus), 0) AS defense
        FROM user_items ui
        JOIN items i ON i.`key` = ui.item_key
        WHERE ui.user_id = ? AND ui.equipped = 1
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}