<?php
$adminPageTitle = 'Logs';
require __DIR__ . '/includes/admin_header.php';

$filterAdmin = (int)($_GET['admin'] ?? 0);
$filterAction = trim($_GET['action'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$sql = "
    SELECT al.*, u.username AS admin_name, t.username AS target_name
    FROM admin_log al
    LEFT JOIN users u ON u.id = al.admin_id
    LEFT JOIN users t ON t.id = al.target_id AND al.target_type = 'user'
    WHERE 1=1
";
$params = [];

if ($filterAdmin) {
    $sql .= " AND al.admin_id = ?";
    $params[] = $filterAdmin;
}
if ($filterAction !== '') {
    $sql .= " AND al.action LIKE ?";
    $params[] = '%' . $filterAction . '%';
}

$sql .= " ORDER BY al.id DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Admins voor filter
$admins = $pdo->query("SELECT id, username FROM users WHERE is_admin = 1")->fetchAll();
?>

<h1 class="admin-title">📜 Admin Logs</h1>

<form method="GET" class="admin-search-bar">
    <select name="admin" style="padding:10px;background:var(--bg-0);border:1px solid var(--border);border-radius:4px;color:var(--text);">
        <option value="0">Alle admins</option>
        <?php foreach ($admins as $a): ?>
            <option value="<?= (int)$a['id'] ?>" <?= $filterAdmin === (int)$a['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($a['username']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <input type="text" name="action" value="<?= htmlspecialchars($filterAction) ?>" placeholder="Filter op actie...">
    <button type="submit" class="btn btn-gold">Filter</button>
    <a href="logs.php" class="btn btn-outline">Reset</a>
</form>

<div class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Admin</th>
                <th>Actie</th>
                <th>Target</th>
                <th>Details</th>
                <th>IP</th>
                <th>Tijd</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= (int)$log['id'] ?></td>
                    <td><strong><?= htmlspecialchars($log['admin_name'] ?? '?') ?></strong></td>
                    <td><code><?= htmlspecialchars($log['action']) ?></code></td>
                    <td>
                        <?php if ($log['target_type'] === 'user' && $log['target_name']): ?>
                            <a href="user_edit.php?id=<?= (int)$log['target_id'] ?>">
                                <?= htmlspecialchars($log['target_name']) ?>
                            </a>
                        <?php elseif ($log['target_type']): ?>
                            <?= htmlspecialchars($log['target_type']) ?> #<?= (int)$log['target_id'] ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><small><?= htmlspecialchars($log['details'] ?? '') ?></small></td>
                    <td><small><?= htmlspecialchars($log['ip'] ?? '') ?></small></td>
                    <td><small><?= date('d M H:i:s', strtotime($log['created_at'])) ?></small></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>