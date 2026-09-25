<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

$user = currentUser($pdo);
$conversationId = (int)($_GET['id'] ?? 0);
$conversation = getConversation($pdo, $conversationId);

if (!$conversation) redirect('messages.php');

// Check of user in gesprek zit
if ((int)$conversation['user_a'] !== (int)$user['id'] && (int)$conversation['user_b'] !== (int)$user['id']) {
    redirect('messages.php');
}

$otherId = getOtherUser($conversation, (int)$user['id']);
$stmt = $pdo->prepare("SELECT id, username, xp, rank_title FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$otherId]);
$other = $stmt->fetch();
if (!$other) redirect('messages.php');

// Markeer als gelezen
markConversationRead($pdo, $conversationId, (int)$user['id']);

// Berichten
$messages = getConversationMessages($pdo, $conversationId, (int)$user['id'], PM_MESSAGES_PER_PAGE);
$lastMessageId = !empty($messages) ? (int)end($messages)['id'] : 0;
$lastMessageId = !empty($messages) ? (int)end($messages)['id'] : 0;

$blocked = isBlocked($pdo, (int)$user['id'], $otherId);
$color = pmRankColor((int)$other['xp']);

$pageTitle = 'Gesprek met ' . $other['username'] . ' — Vendetta';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <a href="messages.php" class="back-link" style="display:inline-block;text-align:left;margin:0 0 10px 0;">← Terug naar inbox</a>
    <h1>
        <span style="color:<?= $color ?>;"><?= htmlspecialchars($other['username']) ?></span>
    </h1>
    <p><?= htmlspecialchars($other['rank_title']) ?> · <?= number_format((int)$other['xp']) ?> XP</p>
</div>

<?php if ($blocked): ?>
    <div class="alert alert-error">
        🚫 Een van jullie heeft de ander geblokkeerd. Je kunt geen berichten sturen.
        <form method="POST" action="message.php?id=<?= $conversationId ?>" style="display:inline;">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="unblock">
            <button type="submit" class="btn btn-outline" style="margin-left:10px;">Deblokkeren</button>
        </form>
    </div>
<?php endif; ?>

<div class="pm-chat" id="pm-chat"
     data-conversation="<?= $conversationId ?>"
     data-last-id="<?= $lastMessageId ?>"
     data-my-id="<?= (int)$user['id'] ?>">

    <div class="pm-messages" id="pm-messages">
        <?php foreach ($messages as $m):
            $isMine = (int)$m['sender_id'] === (int)$user['id'];
        ?>
            <div class="pm-message <?= $isMine ? 'mine' : 'theirs' ?>" data-id="<?= (int)$m['id'] ?>">
                <div class="pmm-body"><?= nl2br(htmlspecialchars($m['body'])) ?></div>
                <div class="pmm-meta">
                    <span><?= date('H:i', strtotime($m['created_at'])) ?></span>
                    <?php if ($isMine && (int)$m['is_read'] === 1): ?>
                        <span class="pmm-read">✓✓</span>
                    <?php elseif ($isMine): ?>
                        <span class="pmm-read">✓</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($messages)): ?>
            <div class="pm-empty">Begin het gesprek met een eerste bericht 👇</div>
        <?php endif; ?>
    </div>

    <?php if (!$blocked): ?>
        <form class="pm-form" id="pm-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="conversation_id" value="<?= $conversationId ?>">

            <textarea name="body" id="pm-input" rows="2" maxlength="<?= PM_BODY_MAX ?>"
                      placeholder="Typ een bericht..." required></textarea>

            <button type="submit" class="btn btn-gold" id="pm-send">
                Verstuur ➤
            </button>
        </form>
    <?php endif; ?>
</div>

<!-- Verwijder conversatie -->
<section class="section">
    <form method="POST" action="message.php?id=<?= $conversationId ?>" style="display:inline;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="delete_conversation">
        <button type="submit" class="btn btn-outline"
                onclick="return confirm('Weet je zeker dat je dit gesprek wil verwijderen?');">
            🗑️ Gesprek verwijderen
        </button>
    </form>

    <form method="POST" action="message.php?id=<?= $conversationId ?>" style="display:inline;">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="action" value="block">
        <button type="submit" class="btn btn-outline">
            <?= $blocked ? '🔓 Deblokkeren' : '🚫 Blokkeren' ?>
        </button>
    </form>
</section>

<?php
// Verwerk POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    $action = $_POST['action'] ?? '';

    if (hash_equals(csrf_token(), $csrf)) {
        if ($action === 'delete_conversation') {
            deleteConversationForUser($pdo, $conversationId, (int)$user['id']);
            redirect('messages.php');
        } elseif ($action === 'block' || $action === 'unblock') {
            toggleBlock($pdo, (int)$user['id'], $otherId);
            redirect("message.php?id=$conversationId");
        }
    }
}
?>

<?php require __DIR__ . '/includes/footer.php'; ?>