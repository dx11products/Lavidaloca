<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);

$targetId = (int)($_GET['id'] ?? 0);
$csrf     = $_GET['csrf'] ?? '';

if (!hash_equals(csrf_token(), $csrf)) {
    redirect('leaderboard.php');
}
if ($targetId <= 0 || $targetId === (int)$user['id']) {
    redirect('leaderboard.php');
}

// Target ophalen
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$targetId]);
$target = $stmt->fetch();

if (!$target) redirect('leaderboard.php');

// Al geliked?
$stmt = $pdo->prepare("SELECT id FROM user_likes WHERE user_id = ? AND target_id = ? LIMIT 1");
$stmt->execute([$user['id'], $targetId]);

if ($stmt->fetch()) {
    Vendetta_redirect_back("Je hebt {$target['username']} al geliked.");
}

// Like toevoegen
$pdo->beginTransaction();
try {
    $pdo->prepare("INSERT INTO user_likes (user_id, target_id) VALUES (?, ?)")
        ->execute([$user['id'], $targetId]);

    $pdo->prepare("UPDATE users SET total_likes_received = total_likes_received + 1 WHERE id = ?")
        ->execute([$targetId]);

    // Beloning voor liker: 1 click + kans op kluiscode
    addClicks($pdo, $user['id'], CLICKS_PER_LIKE);

    logActivity($pdo, $user['id'], "❤️ {$target['username']} geliked");
    logActivity($pdo, $targetId, "❤️ Je kreeg een like van {$user['username']}!");

    notify($pdo, $targetId, "❤️ {$user['username']} heeft je profiel geliked!", '❤️');

    // Kans op kluiscode
    $vaultResult = null;
    if (random_int(1, 100) <= VAULT_DROP_LIKE) {
        $vaultResult = giveRandomVaultCode($pdo, $user['id']);
    }

    $pdo->commit();

    $msg = "+1 click · Je like is verzonden naar {$target['username']}";
    if ($vaultResult) {
        $msg .= " · 🔐 Nieuw kluiscijfer gevonden!";
    }

    Vendetta_redirect_back($msg);
} catch (Exception $e) {
    $pdo->rollBack();
    Vendetta_redirect_back("Like mislukt.");
}

function Vendetta_redirect_back(string $msg): void {
    $_SESSION['flash'] = $msg;
    $back = $_SERVER['HTTP_REFERER'] ?? 'leaderboard.php';
    header("Location: $back");
    exit;
}