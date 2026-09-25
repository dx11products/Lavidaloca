<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$myFamily = getUserFamily($pdo, $user['id']);

// Lijst alle families gesorteerd op geld en ledenaantal
$stmt = $pdo->query("
    SELECT f.*,
           (SELECT COUNT(*) FROM family_members WHERE family_id = f.id) AS member_count,
           u.username AS leader_name
    FROM families f
    JOIN users u ON u.id = f.leader_id
    ORDER BY f.money DESC, member_count DESC
    LIMIT 50
");
$families = $stmt->fetchAll();

$pageTitle = 'Familie,s — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>De <span>Familie,s</span></h1>
    <p>Sluit je aan bij een familie of start je eigen imperium.</p>
</div>

<?php if ($myFamily): ?>
    <div class="alert alert-success">
        👥 Je bent lid van <strong>[<?= htmlspecialchars($myFamily['tag']) ?>] <?= htmlspecialchars($myFamily['name']) ?></strong>
        als <strong><?= htmlspecialchars($myFamily['member_rank']) ?></strong>.
        <a href="family.php?id=<?= $myFamily['id'] ?>">Bekijk je familie→</a>
    </div>
<?php else: ?>
    <div class="action-grid" style="margin-bottom:24px;">
        <a href="family.php?action=create" class="action-card">
            <div class="stat-icon">👑</div>
            <h3>Maak je eigen familie</h3>
            <p>Kost €<?= number_format(FAMILY_CREATE_COST, 0, ',', '.') ?>. Jij wordt de baas.</p>
        </a>
        <a href="#list" class="action-card">
            <div class="stat-icon">🔍</div>
            <h3>Sluit je aan</h3>
            <p>Bekijk de lijst hieronder en kies jouw familie.</p>
        </a>
    </div>
<?php endif; ?>

<section class="section" id="list">
    <h2>Alle Familie,s(<?= count($families) ?>)</h2>

    <?php if (empty($families)): ?>
        <p class="muted">Nog geen familie,s. Wees de eerste die er een start!</p>
    <?php else: ?>
        <div class="family-list">
            <?php foreach ($families as $f): ?>
                <a href="family.php?id=<?= (int)$f['id'] ?>" class="family-card">
                    <div class="family-tag-big">[<?= htmlspecialchars($f['tag']) ?>]</div>
                    <div class="family-info">
                        <h3><?= htmlspecialchars($f['name']) ?></h3>
                        <p class="muted">Baas: <?= htmlspecialchars($f['leader_name']) ?></p>
                    </div>
                    <div class="family-stats">
                        <div>
                            <span class="muted">Leden</span>
                            <strong><?= (int)$f['member_count'] ?>/<?= FAMILY_MAX_MEMBERS ?></strong>
                        </div>
                        <div>
                            <span class="muted">Kas</span>
                            <strong class="gold">€<?= number_format($f['money'], 0, ',', '.') ?></strong>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>