<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user     = currentUser($pdo);
$myFamily = getUserFamily($pdo, $user['id']);
$action   = $_GET['action'] ?? '';
$error    = null;
$result   = null;

// Upgrade info
$nextCrimeUpgrade = $myFamily ? getNextCrimeUpgrade($pdo, $user['id']) : null;
$nextHeistUpgrade = $myFamily ? getNextHeistUpgrade($pdo, $user['id']) : null;
$crimeLevel = $myFamily ? max(1, (int)($myFamily['crime_success_level'] ?? 1)) : 1;
$heistLevel = $myFamily ? max(1, (int)($myFamily['heist_success_level'] ?? 1)) : 1;
$crimeBonus = ($crimeLevel - 1) * CRIME_SUCCESS_BONUS_PER_LEVEL;
$heistBonus = ($heistLevel - 1) * HEIST_SUCCESS_BONUS_PER_LEVEL;

// ============================================================
// FAMILIE AANMAKEN
// ============================================================
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'create_family') {
    $name = trim($_POST['name'] ?? '');
    $tag  = strtoupper(trim($_POST['tag'] ?? ''));
    $desc = trim($_POST['description'] ?? '');
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($myFamily) {
        $error = 'Je zit al in een familie.';
    } elseif ($user['money'] < FAMILY_CREATE_COST) {
        $error = 'Je hebt niet genoeg geld. Je hebt €' . number_format(FAMILY_CREATE_COST, 0, ',', '.') . ' nodig.';
    } elseif (strlen($name) < FAMILY_NAME_MIN || strlen($name) > FAMILY_NAME_MAX) {
        $error = 'Naam: ' . FAMILY_NAME_MIN . '-' . FAMILY_NAME_MAX . ' tekens.';
    } elseif (!preg_match('/^[A-Z0-9]{2,6}$/', $tag)) {
        $error = 'Tag: 2-6 tekens (hoofdletters/cijfers).';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM families WHERE name = ? OR tag = ?");
        $stmt->execute([$name, $tag]);
        if ($stmt->fetch()) {
            $error = 'Deze naam of tag is al in gebruik.';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE users SET money = money - ? WHERE id = ?")
                    ->execute([FAMILY_CREATE_COST, $user['id']]);

                $pdo->prepare("INSERT INTO families (name, tag, description, leader_id) VALUES (?, ?, ?, ?)")
                    ->execute([$name, $tag, $desc, $user['id']]);
                $famId = $pdo->lastInsertId();

                $pdo->prepare("INSERT INTO family_members (family_id, user_id, rank) VALUES (?, ?, 'baas')")
                    ->execute([$famId, $user['id']]);

                logActivity($pdo, $user['id'], "👑 Familie [$tag] $name opgericht");
                checkAchievements($pdo, $user['id']);
                $pdo->commit();

                redirect("family.php?id=$famId");
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Kon familie niet aanmaken.';
            }
        }
    }
}

