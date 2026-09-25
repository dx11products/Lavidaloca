<?php
$adminPageTitle = 'Events';
require __DIR__ . '/includes/admin_header.php';

$error = null;
$success = null;

// Nieuw event
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif ($action === 'create_event') {
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? '🌟');
        $hours = max(1, (int)($_POST['hours'] ?? 6));

        if (empty($title)) {
            $error = 'Titel is verplicht.';
        } else {
            $expires = date('Y-m-d H:i:s', time() + ($hours * 3600));
            $pdo->prepare("
                INSERT INTO world_events (event_key, title, description, icon, expires_at, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
            ")->execute(['manual_' . time(), $title, $desc, $icon, $expires]);

            adminLog($pdo, $admin['id'], 'create_event', 'event', null, $title);
            $success = "Event '{$title}' aangemaakt!";

            // Broadcast
            if (!empty($_POST['broadcast'])) {
                broadcastMessage($pdo, "{$icon} {$title} — {$desc}", $icon);
            }
        }
    } elseif ($action === 'end_event') {
        $eventId = (int)($_POST['event_id'] ?? 0);
        $pdo->prepare("UPDATE world_events SET is_active = 0, expires_at = NOW() WHERE id = ?")
            ->execute([$eventId]);
        adminLog($pdo, $admin['id'], 'end_event', 'event', $eventId);
        $success = 'Event beëindigd.';
    } elseif ($action === 'delete_event') {
        $eventId = (int)($_POST['event_id'] ?? 0);
        $pdo->prepare("DELETE FROM world_events WHERE id = ?")->execute([$eventId]);
        adminLog($pdo, $admin['id'], 'delete_event', 'event', $eventId);
        $success = 'Event verwijderd.';
    }
}

// Events ophalen
$allEvents = $pdo->query("
    SELECT * FROM world_events
    ORDER BY is_active DESC, id DESC
    LIMIT 30
")->fetchAll();
?>

<h1 class="admin-title">🌟 Wereld-events beheren</h1>

<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="admin-alert admin-alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<section class="admin-section">
    <h2>➕ Nieuw event</h2>
    <form method="POST" class="admin-form">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

        <label>Icoon</label>
        <input type="text" name="icon" value="🌟" maxlength="8" style="max-width:100px;">

        <label>Titel</label>
        <input type="text" name="title" required placeholder="Bijv. Dubbele XP Weekend" maxlength="128">

        <label>Beschrijving</label>
        <input type="text" name="description" placeholder="Korte uitleg" maxlength="255">

        <label>Duur (uren)</label>
        <input type="number" name="hours" value="6" min="1" max="720" style="max-width:120px;">

        <label class="checkbox-label">
            <input type="checkbox" name="broadcast" value="1" checked>
            Verstuur broadcast naar alle spelers
        </label>

        <button type="submit" name="action" value="create_event" class="btn btn-gold btn-large">
            🌟 Event aanmaken
        </button>
    </form>
</section>

<section class="admin-section">
    <h2>📋 Alle events</h2>

    <?php if (empty($allEvents)): ?>
        <p class="muted">Geen events.</p>
    <?php else: ?>
        <div class="event-list">
            <?php foreach ($allEvents as $e):
                $isActive = (int)$e['is_active'] === 1 && strtotime($e['expires_at']) > time();
            ?>
                <div class="event-item <?= $isActive ? 'active' : 'inactive' ?>">
                    <div class="event-icon"><?= $e['icon'] ?></div>
                    <div class="event-info">
                        <h3><?= htmlspecialchars($e['title']) ?> <?= $isActive ? '<span class="tag-ok">ACTIEF</span>' : '<span class="tag-muted">AFGELOPEN</span>' ?></h3>
                        <p class="muted"><?= htmlspecialchars($e['description']) ?></p>
                        <small><?= $e['event_key'] ?> · Eindigt <?= date('d M H:i', strtotime($e['expires_at'])) ?></small>
                    </div>
                    <div class="event-actions">
                        <?php if ($isActive): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="action" value="end_event">
                                <input type="hidden" name="event_id" value="<?= (int)$e['id'] ?>">
                                <button type="submit" class="btn btn-outline">Beëindig</button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" style="display:inline;"
                              onsubmit="return confirm('Event verwijderen?');">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete_event">
                            <input type="hidden" name="event_id" value="<?= (int)$e['id'] ?>">
                            <button type="submit" class="btn-icon">🗑️</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>