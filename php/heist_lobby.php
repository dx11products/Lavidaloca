<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$lobbyId = (int)($_GET['id'] ?? 0);
$lobby = getHeistLobby($pdo, $lobbyId);

if (!$lobby) redirect('heists.php');

$members = getHeistMembers($pdo, $lobbyId);
$isMember = isHeistMember($pdo, $lobbyId, $user['id']);
$isHost = (int)$lobby['host_id'] === (int)$user['id'];
$rankData = getRankData((int)$user['xp'], $RANKS);

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    }
    // JOIN
    elseif ($action === 'join') {
        $canJoin = canJoinLobby($pdo, $lobby, $user);
        $heistCooldown = getHeistCooldown($pdo, $user['id']);

        if (!$canJoin['ok']) {
            $error = $canJoin['error'];
        } elseif ($isMember) {
            $error = 'Je bent al lid.';
        } elseif ($rankData['level'] < (int)$lobby['min_rank']) {
            $error = 'Je rank is te laag.';
        } elseif (count($members) >= (int)$lobby['max_players']) {
            $error = 'Team is vol.';
        } elseif ($user['money'] < (int)$lobby['entry_fee']) {
            $error = 'Niet genoeg geld voor inleg.';
        } elseif (getUserActiveLobby($pdo, $user['id'])) {
            $error = 'Je zit al in een ander team.';
        } elseif (!$heistCooldown['ok']) {
            $error = 'Cooldown actief — wacht nog ' . $heistCooldown['wait'] . 's.';
        } else {
            $role = $_POST['role'] ?? 'generiek';

            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE users SET money = money - ? WHERE id = ?")
                    ->execute([$lobby['entry_fee'], $user['id']]);

                $pdo->prepare("
                    INSERT INTO heist_members (lobby_id, user_id, role)
                    VALUES (?, ?, ?)
                ")->execute([$lobbyId, $user['id'], $role]);

                $pdo->prepare("UPDATE heist_lobbies SET reward_pool = reward_pool + ? WHERE id = ?")
                    ->execute([$lobby['entry_fee'], $lobbyId]);

                logActivity($pdo, $user['id'], "🏴 Toegevoegd aan {$lobby['heist_name']} team");
                notify($pdo, $lobby['host_id'], "👥 {$user['username']} is lid geworden van je team!", '👥');
                $pdo->commit();

                redirect("heist_lobby.php?id=$lobbyId");
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Kon niet joinen.';
            }
        }
    }
    // LEAVE
    elseif ($action === 'leave') {
        if (!$isMember) {
            $error = 'Je bent geen lid.';
        } elseif ($isHost) {
            $error = 'De host kan niet vertrekken. Annuleer het team.';
        } elseif ($lobby['status'] !== 'waiting') {
            $error = 'Team is al gestart.';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE users SET money = money + ? WHERE id = ?")
                    ->execute([$lobby['entry_fee'], $user['id']]);

                $pdo->prepare("DELETE FROM heist_members WHERE lobby_id = ? AND user_id = ?")
                    ->execute([$lobbyId, $user['id']]);

                $pdo->prepare("UPDATE heist_lobbies SET reward_pool = reward_pool - ? WHERE id = ?")
                    ->execute([$lobby['entry_fee'], $lobbyId]);

                logActivity($pdo, $user['id'], "🏴 Team verlaten");
                $pdo->commit();
                redirect('heists.php');
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Kon niet vertrekken.';
            }
        }
    }
    // CANCEL
    elseif ($action === 'cancel') {
        if (!$isHost) {
            $error = 'Alleen de host kan annuleren.';
        } elseif ($lobby['status'] !== 'waiting') {
            $error = 'Team is al gestart.';
        } else {
            $pdo->beginTransaction();
            try {
                foreach ($members as $m) {
                    $pdo->prepare("UPDATE users SET money = money + ? WHERE id = ?")
                        ->execute([$lobby['entry_fee'], $m['user_id']]);
                    notify($pdo, $m['user_id'], "🏴 Team geannuleerd — €" . number_format($lobby['entry_fee'], 0, ',', '.') . " terugbetaald.", '🏴');
                }
                $pdo->prepare("UPDATE heist_lobbies SET status = 'cancelled', finished_at = NOW() WHERE id = ?")
                    ->execute([$lobbyId]);
                $pdo->commit();
                redirect('heists.php');
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Kon niet annuleren.';
            }
        }
    }
    // START
    elseif ($action === 'start') {
        if (!$isHost) {
            $error = 'Alleen de host kan starten.';
        } elseif (count($members) < (int)$lobby['min_players']) {
            $error = 'Je hebt minimaal ' . $lobby['min_players'] . ' spelers nodig.';
        } elseif ($lobby['status'] !== 'waiting') {
            $error = 'Team is al gestart.';
        } else {
            $pdo->prepare("UPDATE heist_lobbies SET status = 'in_progress', launched_at = NOW() WHERE id = ?")
                ->execute([$lobbyId]);
            redirect("heist_room.php?id=$lobbyId");
        }
    }
}

$lobby = getHeistLobby($pdo, $lobbyId);
$members = getHeistMembers($pdo, $lobbyId);
$isMember = isHeistMember($pdo, $lobbyId, $user['id']);
$isHost = (int)$lobby['host_id'] === (int)$user['id'];