// ============================================================
// FAMILIE JOINEN
// ============================================================
if ($action === 'join' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'join_family') {
    $famId = (int)($_POST['family_id'] ?? 0);
    $csrf  = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($myFamily) {
        $error = 'Je zit al in een familie.';
    } else {
        $stmt = $pdo->prepare("
            SELECT f.*, (SELECT COUNT(*) FROM family_members WHERE family_id = f.id) AS member_count
            FROM families f WHERE id = ? LIMIT 1
        ");
        $stmt->execute([$famId]);
        $fam = $stmt->fetch();

        if (!$fam) {
            $error = 'Familie niet gevonden.';
        } elseif ((int)$fam['member_count'] >= FAMILY_MAX_MEMBERS) {
            $error = 'Deze familie zit vol.';
        } else {
            $pdo->prepare("INSERT INTO family_members (family_id, user_id, rank) VALUES (?, ?, 'lid')")
                ->execute([$famId, $user['id']]);

            logActivity($pdo, $user['id'], "👥 Lid geworden van [{$fam['tag']}] {$fam['name']}");
            notify($pdo, $fam['leader_id'], "👥 {$user['username']} is lid geworden van je familie!", '👥');
            checkAchievements($pdo, $user['id']);

            redirect("family.php?id=$famId");
        }
    }
}

// ============================================================
// FAMILIE VERLATEN
// ============================================================
if ($action === 'leave' && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'leave_family') {
    $csrf = $_POST['csrf'] ?? '';
    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif (!$myFamily) {
        $error = 'Je zit niet in een familie.';
    } elseif ($myFamily['member_rank'] === 'baas') {
        $error = 'Als baas kun je de familie niet verlaten. Je moet hem opheffen.';
    } else {
        $pdo->prepare("DELETE FROM family_members WHERE user_id = ?")->execute([$user['id']]);
        logActivity($pdo, $user['id'], "👋 Familie verlaten");
        notify($pdo, $myFamily['leader_id'], "👋 {$user['username']} heeft je familie verlaten.", '👋');
        redirect('families.php');
    }
}

// ============================================================
// FAMILIEKAS STORTEN
// ============================================================
if (($_POST['form'] ?? '') === 'family_deposit' && $myFamily) {
    $amount = (int)($_POST['amount'] ?? 0);
    $csrf = $_POST['csrf'] ?? '';
    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($amount < 100) {
        $error = 'Minimum storting is €100.';
    } elseif ($user['money'] < $amount) {
        $error = 'Je hebt niet genoeg cash.';
    } else {
        $pdo->prepare("UPDATE users SET money = money - ? WHERE id = ?")
            ->execute([$amount, $user['id']]);
        $pdo->prepare("UPDATE families SET money = money + ? WHERE id = ?")
            ->execute([$amount, $myFamily['id']]);
        logActivity($pdo, $user['id'],
            "👥 €" . number_format($amount, 0, ',', '.') . " gestort in familiekas");

        $result = "€" . number_format($amount, 0, ',', '.') . " gestort in de familiekas!";
        $user = currentUser($pdo);
        $myFamily = getUserFamily($pdo, $user['id']);
    }
}

// ============================================================
// UPGRADE CRIME SUCCES
// ============================================================
if (($_POST['form'] ?? '') === 'upgrade_crime_success' && $myFamily) {
    $csrf = $_POST['csrf'] ?? '';
    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } else {
        $res = upgradeFamilyCrimeSuccess($pdo, $user['id']);
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            $result = "🎯 Crime succes geüpgraded naar level {$res['level']}! (+{$res['new_bonus']}% kans)";
            $user = currentUser($pdo);
            $myFamily = getUserFamily($pdo, $user['id']);
            $nextCrimeUpgrade = getNextCrimeUpgrade($pdo, $user['id']);
            $crimeLevel = max(1, (int)($myFamily['crime_success_level'] ?? 1));
            $crimeBonus = ($crimeLevel - 1) * CRIME_SUCCESS_BONUS_PER_LEVEL;
        }
    }
}

// ============================================================
// UPGRADE HEIST SUCCES
// ============================================================
if (($_POST['form'] ?? '') === 'upgrade_heist_success' && $myFamily) {
    $csrf = $_POST['csrf'] ?? '';
    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } else {
        $res = upgradeFamilyHeistSuccess($pdo, $user['id']);
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            $result = "🏴 Heist succes geüpgraded naar level {$res['level']}! (+{$res['new_bonus']}% kans)";
            $user = currentUser($pdo);
            $myFamily = getUserFamily($pdo, $user['id']);
            $nextHeistUpgrade = getNextHeistUpgrade($pdo, $user['id']);
            $heistLevel = max(1, (int)($myFamily['heist_success_level'] ?? 1));
            $heistBonus = ($heistLevel - 1) * HEIST_SUCCESS_BONUS_PER_LEVEL;
        }
    }
}

