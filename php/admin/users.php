<?php
$adminPageTitle = 'Spelers';
require __DIR__ . '/includes/admin_header.php';

$query = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$users = searchUsers($pdo, $query, $perPage, $offset);
$total = countUsers($pdo, $query);
$totalPages = max(1, ceil($total / $perPage));

// Acties
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($userId === (int)$admin['id'] && in_array($action, ['ban', 'delete'])) {
        $error = 'Je kunt jezelf niet bannen of verwijderen.';
    } elseif ($action === 'ban') {
        $reason = trim($_POST['reason'] ?? 'Geen reden opgegeven');
        $hours = (int)($_POST['hours'] ?? 0);
        banUser($pdo, $userId, $reason, $hours > 0 ? $hours : null);
        adminLog($pdo, $admin['id'], 'ban_user', 'user', $userId, "Reden: {$reason}, Duur: " . ($hours > 0 ? "{$hours}u" : 'permanent'));
        $success = 'Speler gebandeerd.';
    } elseif ($action === 'unban') {
        unbanUser($pdo, $userId);
        adminLog($pdo, $admin['id'], 'unban_user', 'user', $userId);
        $success = 'Speler gedebandeerd.';
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
        adminLog($pdo, $admin['id'], 'delete_user', 'user', $userId);
        $success = 'Speler verwijderd.';
    }

    // Refresh
    $users = searchUsers($pdo, $query, $perPage, $offset);
}
?>

<h1 class="admin-title">👥 Spelersbeheer</h1>

<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="admin-alert admin-alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<form method="GET" class="admin-search-bar">
    <input type="text" name="q" value="<?= htmlspecialchars($query) ?>" placeholder="Zoek op naam, email of ID...">
    <button type="submit" class="btn btn-gold">🔍 Zoeken</button>
    <a href="users.php" class="btn btn-outline">Reset</a>
</form>

<p class="admin-info"><?= number_format($total) ?> spelers gevonden</p>

<div class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Speler</th>
                <th>Cash</th>
                <th>BTC</th>
                <th>Clicks</th>
                <th>Rank</th>
                <th>Status</th>
                <th>Laatste login</th>
                <th>Acties</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u):
                $rankData = getRankData((int)$u['xp'], getRanksArray());
            ?>
                <tr class="<?= $u['is_banned'] ? 'banned-row' : '' ?>">
                    <td><?= (int)$u['id'] ?></td>
                    <td>
                        <a href="user_edit.php?id=<?= (int)$u['id'] ?>"><strong><?= htmlspecialchars($u['username']) ?></strong></a>
                        <?php if ($u['is_admin']): ?><span class="tag-admin">ADMIN</span><?php endif; ?>
                    </td>
                    <td class="num">€<?= number_format((int)$u['money'], 0, ',', '.') ?></td>
                    <td class="num" style="color:#f7931a;"><?= formatBtc((float)$u['btc']) ?></td>
                    <td class="num" style="color:#4a9dff;"><?= number_format((int)$u['clicks']) ?></td>
                    <td><small><?= htmlspecialchars($rankData['name']) ?></small></td>
                    <td>
                        <?php if ($u['is_banned']): ?>
                            <span class="tag-banned">BANNED</span>
                        <?php else: ?>
                            <span class="tag-ok">✓</span>
                        <?php endif; ?>
                    </td>
                    <td><small><?= $u['last_login'] ? date('d M H:i', strtotime($u['last_login'])) : '—' ?></small></td>
                    <td class="actions">
                        <a href="user_edit.php?id=<?= (int)$u['id'] ?>" class="btn-icon" title="Bewerken">✏️</a>
                        <?php if ($u['is_banned']): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="action" value="unban">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <button type="submit" class="btn-icon" title="Deblokkeren">🔓</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Speler bannen?');">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="action" value="ban">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <input type="hidden" name="reason" value="Schending van de regels">
                                <button type="submit" class="btn-icon" title="Bannen">🚫</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <div class="admin-pagination">
        <?php if ($page > 1): ?>
            <a href="?q=<?= urlencode($query) ?>&page=<?= $page - 1 ?>" class="btn btn-outline">← Vorige</a>
        <?php endif; ?>
        <span>Pagina <?= $page ?> van <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
            <a href="?q=<?= urlencode($query) ?>&page=<?= $page + 1 ?>" class="btn btn-outline">Volgende →</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>