<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$topicId = (int)($_GET['id'] ?? 0);
$topic = getForumTopic($pdo, $topicId);

if (!$topic) redirect('forum.php');

$category = getForumCategory($pdo, (int)$topic['category_id']);

// Views tellen
incrementTopicViews($pdo, $topicId);

$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * FORUM_REPLIES_PER_PAGE;

$replies = getTopicReplies($pdo, $topicId, FORUM_REPLIES_PER_PAGE, $offset);
$totalReplies = countTopicReplies($pdo, $topicId);
$totalPages = max(1, ceil($totalReplies / FORUM_REPLIES_PER_PAGE));

$error = null;

// Reactie plaatsen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($action === 'reply') {
        $body = $_POST['body'] ?? '';
        $res = createForumReply($pdo, $user['id'], $topicId, $body);
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            redirect("forum_topic.php?id=$topicId&page=" . ceil(($totalReplies + 1) / FORUM_REPLIES_PER_PAGE));
        }
    } elseif ($action === 'delete_reply') {
        $replyId = (int)($_POST['reply_id'] ?? 0);
        $res = deleteForumReply($pdo, $user['id'], $replyId);
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            redirect("forum_topic.php?id=$topicId");
        }
    } elseif ($action === 'delete_topic') {
        $res = deleteForumTopic($pdo, $user['id'], $topicId);
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            redirect("forum_board.php?id=" . (int)$topic['category_id']);
        }
    }
}

$authorColor = getUserRankColor($pdo, (int)$topic['xp']);

$pageTitle = htmlspecialchars($topic['title']) . ' — Forum';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <a href="forum_board.php?id=<?= (int)$topic['category_id'] ?>" class="back-link" style="display:inline-block;text-align:left;margin:0 0 10px 0;">
        ← Terug naar <?= htmlspecialchars($category['name'] ?? 'forum') ?>
    </a>
    <h1>
        <?php if ((int)$topic['is_pinned'] === 1): ?>📌<?php endif; ?>
        <?php if ((int)$topic['is_locked'] === 1): ?>🔒<?php endif; ?>
        <span><?= htmlspecialchars($topic['title']) ?></span>
    </h1>
    <p>
        door <strong style="color:<?= $authorColor ?>;"><?= htmlspecialchars($topic['username']) ?></strong>
        · <?= timeAgo($topic['created_at']) ?>
        · 👁️ <?= number_format((int)$topic['views']) ?> views
    </p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Originele post -->
<section class="section">
    <div class="forum-post">
        <div class="fp-author">
            <div class="fpa-avatar" style="border-color:<?= $authorColor ?>;">
                <?= strtoupper(mb_substr($topic['username'], 0, 1)) ?>
            </div>
            <strong style="color:<?= $authorColor ?>;"><?= htmlspecialchars($topic['username']) ?></strong>
            <small><?= htmlspecialchars($topic['rank_title']) ?></small>
            <small class="muted"><?= number_format((int)$topic['xp']) ?> XP</small>
            <?php if ((int)$topic['total_likes_received'] > 0): ?>
                <small>❤️ <?= number_format((int)$topic['total_likes_received']) ?></small>
            <?php endif; ?>
        </div>
        <div class="fp-body">
            <div class="fp-meta">
                <span>📅 <?= date('d M Y H:i', strtotime($topic['created_at'])) ?></span>
                <?php if ((int)$topic['user_id'] === (int)$user['id'] || $user['id'] == 1): ?>
                    <?php if ((time() - strtotime($topic['created_at'])) <= (FORUM_EDIT_TIME_MIN * 60)): ?>
                        <a href="forum_edit.php?id=<?= (int)$topic['id'] ?>" class="fp-edit">✏️ Bewerken</a>
                    <?php endif; ?>
                    <form method="POST" style="display:inline;"
                          onsubmit="return confirm('Weet je zeker dat je dit topic wil verwijderen? Alle reacties worden ook verwijderd.');">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete_topic">
                        <button type="submit" class="fp-delete">🗑️ Verwijderen</button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="fp-content"><?= nl2br(htmlspecialchars($topic['body'])) ?></div>
        </div>
    </div>
</section>

<!-- Reacties -->
<section class="section">
    <h2>💬 Reacties (<?= number_format($totalReplies) ?>)</h2>

    <?php if (empty($replies)): ?>
        <p class="muted">Nog geen reacties. Wees de eerste die reageert!</p>
    <?php else: ?>
        <div class="forum-replies">
            <?php foreach ($replies as $r):
                $rColor = getUserRankColor($pdo, (int)$r['xp']);
            ?>
                <div class="forum-post reply">
                    <div class="fp-author">
                        <div class="fpa-avatar" style="border-color:<?= $rColor ?>;">
                            <?= strtoupper(mb_substr($r['username'], 0, 1)) ?>
                        </div>
                        <strong style="color:<?= $rColor ?>;"><?= htmlspecialchars($r['username']) ?></strong>
                        <small><?= htmlspecialchars($r['rank_title']) ?></small>
                        <small class="muted"><?= number_format((int)$r['xp']) ?> XP</small>
                    </div>
                    <div class="fp-body">
                        <div class="fp-meta">
                            <span>📅 <?= date('d M Y H:i', strtotime($r['created_at'])) ?></span>
                            <?php if ((int)$r['user_id'] === (int)$user['id'] || $user['id'] == 1): ?>
                                <form method="POST" style="display:inline;"
                                      onsubmit="return confirm('Weet je zeker dat je deze reactie wil verwijderen?');">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete_reply">
                                    <input type="hidden" name="reply_id" value="<?= (int)$r['id'] ?>">
                                    <button type="submit" class="fp-delete">🗑️ Verwijderen</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <div class="fp-content"><?= nl2br(htmlspecialchars($r['body'])) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="forum-pagination">
                <?php if ($page > 1): ?>
                    <a href="?id=<?= $topicId ?>&page=<?= $page - 1 ?>" class="btn btn-outline">← Vorige</a>
                <?php endif; ?>
                <span class="muted">Pagina <?= $page ?> van <?= $totalPages ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="?id=<?= $topicId ?>&page=<?= $page + 1 ?>" class="btn btn-outline">Volgende →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<!-- Nieuwe reactie -->
<?php if ((int)$topic['is_locked'] === 0): ?>
    <section class="section">
        <h2>✍️ Plaats een reactie</h2>
        <div class="casino-card">
            <form method="POST" class="casino-form">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="reply">

                <div class="bet-input">
                    <label>Jouw reactie (max <?= FORUM_BODY_MAX ?> tekens)</label>
                    <textarea name="body" rows="5" required minlength="<?= FORUM_BODY_MIN ?>" maxlength="<?= FORUM_BODY_MAX ?>"
                              placeholder="Typ je reactie hier..."
                              style="padding:12px 14px;background:var(--bg-0);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text);font-family:var(--font-body);font-size:16px;width:100%;resize:vertical;"></textarea>
                </div>

                <button type="submit" class="btn btn-gold btn-full">💬 Plaats reactie</button>
            </form>
        </div>
    </section>
<?php else: ?>
    <div class="alert alert-error">🔒 Dit topic is gesloten. Je kunt niet meer reageren.</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>