// ============================================================
// LEDEN PROMOVEREN / DEGRADEREN
// ============================================================
if (($_POST['form'] ?? '') === 'promote_member' && $myFamily && $myFamily['member_rank'] === 'baas') {
    $memberId = (int)($_POST['member_id'] ?? 0);
    $newRank = $_POST['new_rank'] ?? '';
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif (!in_array($newRank, ['lid', 'capo', 'onderbaas'], true)) {
        $error = 'Ongeldige rank.';
    } elseif ($memberId === (int)$user['id']) {
        $error = 'Je kunt jezelf niet aanpassen.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM family_members WHERE user_id = ? AND family_id = ? LIMIT 1");
        $stmt->execute([$memberId, $myFamily['id']]);
        if ($stmt->fetch()) {
            $pdo->prepare("UPDATE family_members SET rank = ? WHERE user_id = ? AND family_id = ?")
                ->execute([$newRank, $memberId, $myFamily['id']]);
            logActivity($pdo, $user['id'], "👑 Lid gepromoveerd naar {$newRank}");
            notify($pdo, $memberId, "👑 Je bent gepromoveerd naar {$newRank} in [{$myFamily['tag']}]!", '👑');
            $result = "Lid gepromoveerd naar " . ucfirst($newRank) . ".";
        } else {
            $error = 'Lid niet gevonden.';
        }
    }
}

// ============================================================
// FAMILIE DETAIL
// ============================================================
$famId   = (int)($_GET['id'] ?? 0);
$family  = null;
$members = [];

