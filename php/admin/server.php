<?php
$adminPageTitle = 'Server tools';
require __DIR__ . '/includes/admin_header.php';

$output = null;
$error = null;
$success = null;

// ============================================================
// POST acties
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    }
    // Draai cron
    elseif ($action === 'run_cron') {
        $cronUrl = "http://localhost:8000/php/cron.php?key=vendetta_geheim_2026";
        $response = @file_get_contents($cronUrl);

        if ($response === false) {
            $error = 'Cron kon niet worden uitgevoerd. Check of de URL klopt en of de server draait.';
        } else {
            $output = $response;
            adminLog($pdo, $admin['id'], 'run_cron', null, null);
            $success = 'Cron uitgevoerd!';
        }
    }
    // Reset alle cooldowns
    elseif ($action === 'reset_cooldowns') {
        $stmt = $pdo->prepare("DELETE FROM crime_cooldowns");
        $stmt->execute();
        $affected = $stmt->rowCount();
        adminLog($pdo, $admin['id'], 'reset_all_cooldowns', null, null, "{$affected} rijen");
        $success = "Alle crime cooldowns gewist ({$affected} rijen).";
    }
    // Iedereen uit ziekenhuis
    elseif ($action === 'clear_hospital') {
        $stmt = $pdo->prepare("UPDATE users SET hospital_until = NULL, health = max_health WHERE hospital_until IS NOT NULL");
        $stmt->execute();
        $affected = $stmt->rowCount();
        adminLog($pdo, $admin['id'], 'clear_hospital', null, null, "{$affected} users");
        $success = "{$affected} spelers ontslagen uit ziekenhuis.";
    }
    // Iedereen uit gevangenis
    elseif ($action === 'clear_prison') {
        $stmt = $pdo->prepare("UPDATE users SET in_prison = 0, prison_until = NULL, prison_fine = 0, prison_reason = NULL WHERE in_prison = 1");
        $stmt->execute();
        $affected = $stmt->rowCount();
        adminLog($pdo, $admin['id'], 'clear_prison', null, null, "{$affected} users");
        $success = "{$affected} spelers vrijgelaten.";
    }
    // Wis alle notificaties
    elseif ($action === 'clear_notifications') {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE is_read = 1");
        $stmt->execute();
        $affected = $stmt->rowCount();
        adminLog($pdo, $admin['id'], 'clear_notifications', null, null, "{$affected} rijen");
        $success = "{$affected} gelezen notificaties verwijderd.";
    }
    // Wis cron logs
    elseif ($action === 'clear_cron_logs') {
        $stmt = $pdo->prepare("DELETE FROM cron_log");
        $stmt->execute();
        $affected = $stmt->rowCount();
        adminLog($pdo, $admin['id'], 'clear_cron_logs', null, null, "{$affected} rijen");
        $success = "{$affected} cron log regels gewist.";
    }
    // Optimaliseer tabellen
    elseif ($action === 'optimize_db') {
        try {
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $t) {
                $pdo->query("OPTIMIZE TABLE `{$t}`");
            }
            adminLog($pdo, $admin['id'], 'optimize_db', null, null, count($tables) . " tabellen");
            $success = count($tables) . " tabellen geoptimaliseerd.";
        } catch (Exception $e) {
            $error = 'Optimalisatie mislukt: ' . $e->getMessage();
        }
    }
}

// ============================================================
// Systeem info
// ============================================================
$phpVersion   = phpversion();
$uploadMax    = ini_get('upload_max_filesize');
$postMax      = ini_get('post_max_size');
$memoryLimit  = ini_get('memory_limit');
$maxExecTime  = ini_get('max_execution_time');
$serverTime   = date('d M Y H:i:s');
$serverTZ     = date_default_timezone_get();

