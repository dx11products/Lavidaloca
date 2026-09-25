<?php
// Diagnose bestand — verwijder dit later
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Familia La Vida Loca — Diagnose</h1>";
echo "<p><strong>PHP versie:</strong> " . phpversion() . "</p>";
echo "<p><strong>Huidige map:</strong> " . __DIR__ . "</p>";

echo "<h2>Bestanden in deze map:</h2><ul>";
foreach (scandir(__DIR__) as $f) {
    if ($f === '.' || $f === '..') continue;
    echo "<li>" . htmlspecialchars($f) . (is_dir(__DIR__ . '/' . $f) ? " (map)" : "") . "</li>";
}
echo "</ul>";

echo "<h2>Database test:</h2>";
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=lavidaloca;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color:green'>✅ Database verbinding OK</p>";

    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tables)) {
        echo "<p style='color:orange'>⚠️ Geen tabellen gevonden. Voer de SQL uit!</p>";
    } else {
        echo "<p>Tabellen: " . implode(", ", $tables) . "</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>❌ Database fout: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>Config bestand test:</h2>";
if (file_exists(__DIR__ . '/config/db.php')) {
    echo "<p style='color:green'>✅ config/db.php bestaat</p>";
} else {
    echo "<p style='color:red'>❌ config/db.php ONTBREEKT</p>";
}

echo "<h2>Assets test:</h2>";
echo file_exists(__DIR__ . '/assets/css/style.css')
    ? "<p style='color:green'>✅ assets/css/style.css bestaat</p>"
    : "<p style='color:red'>❌ assets/css/style.css ONTBREEKT</p>";