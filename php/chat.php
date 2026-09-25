<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$userFamily = getUserFamily($pdo, $user['id']);
$onlineCount = countOnlineUsers($pdo);

// Kanaal bepalen
$channel = $_GET['channel'] ?? 'global';
if ($channel === 'family' && !$userFamily) $channel = 'global';
if (!in_array($channel, ['global','family'], true)) $channel = 'global';

$familyId = ($channel === 'family') ? (int)$userFamily['id'] : null;

// Update online
updateChatOnline($pdo, $user['id']);

// Berichten ophalen
$messages = getChatMessages($pdo, $channel, $familyId);
$lastId = !empty($messages) ? (int)end($messages)['id'] : 0;

// Online users
$onlineUsers = getOnlineUsers($pdo);

$pageTitle = 'Chat — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>💬 <span>Chat</span></h1>
    <p>Praat met andere spelers in real-time.</p>
</div>

<!-- Channel tabs -->
<div class="chat-tabs">
    <a href="?channel=global" class="chat-tab <?= $channel === 'global' ? 'active' : '' ?>">
        🌍 <span>Global</span>
        <span class="chat-tab-count"><?= $onlineCount ?> online</span>
    </a>
    <?php if ($userFamily): ?>
        <a href="?channel=family" class="chat-tab <?= $channel === 'family' ? 'active' : '' ?>">
            👥 <span><?= htmlspecialchars($userFamily['name']) ?></span>
        </a>
    <?php else: ?>
        <span class="chat-tab disabled" title="Je zit niet in een familie">
            👥 <span>Familie</span>
        </span>
    <?php endif; ?>
</div>

<div class="chat-layout">
    <!-- Chat box -->
    <div class="chat-box"
         id="chat-box"
         data-channel="<?= htmlspecialchars($channel) ?>"
         data-family="<?= $familyId ?? 0 ?>"
         data-last-id="<?= $lastId ?>"
         data-my-id="<?= (int)$user['id'] ?>"
         data-is-admin="<?= !empty($user['is_admin']) ? 1 : 0 ?>">

        <!-- Berichten -->
        <div class="chat-messages" id="chat-messages">
            <?php if (empty($messages)): ?>
                <div class="chat-empty">
                    Nog geen berichten. Begin het gesprek! 👇
                </div>
            <?php endif; ?>

            <?php foreach ($messages as $m):
                $color = chatRankColor((int)$m['xp']);
                $isMine = (int)$m['user_id'] === (int)$user['id'];
                $canDelete = $isMine || !empty($user['is_admin']);
            ?>
                <div class="chat-msg <?= $isMine ? 'mine' : '' ?>" data-id="<?= (int)$m['id'] ?>">
                    <div class="cm-avatar" style="border-color: <?= $color ?>;">
                        <?= strtoupper(mb_substr($m['username'], 0, 1)) ?>
                    </div>
                    <div class="cm-body">
                        <div class="cm-head">
                            <strong style="color: <?= $color ?>;"><?= htmlspecialchars($m['username']) ?></strong>
                            <small class="cm-rank"><?= htmlspecialchars($m['rank_title']) ?></small>
                            <time><?= chatTimeAgo($m['created_at']) ?></time>
                            <?php if ($canDelete): ?>
                                <button type="button" class="cm-delete"
                                        data-id="<?= (int)$m['id'] ?>"
                                        title="Verwijderen">×</button>
                            <?php endif; ?>
                        </div>
                        <div class="cm-text"><?= nl2br(htmlspecialchars($m['body'])) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Input -->
        <form class="chat-form" id="chat-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="channel" value="<?= htmlspecialchars($channel) ?>">

            <input type="text"
                   name="body"
                   id="chat-input"
                   placeholder="Typ een bericht..."
                   maxlength="<?= CHAT_MSG_MAX ?>"
                   autocomplete="off"
                   required>

            <button type="submit" class="btn btn-gold" id="chat-send">
                Verstuur ➤
            </button>
        </form>

        <div class="chat-info-bar">
            <span id="chat-typing">Max <?= CHAT_MSG_MAX ?> tekens · Cooldown <?= CHAT_COOLDOWN_SEC ?>s</span>
            <span id="chat-status" class="chat-status"></span>
        </div>
    </div>

    <!-- Sidebar: online users -->
    <aside class="chat-sidebar">
        <h3>🟢 Online (<?= count($onlineUsers) ?>)</h3>

        <?php if (empty($onlineUsers)): ?>
            <p class="muted">Niemand online.</p>
        <?php else: ?>
            <ul class="chat-online-list" id="chat-online-list">
                <?php foreach ($onlineUsers as $u):
                    $color = chatRankColor((int)$u['xp']);
                ?>
                    <li data-user="<?= (int)$u['id'] ?>">
                        <span class="cou-dot" style="background: <?= $color ?>;"></span>
                        <span class="cou-name" style="color: <?= $color ?>;"><?= htmlspecialchars($u['username']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="chat-sidebar-footer">
            <a href="messages.php" class="btn btn-outline btn-full">💌 Privéberichten</a>
        </div>
    </aside>
</div>

<script src="assets/js/chat.js"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>