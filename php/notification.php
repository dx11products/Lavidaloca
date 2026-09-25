<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);

// Markeer alles als gelezen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (hash_equals(csrf_token(), $csrf)) {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")
            ->execute([$user['id']]);
    }
    redirect('notifications.php');
}

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 50");
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

$pageTitle = 'Notificaties — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><span>Notificaties</span></h1>
    <p>Alles wat er in jouw wereld gebeurt.</p>
</div>

<?php if (!empty($notifications)): ?>
    <form method="POST" style="margin-bottom:16px;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <button type="submit" class="btn btn-outline">Alles als gelezen markeren</button>
    </form>
<?php endif; ?>

<?php if (empty($notifications)): ?>
    <div class="alert alert-error" style="background:var(--bg-2);border-color:var(--border);color:var(--text-dim);">
        🔕 Nog geen notificaties.
    </div>
<?php else: ?>
    <div class="notif-list">
        <?php foreach ($notifications as $n): ?>
            <div class="notif-row <?= $n['is_read'] ? '' : 'unread' ?>">
                <span class="notif-icon"><?= htmlspecialchars($n['icon']) ?></span>
                <div class="notif-content">
                    <p><?= htmlspecialchars($n['message']) ?></p>
                    <time><?= date('d M Y H:i', strtotime($n['created_at'])) ?></time>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>