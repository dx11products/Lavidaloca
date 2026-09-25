<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$conversations = getUserConversations($pdo, $user['id']);

$pageTitle = 'Privéberichten — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>💌 <span>Privéberichten</span></h1>
    <p>Chat met andere spelers. Je berichten zijn privé en versleuteld.</p>
</div>

<div class="pm-actions-bar">
    <a href="message_new.php" class="btn btn-gold">✍️ Nieuw bericht</a>
    <a href="leaderboard.php" class="btn btn-outline">🔍 Spelers zoeken</a>
</div>

<section class="section">
    <h2>Gesprekken (<?= count($conversations) ?>)</h2>

    <?php if (empty($conversations)): ?>
        <div class="alert alert-error" style="background:var(--bg-2);border-color:var(--border);color:var(--text-dim);">
            Je hebt nog geen gesprekken. <a href="message_new.php">Start een nieuw gesprek →</a>
        </div>
    <?php else: ?>
        <div class="pm-conversation-list">
            <?php foreach ($conversations as $c):
                $color = pmRankColor((int)$c['other_xp']);
                $initial = strtoupper(mb_substr($c['other_username'], 0, 1));
                $unread = (int)$c['unread'];
                $preview = $c['last_body'];
                if ($c['last_sender_id'] == $user['id']) $preview = 'Jij: ' . $preview;
            ?>
                <a href="message.php?id=<?= (int)$c['id'] ?>" class="pm-conversation <?= $unread > 0 ? 'has-unread' : '' ?>">
                    <div class="pmc-avatar" style="border-color:<?= $color ?>;">
                        <?= $initial ?>
                    </div>
                    <div class="pmc-info">
                        <div class="pmc-head">
                            <strong style="color:<?= $color ?>;"><?= htmlspecialchars($c['other_username']) ?></strong>
                            <span class="pmc-time"><?= pmTimeAgo($c['last_at']) ?></span>
                        </div>
                        <div class="pmc-preview"><?= htmlspecialchars(mb_substr($preview, 0, 80)) ?></div>
                    </div>
                    <?php if ($unread > 0): ?>
                        <div class="pmc-unread"><?= $unread > 9 ? '9+' : $unread ?></div>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>