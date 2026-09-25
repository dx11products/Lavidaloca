<?php
session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'lavidaloca');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Database verbinding mislukt: " . $e->getMessage());
}

// ============================================================
// CONFIG REQUIRES — volgorde is belangrijk!
// heists_v2 vóór achievements (ROLE_LEVEL_XP)
// jackpot vóór casino (contributeToJackpots)
// ============================================================
require_once __DIR__ . '/game.php';
require_once __DIR__ . '/shop.php';
require_once __DIR__ . '/attack.php';
require_once __DIR__ . '/bank.php';
require_once __DIR__ . '/family.php';
require_once __DIR__ . '/family_upgrades.php';
require_once __DIR__ . '/house_upgrades.php';
require_once __DIR__ . '/travel.php';
require_once __DIR__ . '/labs.php';
require_once __DIR__ . '/war.php';
require_once __DIR__ . '/police.php';
require_once __DIR__ . '/cars.php';
require_once __DIR__ . '/cars_v2.php';
require_once __DIR__ . '/crimes_v2.php';
require_once __DIR__ . '/heists.php';
require_once __DIR__ . '/heists_v2.php';
require_once __DIR__ . '/achievements.php';
require_once __DIR__ . '/clicks.php';
require_once __DIR__ . '/vault.php';
require_once __DIR__ . '/btc.php';
require_once __DIR__ . '/messages.php';
require_once __DIR__ . '/forum.php';
require_once __DIR__ . '/diamonds.php';
require_once __DIR__ . '/market.php';
require_once __DIR__ . '/jackpot.php';
require_once __DIR__ . '/casino.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/lootboxes.php';
require_once __DIR__ . '/chat.php';
require_once __DIR__ . '/bullets_limit.php';

// ============================================================
// HULPFUNCTIES
// ============================================================

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/**
 * Haal huidige user op + pas energie regeneratie, bank rente en BTC productie toe.
 */
function currentUser(PDO $pdo): ?array {
    if (!isLoggedIn()) return null;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) return null;

    // --------------------------------------------------------
    // 1. ENERGIE REGENERATIE
    // --------------------------------------------------------
    $now = time();
    $last = strtotime($user['energy_updated']);
    $elapsed = $now - $last;

    if ($elapsed > 0 && $user['energy'] < $user['max_energy']) {
        $regain = intdiv($elapsed, ENERGY_REGEN_SECONDS);
        if ($regain > 0) {
            $newEnergy = min($user['max_energy'], $user['energy'] + $regain);
            $newTimestamp = date('Y-m-d H:i:s', $last + ($regain * ENERGY_REGEN_SECONDS));
            $pdo->prepare("UPDATE users SET energy = ?, energy_updated = ? WHERE id = ?")
                ->execute([$newEnergy, $newTimestamp, $user['id']]);
            $user['energy'] = $newEnergy;
            $user['energy_updated'] = $newTimestamp;
        }
    }

    // --------------------------------------------------------
    // 2. BANK RENTE
    // --------------------------------------------------------
    if (function_exists('applyInterest')) {
        try {
            $user = applyInterest($pdo, $user);
        } catch (Exception $e) {}
    }

    // --------------------------------------------------------
    // 3. BTC PRODUCTIE VAN MINERS
    // --------------------------------------------------------
    if (function_exists('updateUserBtcProduction')) {
        try {
            $gained = updateUserBtcProduction($pdo, $user['id']);
            if ($gained > 0) {
                $stmt = $pdo->prepare("SELECT btc FROM users WHERE id = ?");
                $stmt->execute([$user['id']]);
                $user['btc'] = (float)$stmt->fetchColumn();
            }
        } catch (Exception $e) {}
    }

    // --------------------------------------------------------
    // FALLBACK DEFAULTS — voorkom null errors
    // --------------------------------------------------------
    if (!isset($user['clicks']))              $user['clicks'] = 0;
    if (!isset($user['bank_money']))          $user['bank_money'] = 0;
    if (!isset($user['btc']))                 $user['btc'] = 0;
    if (!isset($user['diamonds']))            $user['diamonds'] = 0;
    if (!isset($user['corruption']))          $user['corruption'] = 0;
    if (!isset($user['in_prison']))           $user['in_prison'] = 0;
    if (!isset($user['achievements_count']))  $user['achievements_count'] = 0;
    if (!isset($user['attacks_won']))         $user['attacks_won'] = 0;
    if (!isset($user['attacks_lost']))        $user['attacks_lost'] = 0;
    if (!isset($user['times_hospitalized']))  $user['times_hospitalized'] = 0;
    if (!isset($user['crimes_done']))         $user['crimes_done'] = 0;
    if (!isset($user['is_admin']))            $user['is_admin'] = 0;
    if (!isset($user['is_banned']))           $user['is_banned'] = 0;
    if (!isset($user['lootbox_pity']))        $user['lootbox_pity'] = 0;
    if (!isset($user['total_lootboxes_opened'])) $user['total_lootboxes_opened'] = 0;

    return $user;
}

/**
 * Voeg een activiteit toe aan het logboek.
 */
function logActivity(PDO $pdo, int $userId, string $message): void {
    try {
        $pdo->prepare("INSERT INTO activity_log (user_id, message) VALUES (?, ?)")
            ->execute([$userId, $message]);
    } catch (Exception $e) {}
}