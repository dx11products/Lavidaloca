<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Admin Diagnose</h1>";

// 1. Bestandslocatie
echo "<h2>1. Bestand info</h2>";
echo "<p>Dit bestand staat in: <code>" . __DIR__ . "</code></p>";

// 2. Config laden
echo "<h2>2. Config test</h2>";
$configPath = __DIR__ . '/../config/db.php';
if (file_exists($configPath)) {
    echo "<p style='color:green'>✅ config/db.php gevonden</p>";
    require_once $configPath;
    echo "<p style='color:green'>✅ config geladen</p>";
} else {
    echo "<p style='color:red'>❌ config/db.php NIET gevonden op: {$configPath}</p>";
    exit;
}

// 3. Admin functie test
echo "<h2>3. Admin functie</h2>";
if (function_exists('requireAdmin')) {
    echo "<p style='color:green'>✅ requireAdmin bestaat</p>";
} else {
    echo "<p style='color:red'>❌ requireAdmin ONTBREEKT — config/admin.php niet geladen</p>";
}

// 4. Login check
echo "<h2>4. Login status</h2>";
if (isLoggedIn()) {
    echo "<p style='color:green'>✅ Ingelogd als user_id = " . $_SESSION['user_id'] . "</p>";
    $u = currentUser($pdo);
    if ($u) {
        echo "<p>Username: <strong>" . htmlspecialchars($u['username']) . "</strong></p>";
        echo "<p>is_admin: <strong>" . ($u['is_admin'] ?? 0) . "</strong></p>";
    }
} else {
    echo "<p style='color:red'>❌ NIET ingelogd — log in via game</p>";
}

// 5. Bestanden in admin map
echo "<h2>5. Admin bestanden</h2>";
$files = scandir(__DIR__);
echo "<ul>";
foreach ($files as $f) {
    if ($f === '.' || $f === '..') continue;
    echo "<li>" . htmlspecialchars($f) . (is_dir(__DIR__ . '/' . $f) ? " (map)" : "") . "</li>";
}
echo "</ul>";

// 6. Includes check
echo "<h2>6. Includes map</h2>";
$inc = __DIR__ . '/includes';
if (is_dir($inc)) {
    echo "<ul>";
    foreach (scandir($inc) as $f) {
        if ($f === '.' || $f === '..') continue;
        echo "<li>" . htmlspecialchars($f) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color:red'>❌ includes map ONTBREEKT</p>";
}