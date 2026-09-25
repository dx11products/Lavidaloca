<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$categories = getForumCategories($pdo);
$stats = getForumStats($pdo);
$recentTopics = getRecentTopics($pdo, 8);
$popularTopics = getPopularTopics($pdo, 5);

$pageTitle = 'Forum — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Het <span>Forum</span></h1>
    <p>Praat met andere spelers, deel strategieën en stel vragen.</p>
</div>

<!-- Forum stats -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">📝</div>
        <div class="stat-value"><?= number_format($stats['topics']) ?></div>
        <div class="stat-label">Topics</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💬</div>
        <div class="stat-value"><?= number_format($stats['replies']) ?></div>
        <div class="stat-label">Reacties</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-value"><?= number_format($stats['members']) ?></div>
        <div class="stat-label">Actieve leden</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">➕</div>
        <div class="stat-value">∞</div>
        <div class="stat-label">Iedereen kan posten</div>
    </div>
</div>

<!-- Categorieën -->
<section class="section">
    <h2>📂 Categorieën</h2>
    <div class="forum-category-grid">
        <?php foreach ($categories as $cat):
            $catStats = getCategoryStats($pdo, (int)$cat['id']);
            $locked = (int)$cat['is_locked'] === 1;
        ?>
            <a href="forum_board.php?id=<?= (int)$cat['id'] ?>" class="forum-category-card" style="border-left-color:<?= htmlspecialchars($cat['color']) ?>;">
                <div class="fc-icon" style="color:<?= htmlspecialchars($cat['color']) ?>;"><?= $cat['icon'] ?></div>
                <div class="fc-info">
                    <h3>
                        <?= htmlspecialchars($cat['name']) ?>
                        <?php if ($locked): ?>🔒<?php endif; ?>
                    </h3>
                    <p class="muted"><?= htmlspecialchars($cat['description']) ?></p>
                    <div class="fc-stats">
                        <span>📝 <?= number_format($catStats['topics']) ?> topics</span>
                        <span>💬 <?= number_format($catStats['replies']) ?> reacties</span>
                    </div>
                </div>
                <?php if ($catStats['last_topic']): ?>
                    <div class="fc-last">
                        <span class="muted">Laatste:</span>
                        <strong><?= htmlspecialchars(forumExcerpt($catStats['last_topic']['title'], 30)) ?></strong>
                        <small><?= htmlspecialchars($catStats['last_topic']['username']) ?> · <?= timeAgo($catStats['last_topic']['last_reply_at'] ?? $catStats['last_topic']['created_at']) ?></small>
                    </div>
                <?php else: ?>
                    <div class="fc-last">
                        <span class="muted">Nog geen topics</span>
                    </div>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Recent + Popular -->
<div class="dashboard-split">
    <section class="section">
        <h2>🔥 Recente topics</h2>
        <?php if (empty($recentTopics)): ?>
            <p class="muted">Nog geen topics. <a href="forum_new.php">Start de eerste!</a></p>
        <?php else: ?>
            <ul class="forum-topic-list">
                <?php foreach ($recentTopics as $t): ?>
                    <li>
                        <div class="ft-icon" style="color:<?= htmlspecialchars($t['category_color']) ?>;">
                            <?= $t['category_icon'] ?>
                        </div>
                        <div class="ft-info">
                            <a href="forum_topic.php?id=<?= (int)$t['id'] ?>" class="ft-title">
                                <?= htmlspecialchars($t['title']) ?>
                            </a>
                            <small>
                                door <strong><?= htmlspecialchars($t['username']) ?></strong>
                                in <?= htmlspecialchars($t['category_name']) ?>
                                · <?= timeAgo($t['last_reply_at'] ?? $t['created_at']) ?>
                            </small>
                        </div>
                        <div class="ft-count">
                            <span>💬 <?= (int)$t['reply_count'] ?></span>
                            <small>👁️ <?= (int)$t['views'] ?></small>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="section">
        <h2>📈 Populair</h2>
        <?php if (empty($popularTopics)): ?>
            <p class="muted">Nog geen populaire topics.</p>
        <?php else: ?>
            <ul class="forum-topic-list">
                <?php foreach ($popularTopics as $i => $t): ?>
                    <li>
                        <div class="ft-rank">#<?= $i + 1 ?></div>
                        <div class="ft-info">
                            <a href="forum_topic.php?id=<?= (int)$t['id'] ?>" class="ft-title">
                                <?= htmlspecialchars(forumExcerpt($t['title'], 40)) ?>
                            </a>
                            <small><?= htmlspecialchars($t['username']) ?> · 💬 <?= (int)$t['reply_count'] ?></small>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<!-- Nieuwe topic knop -->
<section class="section">
    <a href="forum_new.php" class="btn btn-gold btn-large btn-full">
        ➕ Nieuw topic starten
    </a>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>