<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user      = currentUser($pdo);
$rankData  = getRankData((int)$user['xp'], $RANKS);
$myBonuses = getEquippedBonuses($pdo, $user['id']);

// Munitie check
$myAmmo    = getUserAmmo($pdo, $user['id']);
$totalAmmo = getTotalAmmo($myAmmo);
$hasAmmo   = $totalAmmo >= AMMO_PER_ATTACK;

// Click-wapen bonus
$clickWeaponBonus = getClickWeaponBonus($pdo, $user['id']);
$equippedWeapon = getEquippedClickWeapon($pdo, $user['id']);

$baseAttack  = 10 + (int)$myBonuses['attack'] + $clickWeaponBonus;
$attackBonus = $hasAmmo ? AMMO_DAMAGE_BONUS : 0;
$myAttack    = $baseAttack + $attackBonus;
$myDefense   = 10 + (int)$myBonuses['defense'];

$inHospital = isInHospital($user);
$result = null;
$error  = null;

// === POST VERWERKING (fallback + bank_rob) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    $form = $_POST['form'] ?? 'attack';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    }
    // === BANK OVERVALLEN ===
    elseif ($form === 'bank_rob') {
        if ($inHospital) {
            $error = 'Je ligt in het ziekenhuis.';
        } elseif ($user['energy'] < BANK_ATTACK_COST) {
            $error = 'Niet genoeg energie.';
        } else {
            $newEnergy = $user['energy'] - BANK_ATTACK_COST;
            $success = random_int(1, 100) <= 30;

            if ($success) {
                $loot = random_int(5000, 25000);
                $xp   = 100;

                $pdo->prepare("
                    UPDATE users
                    SET money = money + ?, xp = xp + ?, energy = ?, energy_updated = NOW()
                    WHERE id = ?
                ")->execute([$loot, $xp, $newEnergy, $user['id']]);

                logActivity($pdo, $user['id'], "🏦 Bankoverval geslaagd — €" . number_format($loot, 0, ',', '.'));

                $result = [
                    'success'      => true,
                    'target'       => 'de bank',
                    'loot'         => $loot,
                    'xp'           => $xp,
                    'hospitalized' => false,
                    'is_bank'      => true,
                ];

                $newRank = getRankData($user['xp'] + $xp, $RANKS);
                if ($newRank['level'] > $rankData['level']) {
                    $pdo->prepare("UPDATE users SET rank_title = ? WHERE id = ?")->execute([$newRank['name'], $user['id']]);
                    logActivity($pdo, $user['id'], "🎖️ Gepromoveerd naar {$newRank['name']}!");
                }
            } else {
                $pdo->prepare("UPDATE users SET energy = ?, energy_updated = NOW() WHERE id = ?")
                    ->execute([$newEnergy, $user['id']]);

                logActivity($pdo, $user['id'], "🏦 Bankoverval mislukt");

                $result = [
                    'success'      => false,
                    'target'       => 'de bank',
                    'hospitalized' => false,
                    'is_bank'      => true,
                ];
            }

            checkAchievements($pdo, $user['id']);
            $user = currentUser($pdo);
            $inHospital = isInHospital($user);
        }
    }
    // === SPELER AANVALLEN (fallback) ===
    else {
        $targetId = (int)($_POST['target_id'] ?? 0);

        if ($inHospital) {
            $error = 'Je ligt in het ziekenhuis.';
        } elseif ($user['energy'] < ATTACK_ENERGY_COST) {
            $error = 'Niet genoeg energie.';
        } elseif ($targetId === (int)$user['id']) {
            $error = 'Je kunt jezelf niet aanvallen.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$targetId]);
            $target = $stmt->fetch();

            if (!$target) {
                $error = 'Slachtoffer niet gevonden.';
            } elseif (isInHospital($target)) {
                $error = 'Dit slachtoffer ligt in het ziekenhuis.';
            } else {
                $targetBonuses = getEquippedBonuses($pdo, $target['id']);
                $targetDefense = 10 + (int)$targetBonuses['defense'];

                $chance  = calcWinChance($myAttack, $targetDefense);
                $roll    = random_int(1, 100);
                $success = $roll <= $chance;
                $newEnergy = $user['energy'] - ATTACK_ENERGY_COST;

                if ($success) {
                    $loot = min((int)$target['money'], (int)floor($target['money'] * ATTACK_STEAL_PERCENT / 100));
                    $healthDamage = ATTACK_HEALTH_DAMAGE_WIN;
                    $newHealth = max(0, $target['health'] - $healthDamage);

                    $pdo->beginTransaction();
                    try {
                        if ($hasAmmo) removeAmmo($pdo, $user['id'], 'pistool', AMMO_PER_ATTACK);

                        $pdo->prepare("
                            UPDATE users
                            SET money = money + ?, xp = xp + ?, energy = ?, energy_updated = NOW(),
                                attacks_won = attacks_won + 1, last_attack = NOW()
                            WHERE id = ?
                        ")->execute([$loot, ATTACK_XP_WIN, $newEnergy, $user['id']]);

                        $hospitalUntil = null;
                        if ($newHealth <= 0) $hospitalUntil = date('Y-m-d H:i:s', time() + HOSPITAL_DURATION_MIN * 60);

                        $pdo->prepare("
                            UPDATE users
                            SET money = money - ?, health = ?, hospital_until = COALESCE(?, hospital_until),
                                times_hospitalized = times_hospitalized + ?
                            WHERE id = ?
                        ")->execute([$loot, $newHealth, $hospitalUntil, $newHealth <= 0 ? 1 : 0, $target['id']]);

                        logActivity($pdo, $user['id'], "⚔️ Aanval op {$target['username']} gewonnen — €" . number_format($loot, 0, ',', '.'));
                        logActivity($pdo, $target['id'], "💥 Aangevallen door {$user['username']}");

                        // Clicks + vault
                        addClicks($pdo, $user['id'], CLICKS_PER_ATTACK_WIN);
                        $vaultDrop = null;
                        if (random_int(1, 100) <= VAULT_DROP_ATTACK_WIN) {
                            $vaultDrop = giveRandomVaultCode($pdo, $user['id']);
                        }

                        $pdo->commit();

                        $result = [
                            'success'      => true,
                            'target'       => $target['username'],
                            'loot'         => $loot,
                            'xp'           => ATTACK_XP_WIN,
                            'hospitalized' => $newHealth <= 0,
                            'is_bank'      => false,
                            'used_ammo'    => $hasAmmo,
                            'vault_drop'   => $vaultDrop,
                        ];
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = 'Systeemfout.';
                    }
                } else {
                    $healthDamage = ATTACK_HEALTH_DAMAGE_LOSS;
                    $newHealth = max(0, $user['health'] - $healthDamage);
                    $hospitalUntil = null;
                    if ($newHealth <= 0) $hospitalUntil = date('Y-m-d H:i:s', time() + HOSPITAL_DURATION_MIN * 60);

                    $pdo->beginTransaction();
                    try {
                        if ($hasAmmo) removeAmmo($pdo, $user['id'], 'pistool', AMMO_PER_ATTACK);

                        $pdo->prepare("
                            UPDATE users
                            SET health = ?, hospital_until = COALESCE(?, hospital_until),
                                energy = ?, energy_updated = NOW(),
                                attacks_lost = attacks_lost + 1,
                                times_hospitalized = times_hospitalized + ?,
                                last_attack = NOW()
                            WHERE id = ?
                        ")->execute([$newHealth, $hospitalUntil, $newEnergy, $newHealth <= 0 ? 1 : 0, $user['id']]);

                        $pdo->prepare("UPDATE users SET xp = xp + ?, attacks_won = attacks_won + 1 WHERE id = ?")
                            ->execute([ATTACK_XP_LOSS, $target['id']]);

                        logActivity($pdo, $user['id'], "❌ Aanval op {$target['username']} verloren");
                        logActivity($pdo, $target['id'], "🛡️ Aanval van {$user['username']} afgeslagen");

                        addClicks($pdo, $user['id'], CLICKS_PER_ATTACK_LOSS);

                        $pdo->commit();

                        $result = [
                            'success'      => false,
                            'target'       => $target['username'],
                            'hospitalized' => $newHealth <= 0,
                            'is_bank'      => false,
                            'used_ammo'    => $hasAmmo,
                        ];
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = 'Systeemfout.';
                    }
                }
            }
        }

        checkAchievements($pdo, $user['id']);
        $user = currentUser($pdo);
        $inHospital = isInHospital($user);
        $myAmmo = getUserAmmo($pdo, $user['id']);
        $totalAmmo = getTotalAmmo($myAmmo);
        $hasAmmo = $totalAmmo >= AMMO_PER_ATTACK;
    }
}

// Targets
$stmt = $pdo->prepare("
    SELECT id, username, money, health, max_health, rank_title, xp, hospital_until
    FROM users
    WHERE id != ?
      AND (hospital_until IS NULL OR hospital_until < NOW())
    ORDER BY money DESC
    LIMIT 20
");
$stmt->execute([$user['id']]);
$targets = $stmt->fetchAll();

$pageTitle = 'Aanvallen — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Val een <span>slachtoffer</span> aan</h1>
    <p>Kies iemand uit de lijst. Hoe meer geld ze hebben, hoe meer buit.</p>
</div>

<?php if ($inHospital): ?>
    <div class="alert alert-error">
        🏥 Je ligt in het ziekenhuis voor nog
        <strong><?= floor(hospitalSecondsLeft($user) / 60) ?> minuten</strong>.
        <a href="hospital.php">Koop vervroegd ontslag</a> of wacht tot je hersteld bent.
    </div>
<?php endif; ?>

<?php if (!$hasAmmo && $totalAmmo < AMMO_PER_ATTACK && $totalAmmo > 0): ?>
    <div class="alert alert-error">
        🔫 Je hebt nog maar <strong><?= $totalAmmo ?></strong> kogels.
        Je hebt er <?= AMMO_PER_ATTACK ?> nodig voor de schadebonus.
        <a href="bullets.php">Productie starten →</a>
    </div>
<?php elseif ($totalAmmo === 0): ?>
    <div class="alert alert-error">
        🔫 Je hebt geen kogels. Zonder munitie vecht je met -<?= AMMO_DAMAGE_BONUS ?> schade.
        <a href="bullets.php">Open een kogelfabriek →</a>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($result): ?>
    <div class="alert <?= $result['success'] ? 'alert-success' : 'alert-error' ?>">
        <?php if ($result['success']): ?>
            <?php if ($result['is_bank']): ?>
                🏦 <strong>Bankoverval geslaagd!</strong>
                Je stal <strong>€<?= number_format($result['loot'], 0, ',', '.') ?></strong>
                en verdiende <strong><?= $result['xp'] ?> XP</strong>.
            <?php else: ?>
                ⚔️ Je hebt <strong><?= htmlspecialchars($result['target']) ?></strong> verslagen!
                Je stal <strong>€<?= number_format($result['loot'], 0, ',', '.') ?></strong>
                en verdiende <strong><?= $result['xp'] ?> XP</strong>.
                <?php if (!empty($result['used_ammo'])): ?>
                    <br>🔫 Je gebruikte <?= AMMO_PER_ATTACK ?> kogels (+<?= AMMO_DAMAGE_BONUS ?> schade).
                <?php endif; ?>
                <?php if ($result['hospitalized']): ?>
                    <br>🏥 Je slachtoffer ligt nu in het ziekenhuis.
                <?php endif; ?>
                <?php if (!empty($result['vault_drop'])): ?>
                    <br>🔐 <strong>Kluiscijfer!</strong>
                    <?= htmlspecialchars($result['vault_drop']['vault_name']) ?>
                    — cijfer <?= (int)$result['vault_drop']['value'] ?>
                <?php endif; ?>
            <?php endif; ?>
        <?php else: ?>
            <?php if ($result['is_bank']): ?>
                🏦 <strong>Bankoverval mislukt.</strong>
                De beveiliging was te sterk.
            <?php else: ?>
                ❌ Je aanval op <strong><?= htmlspecialchars($result['target']) ?></strong> mislukte.
                <?php if (!empty($result['used_ammo'])): ?>
                    <br>🔫 Je verloor <?= AMMO_PER_ATTACK ?> kogels.
                <?php endif; ?>
                <?php if ($result['hospitalized']): ?>
                    <br>🏥 Je bent zelf in het ziekenhuis beland.
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Jouw gevechtsstats -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">⚔️</div>
        <div class="stat-value"><?= $myAttack ?></div>
        <div class="stat-label">
            Jouw aanval
            <?php if ($clickWeaponBonus > 0): ?>
                <span style="color:var(--gold);">(+<?= $clickWeaponBonus ?> wapen)</span>
            <?php endif; ?>
            <?php if ($hasAmmo): ?>
                <span style="color:#58e08c;">(+<?= AMMO_DAMAGE_BONUS ?> munitie)</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🛡️</div>
        <div class="stat-value"><?= $myDefense ?></div>
        <div class="stat-label">Jouw verdediging</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⚡</div>
        <div class="stat-value" data-hud="energy"><?= (int)$user['energy'] ?></div>
        <div class="stat-label">Energie (<?= ATTACK_ENERGY_COST ?> per aanval)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🔫</div>
        <div class="stat-value"><?= number_format($totalAmmo, 0, ',', '.') ?></div>
        <div class="stat-label">Kogels (<?= AMMO_PER_ATTACK ?> per aanval)</div>
    </div>
</div>

<!-- Bank overvallen -->
<section class="section">
    <h2>🏦 Bank overvallen</h2>
    <div class="casino-card">
        <p class="muted">
            Overval een bank voor groot geld — maar er is risico.
            Kost <strong><?= BANK_ATTACK_COST ?> energie</strong>.
            <strong>30% kans op succes</strong> — buit tussen €5.000 en €25.000.
        </p>
        <form method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="form" value="bank_rob">

            <button type="submit" class="btn btn-gold btn-full"
                    <?= ($user['energy'] < BANK_ATTACK_COST || $inHospital) ? 'disabled' : '' ?>>
                <?php if ($inHospital): ?>
                    🏥 Je ligt in het ziekenhuis
                <?php elseif ($user['energy'] < BANK_ATTACK_COST): ?>
                    ⚡ Niet genoeg energie
                <?php else: ?>
                    🏦 Overval de bank (<?= BANK_ATTACK_COST ?> ⚡)
                <?php endif; ?>
            </button>
        </form>
    </div>
</section>

<!-- Targets lijst -->
<section class="section">
    <h2>Beschikbare slachtoffers (<?= count($targets) ?>)</h2>

    <?php if (empty($targets)): ?>
        <p class="muted">Geen slachtoffers beschikbaar. Andere spelers liggen allemaal in het ziekenhuis of er zijn geen andere spelers.</p>
    <?php else: ?>
        <div class="target-list">
            <?php foreach ($targets as $t):
                $tBonuses = getEquippedBonuses($pdo, $t['id']);
                $tDefense = 10 + (int)$tBonuses['defense'];
                $chance   = calcWinChance($myAttack, $tDefense);
                $disabled = $inHospital || $user['energy'] < ATTACK_ENERGY_COST;
            ?>
                <div class="target-card <?= $disabled ? 'disabled' : '' ?>">
                    <div class="target-info">
                        <h3><?= htmlspecialchars($t['username']) ?></h3>
                        <p class="muted"><?= htmlspecialchars($t['rank_title']) ?></p>
                        <div class="target-meta">
                            <span>💰 €<?= number_format($t['money'], 0, ',', '.') ?></span>
                            <span>❤️ <?= (int)$t['health'] ?>/<?= (int)$t['max_health'] ?></span>
                            <span>🛡️ <?= $tDefense ?></span>
                        </div>
                    </div>
                    <div class="target-action">
                        <div class="chance">
                            <span class="chance-label">Kans</span>
                            <strong class="chance-value <?= $chance >= 60 ? 'good' : ($chance >= 40 ? 'medium' : 'bad') ?>">
                                <?= $chance ?>%
                            </strong>
                        </div>
                        <button type="button"
                                class="btn btn-gold"
                                data-action="attack"
                                data-target="<?= (int)$t['id'] ?>"
                                <?= $disabled ? 'disabled' : '' ?>>
                            Aanvallen
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>