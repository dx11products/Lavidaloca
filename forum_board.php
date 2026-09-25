<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$categoryId = (int)($_GET['id'] ?? 0);
$category = getForumCategory($pdo, $categoryId);

if (!$category) redirect('forum.php');

$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * FORUM_TOPICS_PER_PAGE;

$topics = getTopicsInCategory($pdo, $categoryId, FORUM_TOPICS_PER_PAGE, $offset);
$totalTopics = countTopicsInCategory($pdo, $categoryId);
$totalPages = max(1, ceil($totalTopics / FORUM_TOPICS_PER_PAGE));

$pageTitle = htmlspecialchars($category['name']) . ' — Forum';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <a href="forum.php" class="back-link" style="display:inline-block;text-align:left;margin:0 0 10px 0;">← Terug naar forum</a>
    <h1><?= $category['icon'] ?> <span><?= htmlspecialchars($category['name']) ?></span></h1>
    <p><?= htmlspecialchars($category['description']) ?></p>
</div>

<!-- Nieuwe topic -->
<?php if ((int)$category['is_locked'] === 0): ?>
<section class="section">
    <a href="forum_new.php?category=<?= $categoryId ?>" class="btn btn-gold btn-full">
        ➕ Nieuw topic in <?= htmlspecialchars($category['name']) ?>
    </a>
</section>
<?php else: ?>
    <div class="alert alert-error">🔒 Deze categorie is gesloten. Je kunt geen nieuwe topics plaatsen.</div>
<?php endif; ?>

<!-- Topics -->
<section class="section">
    <h2>📝 Topics (<?= number_format($totalTopics) ?>)</h2>

    <?php if (empty($topics)): ?>
        <p class="muted">Nog geen topics in deze categorie. Wees de eerste!</p>
    <?php else: ?>
        <div class="forum-topic-full-list">
            <?php foreach ($topics as $t):
                $rankColor = getUserRankColor($pdo, (int)$t['xp']);
            ?>
                <a href="forum_topic.php?id=<?= (int)$t['id'] ?>" class="forum-topic-row <?= (int)$t['is_pinned'] === 1 ? 'pinned' : '' ?>">
                    <div class="ftr-avatar" style="border-color:<?= $rankColor ?>;">
                        <?= strtoupper(mb_substr($t['username'], 0, 1)) ?>
                    </div>
                    <div class="ftr-info">
                        <h3>
                            <?php if ((int)$t['is_pinned'] === 1): ?>📌<?php endif; ?>
                            <?php if ((int)$t['is_locked'] === 1): ?>🔒<?php endif; ?>
                            <?= htmlspecialchars($t['title']) ?>
                        </h3>
                        <small>
                            door <strong style="color:<?= $rankColor ?>;"><?= htmlspecialchars($t['username']) ?></strong>
                            · <?= htmlspecialchars($t['rank_title']) ?>
                            · <?= timeAgo($t['created_at']) ?>
                        </small>
                    </div>
                    <div class="ftr-stats">
                        <div><span>💬</span><strong><?= (int)$t['reply_count'] ?></strong></div>
                        <div><span>👁️</span><strong><?= (int)$t['views'] ?></strong></div>
                    </div>
                    <div class="ftr-last">
                        <?php if ($t['last_reply_username']): ?>
                            <small>Laatste: <?= htmlspecialchars($t['last_reply_username']) ?></small>
                            <small><?= timeAgo($t['last_reply_at']) ?></small>
                        <?php else: ?>
                            <small class="muted">Geen reacties</small>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="forum-pagination">
                <?php if ($page > 1): ?>
                    <a href="?id=<?= $categoryId ?>&page=<?= $page - 1 ?>" class="btn btn-outline">← Vorige</a>
                <?php endif; ?>
                <span class="muted">Pagina <?= $page ?> van <?= $totalPages ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="?id=<?= $categoryId ?>&page=<?= $page + 1 ?>" class="btn btn-outline">Volgende →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>