<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$categories = getForumCategories($pdo);

$presetCategory = (int)($_GET['category'] ?? 0);
if ($presetCategory === 0 && !empty($categories)) {
    $presetCategory = (int)$categories[0]['id'];
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } else {
        $title = $_POST['title'] ?? '';
        $body = $_POST['body'] ?? '';
        $categoryId = (int)($_POST['category'] ?? 0);

        $res = createForumTopic($pdo, $user['id'], $categoryId, $title, $body);
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            redirect("forum_topic.php?id={$res['topic_id']}");
        }
    }
}

$pageTitle = 'Nieuw topic — Forum';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <a href="forum.php" class="back-link" style="display:inline-block;text-align:left;margin:0 0 10px 0;">← Terug naar forum</a>
    <h1>➕ Nieuw <span>topic</span></h1>
    <p>Start een discussie, stel een vraag of deel iets met de community.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<section class="section">
    <div class="casino-card">
        <form method="POST" class="casino-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="create">

            <div class="bet-input">
                <label>Categorie</label>
                <select name="category" required>
                    <?php foreach ($categories as $cat):
                        if ((int)$cat['is_locked'] === 1) continue;
                    ?>
                        <option value="<?= (int)$cat['id'] ?>"
                                <?= $presetCategory === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="bet-input">
                <label>Titel (<?= FORUM_TITLE_MIN ?>–<?= FORUM_TITLE_MAX ?> tekens)</label>
                <input type="text" name="title" required
                       minlength="<?= FORUM_TITLE_MIN ?>" maxlength="<?= FORUM_TITLE_MAX ?>"
                       placeholder="Waar gaat je topic over?">
            </div>

            <div class="bet-input">
                <label>Bericht (<?= FORUM_BODY_MIN ?>–<?= FORUM_BODY_MAX ?> tekens)</label>
                <textarea name="body" rows="10" required
                          minlength="<?= FORUM_BODY_MIN ?>" maxlength="<?= FORUM_BODY_MAX ?>"
                          placeholder="Schrijf hier je volledige bericht..."
                          style="padding:12px 14px;background:var(--bg-0);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text);font-family:var(--font-body);font-size:16px;width:100%;resize:vertical;"></textarea>
            </div>

            <button type="submit" class="btn btn-gold btn-large btn-full">
                📝 Topic plaatsen
            </button>
        </form>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>