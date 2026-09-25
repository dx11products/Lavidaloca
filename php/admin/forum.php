<?php
$adminPageTitle = 'Forum beheer';
require __DIR__ . '/includes/admin_header.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } else {
        $topicId = (int)($_POST['topic_id'] ?? 0);
        $replyId = (int)($_POST['reply_id'] ?? 0);

        if ($action === 'delete_topic') {
            adminDeleteTopic($pdo, $topicId);
            adminLog($pdo, $admin['id'], 'forum_delete_topic', 'topic', $topicId);
            $success = 'Topic verwijderd.';
        } elseif ($action === 'delete_reply') {
            adminDeleteReply($pdo, $replyId);
            adminLog($pdo, $admin['id'], 'forum_delete_reply', 'reply', $replyId);
            $success = 'Reactie verwijderd.';
        } elseif ($action === 'pin_topic') {
            adminPinTopic($pdo, $topicId, true);
            $success = 'Topic vastgezet.';
        } elseif ($action === 'unpin_topic') {
            adminPinTopic($pdo, $topicId, false);
            $success = 'Topic losgemaakt.';
        } elseif ($action === 'lock_topic') {
            adminLockTopic($pdo, $topicId, true);
            $success = 'Topic gesloten.';
        } elseif ($action === 'unlock_topic') {
            adminLockTopic($pdo, $topicId, false);
            $success = 'Topic heropend.';
        }
    }
}

$topics = $pdo->query("
    SELECT t.*, u.username, c.name AS category_name
    FROM forum_topics t
    JOIN users u ON u.id = t.user_id
    JOIN forum_categories c ON c.id = t.category_id
    ORDER BY t.id DESC
    LIMIT 50
")->fetchAll();

$recentReplies = $pdo->query("
    SELECT r.*, u.username, t.title AS topic_title
    FROM forum_replies r
    JOIN users u ON u.id = r.user_id
    JOIN forum_topics t ON t.id = r.topic_id
    WHERE r.is_deleted = 0
    ORDER BY r.id DESC
    LIMIT 20
")->fetchAll();
?>

<h1 class="admin-title">💬 Forum moderatie</h1>

<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="admin-alert admin-alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<section class="admin-section">
    <h2>📋 Recente topics</h2>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Titel</th>
                    <th>Auteur</th>
                    <th>Categorie</th>
                    <th>Replies</th>
                    <th>Status</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topics as $t): ?>
                    <tr>
                        <td><?= (int)$t['id'] ?></td>
                        <td>
                            <a href="../forum_topic.php?id=<?= (int)$t['id'] ?>" target="_blank">
                                <?= htmlspecialchars(mb_substr($t['title'], 0, 50)) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($t['username']) ?></td>
                        <td><small><?= htmlspecialchars($t['category_name']) ?></small></td>
                        <td class="num"><?= (int)$t['reply_count'] ?></td>
                        <td>
                            <?php if ($t['is_pinned']): ?><span class="tag-ok">📌</span><?php endif; ?>
                            <?php if ($t['is_locked']): ?><span class="tag-banned">🔒</span><?php endif; ?>
                        </td>
                        <td class="actions">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="topic_id" value="<?= (int)$t['id'] ?>">
                                <?php if ($t['is_pinned']): ?>
                                    <button type="submit" name="action" value="unpin_topic" class="btn-icon" title="Losmaken">📍</button>
                                <?php else: ?>
                                    <button type="submit" name="action" value="pin_topic" class="btn-icon" title="Vastzetten">📌</button>
                                <?php endif; ?>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="topic_id" value="<?= (int)$t['id'] ?>">
                                <?php if ($t['is_locked']): ?>
                                    <button type="submit" name="action" value="unlock_topic" class="btn-icon" title="Heropen">🔓</button>
                                <?php else: ?>
                                    <button type="submit" name="action" value="lock_topic" class="btn-icon" title="Sluiten">🔒</button>
                                <?php endif; ?>
                            </form>
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Topic + alle replies verwijderen?');">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="topic_id" value="<?= (int)$t['id'] ?>">
                                <button type="submit" name="action" value="delete_topic" class="btn-icon" title="Verwijderen">🗑️</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-section">
    <h2>💬 Recente reacties</h2>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Bericht</th>
                    <th>Auteur</th>
                    <th>In topic</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentReplies as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><small><?= htmlspecialchars(mb_substr($r['body'], 0, 60)) ?>...</small></td>
                        <td><?= htmlspecialchars($r['username']) ?></td>
                        <td>
                            <a href="../forum_topic.php?id=<?= (int)$r['topic_id'] ?>" target="_blank">
                                <?= htmlspecialchars(mb_substr($r['topic_title'], 0, 40)) ?>
                            </a>
                        </td>
                        <td class="actions">
                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Reactie verwijderen?');">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="reply_id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" name="action" value="delete_reply" class="btn-icon">🗑️</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>