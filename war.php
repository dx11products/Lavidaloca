<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$myFamily = getUserFamily($pdo, $user['id']);

// Sluit verlopen oorlogen
closeExpiredWars($pdo);

$error = null;
$result = null;

// Oorlog declareren
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif (!$myFamily) {
        $error = 'Je zit niet in een familie.';
    } elseif ($myFamily['member_rank'] !== 'baas') {
        $error = 'Alleen de baas kan een oorlog starten.';
    } elseif ($action === 'declare') {
        $targetId = (int)($_POST['target_id'] ?? 0);

        if ($targetId === (int)$myFamily['id']) {
            $error = 'Je kunt niet tegen jezelf vechten.';
        } elseif (hasActiveWar($pdo, $myFamily['id'])) {
            $error = 'Je familie is al in oorlog.';
        } else {
            $stmt = $pdo->prepare("
                SELECT f.*,
                       (SELECT COUNT(*) FROM family_members WHERE family_id = f.id) AS member_count
                FROM families f WHERE f.id = ? LIMIT 1
            ");
            $stmt->execute([$targetId]);
            $target = $stmt->fetch();

            if (!$target) {
                $error = 'Familie niet gevonden.';
            } elseif ((int)$target['member_count'] < WAR_MIN_MEMBERS) {
                $error = 'Deze familie heeft te weinig leden (' . WAR_MIN_MEMBERS . ' nodig).';
            } elseif ($myFamily['money'] < WAR_MIN_BANK) {
                $error = 'Je familie bank  heeft minimaal €' . number_format(WAR_MIN_BANK, 0, ',', '.') . ' nodig.';
            } elseif (hasActiveWar($pdo, (int)$target['id'])) {
                $error = 'Deze familie is al in oorlog.';
            } else {
                $endsAt = date('Y-m-d H:i:s', time() + (WAR_DURATION_HOURS * 3600));

                $pdo->prepare("
                    INSERT INTO family_wars (family_a_id, family_b_id, ends_at, status)
                    VALUES (?, ?, ?, 'active')
                ")->execute([$myFamily['id'], $targetId, $endsAt]);

                $warId = $pdo->lastInsertId();

                // Notify beide bendes
                foreach ([$myFamily['id'], $targetId] as $fid) {
                    $stmt = $pdo->prepare("SELECT user_id FROM family_members WHERE family_id = ?");
                    $stmt->execute([$fid]);
                    foreach ($stmt->fetchAll() as $m) {
                        notify($pdo, $m['user_id'],
                            "⚔️ OORLOG! [{$myFamily['tag']}] vs [{$target['tag']}] — " . WAR_DURATION_HOURS . " uur",
                            '⚔️');
                    }
                }

                logActivity($pdo, $user['id'],
                    "⚔️ Oorlog verklaard aan [{$target['tag']}] {$target['name']}");

                redirect("war.php?id=$warId");
            }
        }
    }
}

$myWar = $myFamily ? getActiveWar($pdo, $myFamily['id']) : null;
$targets = $myFamily ? findWarTargets($pdo, $myFamily['id']) : [];
$allWars = getAllActiveWars($pdo);

$pageTitle = 'Familie oorlogen — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Bende <span>oorlogen</span></h1>
    <p>Vecht tegen andere familie,s om geld, eer en territorium.</p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!$myFamily): ?>
    <div class="alert alert-error">
        👥 Je zit niet in een familie. <a href="families.php">Meld je aan bij een familie.→</a>
    </div>