if ($famId > 0) {
    $stmt = $pdo->prepare("
        SELECT f.*, u.username AS leader_name
        FROM families f
        JOIN users u ON u.id = f.leader_id
        WHERE f.id = ? LIMIT 1
    ");
    $stmt->execute([$famId]);
    $family = $stmt->fetch();

    if ($family) {
        $members = getFamilyMembers($pdo, $famId);
    }
}

$activeWar = $myFamily ? getActiveWar($pdo, $myFamily['id']) : null;

$pageTitle = 'Familie — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($result): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($result) ?></div>
<?php endif; ?>

<?php if ($action === 'create' || !$family): ?>
    <div class="page-header">
        <h1>Start je <span>eigen familie</span></h1>
        <p>Kost €<?= number_format(FAMILY_CREATE_COST, 0, ',', '.') ?>. Max <?= FAMILY_MAX_MEMBERS ?> leden.</p>
    </div>

    <?php if ($myFamily): ?>
        <div class="alert alert-error">
            Je zit al in een familie.
            <a href="family.php?id=<?= $myFamily['id'] ?>">Bekijk je familie →</a>
        </div>
    <?php else: ?>
        <div class="auth-card" style="max-width:100%;">
            <form method="POST" action="family.php?action=create">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="form" value="create_family">

                <label>Familie naam</label>
                <input type="text" name="name" required
                       minlength="<?= FAMILY_NAME_MIN ?>" maxlength="<?= FAMILY_NAME_MAX ?>"
                       placeholder="Bijv. Corleone Family">

                <label>Tag (2-6 tekens, hoofdletters)</label>
                <input type="text" name="tag" required minlength="2" maxlength="6"
                       pattern="[A-Z0-9]{2,6}" placeholder="Bijv. CORL"
                       style="text-transform:uppercase;">

                <label>Beschrijving (optioneel)</label>
                <input type="text" name="description" maxlength="255"
                       placeholder="Korte tekst over je familie">

                <button type="submit" class="btn btn-gold btn-full"
                        <?= $user['money'] < FAMILY_CREATE_COST ? 'disabled' : '' ?>>
                    <?= $user['money'] < FAMILY_CREATE_COST
                        ? 'Niet genoeg geld (€' . number_format(FAMILY_CREATE_COST, 0, ',', '.') . ')'
                        : 'Familie oprichten voor €' . number_format(FAMILY_CREATE_COST, 0, ',', '.') ?>
                </button>
            </form>
        </div>
    <?php endif; ?>

<?php elseif ($family): ?>
    <div class="page-header">
        <div class="family-header">
            <div class="family-tag-huge">[<?= htmlspecialchars($family['tag']) ?>]</div>
            <h1><?= htmlspecialchars($family['name']) ?></h1>
            <p><?= htmlspecialchars($family['description']) ?: 'Geen beschrijving.' ?></p>
        </div>
    </div>

    <?php if ($activeWar && $myFamily && $myFamily['id'] == $family['id']): ?>
        <?php
        $isA = (int)$activeWar['family_a_id'] === (int)$family['id'];
        $myScore = $isA ? (int)$activeWar['score_a'] : (int)$activeWar['score_b'];
        $enemyScore = $isA ? (int)$activeWar['score_b'] : (int)$activeWar['score_a'];
        $enemyTag = $isA ? $activeWar['family_b_tag'] : $activeWar['family_a_tag'];
        ?>
        <div class="alert alert-error">
            ⚔️ <strong>Jullie zijn in oorlog met [<?= htmlspecialchars($enemyTag) ?>]!</strong>
            Score: <?= $myScore ?> - <?= $enemyScore ?>.
            <a href="war_battle.php?id=<?= (int)$activeWar['id'] ?>">Ga vechten →</a>
        </div>
    <?php endif; ?>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon">👑</div>
            <div class="stat-value"><?= htmlspecialchars($family['leader_name']) ?></div>
            <div class="stat-label">Baas</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-value"><?= count($members) ?> / <?= FAMILY_MAX_MEMBERS ?></div>
            <div class="stat-label">Leden</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">💰</div>
            <div class="stat-value gold">€<?= number_format($family['money'], 0, ',', '.') ?></div>
            <div class="stat-label">Familiekas</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <div class="stat-value"><?= date('d M Y', strtotime($family['created_at'])) ?></div>
            <div class="stat-label">Opgericht</div>
        </div>
    </div>

    <?php if (!$myFamily): ?>
        <section class="section">
            <form method="POST" action="family.php?action=join">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="form" value="join_family">
                <input type="hidden" name="family_id" value="<?= (int)$family['id'] ?>">
                <button type="submit" class="btn btn-gold btn-large btn-full"
                        <?= count($members) >= FAMILY_MAX_MEMBERS ? 'disabled' : '' ?>>
                    <?= count($members) >= FAMILY_MAX_MEMBERS ? 'Familie zit vol' : 'Word lid van deze familie' ?>
                </button>
            </form>
        </section>
    <?php elseif ($myFamily['id'] == $family['id'] && $myFamily['member_rank'] !== 'baas'): ?>
        <section class="section">
            <form method="POST" action="family.php?action=leave"
                  onsubmit="return confirm('Weet je zeker dat je de familie wil verlaten?');">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="form" value="leave_family">
                <button type="submit" class="btn btn-outline btn-full">Familie verlaten</button>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($myFamily && $myFamily['id'] == $family['id'] && $myFamily['member_rank'] === 'baas'): ?>
        <section class="section">
            <h2>⚔️ Familie oorlog</h2>
            <?php if ($activeWar): ?>
                <div class="war-banner">
                    <div class="war-side">
                        <div class="war-tag">[<?= htmlspecialchars($isA ? $activeWar['family_a_tag'] : $activeWar['family_b_tag']) ?>]</div>
                        <div class="war-score"><?= $isA ? (int)$activeWar['score_a'] : (int)$activeWar['score_b'] ?></div>
                    </div>
                    <div class="war-center">
                        <div class="war-vs">VS</div>
                        <a href="war_battle.php?id=<?= (int)$activeWar['id'] ?>" class="btn btn-gold" style="margin-top:10px;">
                            ⚔️ Ga vechten
                        </a>
                    </div>
                    <div class="war-side">
                        <div class="war-tag">[<?= htmlspecialchars($isA ? $activeWar['family_b_tag'] : $activeWar['family_a_tag']) ?>]</div>
                        <div class="war-score"><?= $isA ? (int)$activeWar['score_b'] : (int)$activeWar['score_a'] ?></div>
                    </div>
                </div>
            <?php else: ?>
                <div class="action-grid">
                    <a href="war.php" class="action-card">
                        <div class="stat-icon">⚔️</div>
                        <h3>Oorlog verklaren</h3>
                        <p>Daag een andere familie uit voor geld en eer.</p>
                    </a>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($myFamily && $myFamily['id'] == $family['id']): ?>
        <section class="section">
            <h2>💰 Familiekas storten</h2>
            <div class="casino-card">
                <p class="muted">Stort geld naar de gezamenlijke familiekas. Alleen de baas kan het opnemen.</p>
                <form method="POST" class="casino-form">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="form" value="family_deposit">

                    <div class="bet-input">
                        <label>Bedrag (€)</label>
                        <input type="number" name="amount" min="100"
                               max="<?= (int)$user['money'] ?>" value="100" step="50" required>
                    </div>

                    <button type="submit" class="btn btn-gold btn-full"
                            <?= $user['money'] < 100 ? 'disabled' : '' ?>>
                        Storten naar familiekas
                    </button>
                </form>
            </div>
        </section>

        <section class="section">
            <h2>⚡ Familie Upgrades</h2>
            <p class="muted">Upgrades gelden voor <strong>alle leden</strong>. Alleen de baas kan upgraden.</p>

            <div class="family-upgrade-grid">

                <!-- CRIME SUCCESS UPGRADE -->
                <div class="family-upgrade-card crime">
                    <div class="fuc-head">
                        <div class="fuc-icon">🎯</div>
                        <div>
                            <h3>Crime Succes</h3>
                            <p class="muted">Verhoogt succes-kans voor alle crimes.</p>
                        </div>
                        <div class="fuc-level">Lv <?= $crimeLevel ?></div>
                    </div>

                    <div class="fuc-stats">
                        <div class="fuc-row">
                            <span>Huidige bonus</span>
                            <strong style="color:#58e08c;">+<?= $crimeBonus ?>%</strong>
                        </div>
                        <?php if ($nextCrimeUpgrade): ?>
                            <div class="fuc-row highlight">
                                <span>Na upgrade (Lv <?= $nextCrimeUpgrade['level'] ?>)</span>
                                <strong style="color:#58e08c;">+<?= $nextCrimeUpgrade['new_bonus'] ?>%</strong>
                            </div>
                            <div class="fuc-row">
                                <span>Kosten</span>
                                <strong style="color:<?= (int)$userFamily['money'] >= $nextCrimeUpgrade['cost'] ? '#58e08c' : '#ff5c5c' ?>;">
                                    €<?= number_format($nextCrimeUpgrade['cost'], 0, ',', '.') ?>
                                </strong>
                            </div>
                        <?php else: ?>
                            <div class="fuc-maxed">🏆 Maximaal niveau bereikt (+<?= $crimeBonus ?>%)</div>
                        <?php endif; ?>
                    </div>

                    <?php if ($nextCrimeUpgrade && $myFamily['member_rank'] === 'baas'): ?>
                        <form method="POST" style="margin-top:14px;">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="form" value="upgrade_crime_success">
                            <button type="submit" class="btn btn-gold btn-full"
                                    <?= (int)$userFamily['money'] < $nextCrimeUpgrade['cost'] ? 'disabled' : '' ?>
                                    onclick="return confirm('€<?= number_format($nextCrimeUpgrade['cost'], 0, ',', '.') ?> uit de familiekas gebruiken?');">
                                <?php if ((int)$userFamily['money'] < $nextCrimeUpgrade['cost']): ?>
                                    ❌ Niet genoeg familiegeld
                                <?php else: ?>
                                    🎯 Upgrade naar Lv <?= $nextCrimeUpgrade['level'] ?>
                                <?php endif; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- HEIST SUCCESS UPGRADE -->
                <div class="family-upgrade-card heist">
                    <div class="fuc-head">
                        <div class="fuc-icon">🏴</div>
                        <div>
                            <h3>Heist Succes</h3>
                            <p class="muted">Verhoogt succes-kans voor alle heists.</p>
                        </div>
                        <div class="fuc-level">Lv <?= $heistLevel ?></div>
                    </div>

                    <div class="fuc-stats">
                        <div class="fuc-row">
                            <span>Huidige bonus</span>
                            <strong style="color:#58e08c;">+<?= $heistBonus ?>%</strong>
                        </div>
                        <?php if ($nextHeistUpgrade): ?>
                            <div class="fuc-row highlight">
                                <span>Na upgrade (Lv <?= $nextHeistUpgrade['level'] ?>)</span>
                                <strong style="color:#58e08c;">+<?= $nextHeistUpgrade['new_bonus'] ?>%</strong>
                            </div>
                            <div class="fuc-row">
                                <span>Kosten</span>
                                <strong style="color:<?= (int)$userFamily['money'] >= $nextHeistUpgrade['cost'] ? '#58e08c' : '#ff5c5c' ?>;">
                                    €<?= number_format($nextHeistUpgrade['cost'], 0, ',', '.') ?>
                                </strong>
                            </div>
                        <?php else: ?>
                            <div class="fuc-maxed">🏆 Maximaal niveau bereikt (+<?= $heistBonus ?>%)</div>
                        <?php endif; ?>
                    </div>

                    <?php if ($nextHeistUpgrade && $myFamily['member_rank'] === 'baas'): ?>
                        <form method="POST" style="margin-top:14px;">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="form" value="upgrade_heist_success">
                            <button type="submit" class="btn btn-gold btn-full"
                                    <?= (int)$userFamily['money'] < $nextHeistUpgrade['cost'] ? 'disabled' : '' ?>
                                    onclick="return confirm('€<?= number_format($nextHeistUpgrade['cost'], 0, ',', '.') ?> uit de familiekas gebruiken?');">
                                <?php if ((int)$userFamily['money'] < $nextHeistUpgrade['cost']): ?>
                                    ❌ Niet genoeg familiegeld
                                <?php else: ?>
                                    🏴 Upgrade naar Lv <?= $nextHeistUpgrade['level'] ?>
                                <?php endif; ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

            </div>
        </section>
    <?php endif; ?>

    <section class="section">
        <h2>Leden (<?= count($members) ?>)</h2>
        <div class="family-members">
            <?php foreach ($members as $m): ?>
                <div class="member-row">
                    <div class="member-rank-badge rank-<?= $m['rank'] ?>">
                        <?= htmlspecialchars($m['rank']) ?>
                    </div>
                    <div class="member-info">
                        <strong><?= htmlspecialchars($m['username']) ?></strong>
                        <small><?= htmlspecialchars($m['rank_title']) ?></small>
                    </div>
                    <div class="member-stats">
                        <span class="muted">€<?= number_format($m['money'], 0, ',', '.') ?></span>
                        <span class="muted"><?= number_format($m['xp']) ?> XP</span>
                    </div>

                    <?php if ($myFamily && $myFamily['id'] == $family['id']
                              && $myFamily['member_rank'] === 'baas'
                              && (int)$m['id'] !== (int)$user['id']): ?>
                        <form method="POST" class="member-promote-form">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="form" value="promote_member">
                            <input type="hidden" name="member_id" value="<?= (int)$m['id'] ?>">

                            <select name="new_rank" onchange="this.form.submit()">
                                <option value="lid"       <?= $m['rank'] === 'lid'       ? 'selected' : '' ?>>Lid</option>
                                <option value="capo"      <?= $m['rank'] === 'capo'      ? 'selected' : '' ?>>Capo</option>
                                <option value="onderbaas" <?= $m['rank'] === 'onderbaas' ? 'selected' : '' ?>>Onderbaas</option>
                            </select>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
                    