// DB stats
try {
    $dbSize = $pdo->query("
        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
        FROM information_schema.TABLES
        WHERE table_schema = 'lavidaloca'
    ")->fetchColumn();
} catch (Exception $e) { $dbSize = '?'; }

$tableCount = $pdo->query("
    SELECT COUNT(*) FROM information_schema.TABLES WHERE table_schema = 'lavidaloca'
")->fetchColumn();

// Cron laatste run
$lastCron = null;
try {
    $stmt = $pdo->query("SELECT * FROM cron_log ORDER BY id DESC LIMIT 1");
    $lastCron = $stmt->fetch();
} catch (Exception $e) {}
?>

<h1 class="admin-title">🔧 Server tools</h1>

<?php if ($error): ?>
    <div class="admin-alert admin-alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="admin-alert admin-alert-success">✅ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Systeem info -->
<section class="admin-section">
    <h2>⚙️ Systeem informatie</h2>
    <ul class="info-list">
        <li><span>PHP versie</span><strong><?= htmlspecialchars($phpVersion) ?></strong></li>
        <li><span>Server tijd</span><strong><?= $serverTime ?></strong></li>
        <li><span>Tijdzone</span><strong><?= htmlspecialchars($serverTZ) ?></strong></li>
        <li><span>Upload max</span><strong><?= htmlspecialchars($uploadMax) ?></strong></li>
        <li><span>POST max</span><strong><?= htmlspecialchars($postMax) ?></strong></li>
        <li><span>Memory limit</span><strong><?= htmlspecialchars($memoryLimit) ?></strong></li>
        <li><span>Max execution time</span><strong><?= htmlspecialchars($maxExecTime) ?>s</strong></li>
        <li><span>Database grootte</span><strong><?= $dbSize ?> MB</strong></li>
        <li><span>Aantal tabellen</span><strong><?= number_format((int)$tableCount) ?></strong></li>
        <li><span>Laatste cron run</span><strong><?= $lastCron ? date('d M H:i:s', strtotime($lastCron['ran_at'])) : '—' ?></strong></li>
    </ul>
</section>

<!-- Cron -->
<section class="admin-section">
    <h2>🕐 Cron jobs</h2>
    <p class="muted">Forceer alle achtergrondtaken nu uit te voeren: energie regen, bank rente, markt fluctuaties, events, etc.</p>

    <form method="POST" style="margin-top:14px;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <button type="submit" name="action" value="run_cron" class="btn btn-gold btn-large btn-full">
            ▶️ Draai cron nu
        </button>
    </form>

    <?php if ($output): ?>
        <div style="margin-top:16px;">
            <h3 style="font-size:.85rem;color:#ff5c5c;text-transform:uppercase;letter-spacing:1.5px;margin-bottom:10px;">
                📤 Output
            </h3>
            <pre style="background:#0a0606;padding:16px;border-radius:6px;border:1px solid #241915;color:#58e08c;font-family:monospace;font-size:12px;overflow-x:auto;white-space:pre-wrap;max-height:400px;"><?= htmlspecialchars($output) ?></pre>
        </div>
    <?php endif; ?>
</section>

<!-- Database onderhoud -->
<section class="admin-section">
    <h2>🗄️ Database onderhoud</h2>
    <p class="muted">Snelle schoonmaak acties. Voorzicht met deze knoppen!</p>

    <div class="mass-action-grid" style="margin-top:14px;">
        <div class="mass-card">
            <h3>🎯 Crime cooldowns</h3>
            <p>Wis ALLE crime cooldowns van alle spelers.</p>
            <form method="POST" onsubmit="return confirm('Alle crime cooldowns wissen?');">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <button type="submit" name="action" value="reset_cooldowns" class="btn btn-outline btn-full">
                    Wis cooldowns
                </button>
            </form>
        </div>

        <div class="mass-card">
            <h3>🏥 Ziekenhuis</h3>
            <p>Ontsla ALLE spelers uit het ziekenhuis + volledige health.</p>
            <form method="POST" onsubmit="return confirm('Alle spelers ontslaan uit ziekenhuis?');">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <button type="submit" name="action" value="clear_hospital" class="btn btn-outline btn-full">
                    Ontsla iedereen
                </button>
            </form>
        </div>

        <div class="mass-card">
            <h3>🚔 Gevangenis</h3>
            <p>Laat ALLE gevangenen vrij zonder boete.</p>
            <form method="POST" onsubmit="return confirm('Alle gevangenen vrijlaten?');">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <button type="submit" name="action" value="clear_prison" class="btn btn-outline btn-full">
                    Laat iedereen vrij
                </button>
            </form>
        </div>

        <div class="mass-card">
            <h3>🔔 Notificaties</h3>
            <p>Verwijder alle GELEZEN notificaties.</p>
            <form method="POST" onsubmit="return confirm('Gelezen notificaties verwijderen?');">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <button type="submit" name="action" value="clear_notifications" class="btn btn-outline btn-full">
                    Wis gelezen
                </button>
            </form>
        </div>

        <div class="mass-card">
            <h3>📜 Cron logs</h3>
            <p>Verwijder alle cron log regels.</p>
            <form method="POST" onsubmit="return confirm('Alle cron logs wissen?');">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <button type="submit" name="action" value="clear_cron_logs" class="btn btn-outline btn-full">
                    Wis logs
                </button>
            </form>
        </div>

        <div class="mass-card">
            <h3>⚡ Optimaliseer DB</h3>
            <p>Optimaliseer alle tabellen voor betere performance.</p>
            <form method="POST" onsubmit="return confirm('Database optimaliseren? Dit kan even duren.');">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <button type="submit" name="action" value="optimize_db" class="btn btn-gold btn-full">
                    Optimaliseer
                </button>
            </form>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>