<?php else: ?>

    <!-- Actieve oorlog van jouw bende -->
    <?php if ($myWar): ?>
        <?php
        $isA = (int)$myWar['family_a_id'] === (int)$myFamily['id'];
        $myScore = $isA ? (int)$myWar['score_a'] : (int)$myWar['score_b'];
        $enemyScore = $isA ? (int)$myWar['score_b'] : (int)$myWar['score_a'];
        $enemyName = $isA ? $myWar['family_b_name'] : $myWar['family_a_name'];
        $enemyTag = $isA ? $myWar['family_b_tag'] : $myWar['family_a_tag'];
        $enemyId = $isA ? (int)$myWar['family_b_id'] : (int)$myWar['family_a_id'];
        $myTag = $myFamily['tag'];
        $secondsLeft = max(0, strtotime($myWar['ends_at']) - time());
        $hoursLeft = floor($secondsLeft / 3600);
        $minLeft = floor(($secondsLeft % 3600) / 60);
        ?>
        <section class="section">
            <div class="war-banner">
                <div class="war-side">
                    <div class="war-tag">[<?= htmlspecialchars($myTag) ?>]</div>
                    <div class="war-score <?= $myScore > $enemyScore ? 'lead' : '' ?>"><?= $myScore ?></div>
                </div>
                <div class="war-center">
                    <div class="war-vs">VS</div>
                    <div class="war-time">Nog <?= $hoursLeft ?>u <?= $minLeft ?>m</div>
                    <a href="war_battle.php?id=<?= (int)$myWar['id'] ?>" class="btn btn-gold" style="margin-top:10px;">
                        ⚔️ Voer oorlog.
                    </a>
                </div>
                <div class="war-side">
                    <div class="war-tag">[<?= htmlspecialchars($enemyTag) ?>]</div>
                    <div class="war-score <?= $enemyScore > $myScore ? 'lead' : '' ?>"><?= $enemyScore ?></div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Alle actieve oorlogen -->
    <section class="section">
        <h2>Actieve oorlogen (<?= count($allWars) ?>)</h2>

        <?php if (empty($allWars)): ?>
            <p class="muted">Geen actieve oorlogen op dit moment. Wees de eerste die er een start!</p>
        <?php else: ?>
            <div class="war-list">
                <?php foreach ($allWars as $w): ?>
                    <div class="war-row">
                        <div class="war-row-side">
                            <span class="war-row-tag">[<?= htmlspecialchars($w['family_a_tag']) ?>]</span>
                            <span class="war-row-name"><?= htmlspecialchars($w['family_a_name']) ?></span>
                        </div>
                        <div class="war-row-score">
                            <strong><?= (int)$w['score_a'] ?></strong>
                            <span>—</span>
                            <strong><?= (int)$w['score_b'] ?></strong>
                        </div>
                        <div class="war-row-side right">
                            <span class="war-row-name"><?= htmlspecialchars($w['family_b_name']) ?></span>
                            <span class="war-row-tag">[<?= htmlspecialchars($w['family_b_tag']) ?>]</span>
                        </div>
                    </div>
                    <div class="war-row-meta">
                        <?= (int)$w['total_battles'] ?> gevechten · eindigt
                        <?= date('d M H:i', strtotime($w['ends_at'])) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Oorlog declareren -->
    <?php if ($myFamily['member_rank'] === 'baas' && !$myWar): ?>
    <section class="section">
        <h2>Oorlog declareren</h2>
        <p class="muted">
            Je hebt minimaal €<?= number_format(WAR_MIN_BANK, 0, ',', '.') ?> in de familie bank nodig.
            De verliezer betaalt <?= WAR_PRIZE_PERCENT ?>% van de bank aan de winnaar.
        </p>

        <?php if (empty($targets)): ?>
            <p class="muted">Geen beschikbare familie,s om oorlog tegen te voeren.</p>
        <?php else: ?>
            <div class="family-list">
                <?php foreach ($targets as $t): ?>
                    <div class="family-card">
                        <div class="family-tag-big">[<?= htmlspecialchars($t['tag']) ?>]</div>
                        <div class="family-info">
                            <h3><?= htmlspecialchars($t['name']) ?></h3>
                            <p class="muted"><?= (int)$t['member_count'] ?> leden</p>
                        </div>
                        <div class="family-stats">
                            <div>
                                <span class="muted">Kas</span>
                                <strong class="gold">€<?= number_format($t['money'], 0, ',', '.') ?></strong>
                            </div>
                        </div>
                        <form method="POST" onsubmit="return confirm('Oorlog verklaren aan [<?= htmlspecialchars($t['tag']) ?>]? Dit kan niet ongedaan worden gemaakt.');">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="action" value="declare">
                            <input type="hidden" name="target_id" value="<?= (int)$t['id'] ?>">
                            <button type="submit" class="btn btn-gold"
                                    <?= $myFamily['money'] < WAR_MIN_BANK ? 'disabled' : '' ?>>
                                ⚔️ Oorlog
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>