$successChance = calculateHeistSuccessV2($pdo, $lobby, $members);
$levelMult = getHeistLevelMultiplier($rankData['level']);

$pageTitle = 'Heist lobby — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><?= $lobby['icon'] ?> <span><?= htmlspecialchars($lobby['heist_name']) ?></span></h1>
    <p>
        <?php if ($lobby['is_family_heist']): ?>
            👥 Familie-heist
        <?php else: ?>
            Wachten op teamleden...
        <?php endif; ?>
    </p>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-value"><?= count($members) ?> / <?= $lobby['max_players'] ?></div>
        <div class="stat-label">Team leden</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🎯</div>
        <div class="stat-value" style="color:<?= $successChance >= 60 ? '#58e08c' : ($successChance >= 40 ? 'var(--gold)' : '#ff5c5c') ?>;">
            <?= $successChance ?>%
        </div>
        <div class="stat-label">Slagingskans</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-value gold">€<?= number_format((int)floor($lobby['base_reward_min'] * $levelMult), 0, ',', '.') ?>–€<?= number_format((int)floor($lobby['base_reward_max'] * $levelMult), 0, ',', '.') ?></div>
        <div class="stat-label">Mogelijke buit (×<?= number_format($levelMult, 2) ?>)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">👑</div>
        <div class="stat-value"><?= htmlspecialchars($lobby['host_name']) ?></div>
        <div class="stat-label">Host</div>
    </div>
</div>

<section class="section">
    <h2>Team (<?= count($members) ?>)</h2>
    <div class="family-members">
        <?php foreach ($members as $m):
            $roleInfo = HEIST_ROLES[$m['role']] ?? HEIST_ROLES['generiek'];
        ?>
            <div class="member-row">
                <div class="member-rank-badge rank-<?= $m['role'] === 'meesterbrein' ? 'baas' : ($m['role'] === 'hacker' ? 'onderbaas' : 'lid') ?>">
                    <?= $roleInfo['icon'] ?> <?= htmlspecialchars($roleInfo['name']) ?>
                </div>
                <div class="member-info">
                    <strong><?= htmlspecialchars($m['username']) ?></strong>
                    <small><?= htmlspecialchars($m['rank_title']) ?></small>
                </div>
                <div class="member-stats">
                    <span class="muted"><?= number_format($m['xp']) ?> XP</span>
                    <?php if ((int)$m['user_id'] === (int)$lobby['host_id']): ?>
                        <span class="badge-equipped">Host</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($isHost): ?>
<section class="section">
    <?php if (count($members) < (int)$lobby['min_players']): ?>
        <div class="alert alert-error">
            Wacht op meer teamleden (minimaal <?= $lobby['min_players'] ?>).
        </div>
    <?php else: ?>
        <form method="POST" style="margin-bottom:10px;">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="start">
            <button type="submit" class="btn btn-gold btn-large btn-full">
                🏴 START DE HEIST
            </button>
        </form>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="cancel">
        <button type="submit" class="btn btn-outline btn-full"
                onclick="return confirm('Weet je zeker dat je het team wil annuleren? Iedereen krijgt zijn geld terug.');">
            Annuleer team
        </button>
    </form>
</section>

<?php elseif ($isMember): ?>
<section class="section">
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="leave">
        <button type="submit" class="btn btn-outline btn-full"
                onclick="return confirm('Weet je zeker dat je het team wil verlaten?');">
            Team verlaten
        </button>
    </form>
    <p class="muted" style="text-align:center;margin-top:12px;">Wachten tot de host start...</p>
</section>

<?php else: ?>
<section class="section">
    <h2>Join dit team</h2>
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="join">

        <div class="role-grid">
            <?php
            $myRoles = getUserRoleLevels($pdo, $user['id']);
            foreach (HEIST_ROLES as $key => $role):
                $myRole = $myRoles[$key] ?? null;
                $totalBonus = $myRole ? ($myRole['role_bonus'] + $myRole['level_bonus']) : $role['bonus'];
                $bonusPct = (int)round(($totalBonus - 1) * 100);
            ?>
                <label class="role-card">
                    <input type="radio" name="role" value="<?= $key ?>" <?= $key === 'generiek' ? 'checked' : '' ?>>
                    <div class="role-content">
                        <span class="role-icon"><?= $role['icon'] ?></span>
                        <h3><?= htmlspecialchars($role['name']) ?></h3>
                        <p><?= htmlspecialchars($role['desc']) ?></p>
                        <div class="role-bonus">
                            Bonus: <strong>+<?= $bonusPct ?>%</strong>
                            <?php if ($myRole && $myRole['level'] > 1): ?>
                                <br><small style="color:<?= $myRole['level_color'] ?>;">
                                    <?= $myRole['level_icon'] ?> <?= $myRole['level_name'] ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>

        <button type="submit" class="btn btn-gold btn-large btn-full" style="margin-top:20px;">
            🏴 Join team<?= (int)$lobby['entry_fee'] > 0 ? ' — €' . number_format((int)$lobby['entry_fee'], 0, ',', '.') : '' ?>
        </button>
    </form>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>