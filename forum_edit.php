<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$topicId = (int)($_GET['id'] ?? 0);
$topic = getForumTopic($pdo, $topicId);

if (!$topic) redirect('forum.php');
if ((int)$topic['user_id'] !== (int)$user['id'] && $user['id'] != 1) redirect('forum.php');
if ((time() - strtotime($topic['created_at'])) > (FORUM_EDIT_TIME_MIN * 60) && $user['id'] != 1) {
    redirect("forum_topic.php?id=$topicId");
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } else {
        $res = editForumTopic($pdo, $user['id'], $topicId, $_POST['title'] ?? '', $_POST['body'] ?? '');
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            redirect("forum_topic.php?id=$topicId");
        }
    }
}

$pageTitle = 'Topic bewerken — Forum';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <a href="forum_topic.php?id=<?= $topicId ?>" class="back-link" style="display:inline-block;text-align:left;margin:0 0 10px 0;">← Terug naar topic</a>
    <h1>✏️ <span>Topic bewerken</span></h1>
    <p>Je kunt je topic aanpassen binnen <?= FORUM_EDIT_TIME_MIN ?> minuten na plaatsing.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<section class="section">
    <div class="casino-card">
        <form method="POST" class="casino-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

            <div class="bet-input">
                <label>Titel</label>
                <input type="text" name="title" required
                       minlength="<?= FORUM_TITLE_MIN ?>" maxlength="<?= FORUM_TITLE_MAX ?>"
                       value="<?= htmlspecialchars($topic['title']) ?>">
            </div>

            <div class="bet-input">
                <label>Bericht</label>
                <textarea name="body" rows="10" required
                          minlength="<?= FORUM_BODY_MIN ?>" maxlength="<?= FORUM_BODY_MAX ?>"
                          style="padding:12px 14px;background:var(--bg-0);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text);font-family:var(--font-body);font-size:16px;width:100%;resize:vertical;"><?= htmlspecialchars($topic['body']) ?></textarea>
            </div>

            <button type="submit" class="btn btn-gold btn-full">💾 Opslaan</button>
        </form>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>