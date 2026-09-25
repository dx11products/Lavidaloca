<?php
$adminPageTitle = 'Instellingen';
require __DIR__ . '/includes/admin_header.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } else {
        $key = $_POST['key'] ?? '';
        $value = $_POST['value'] ?? '';

        if ($key !== '') {
            updateSetting($pdo, $key, $value);
            adminLog($pdo, $admin['id'], 'update_setting', 'setting', null, "{$key} = {$value}");
            $success = 'Instelling opgeslagen.';
        }
    }
}

// Groepeer per categorie
$allSettings = getAllSettings($pdo);
$grouped = [];
foreach ($allSettings as $s) {
    $grouped[$s['category']][] = $s;
}

$categoryLabels = [
    'general'   => '⚙️ Algemeen',
    'economy'   => '💰 Economie',
    'pvp'       => '⚔️ PvP',
    'community' => '👥 Community',
];
?>

<h1 class="admin-title">⚙️ Game Instellingen</h1>

<?php if ($error): ?><div class="admin-alert admin-alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="admin-alert admin-alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="admin-alert admin-alert-warning">
    ⚠️ Deze instellingen wijzigen het spel DIRECT. Wees voorzichtig!
</div>

<?php foreach ($grouped as $cat => $settings): ?>
    <section class="admin-section">
        <h2><?= $categoryLabels[$cat] ?? ucfirst($cat) ?></h2>
        <div class="settings-grid">
            <?php foreach ($settings as $s): ?>
                <form method="POST" class="setting-item">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="key" value="<?= htmlspecialchars($s['key']) ?>">

                    <div class="setting-info">
                        <label><strong><?= htmlspecialchars($s['label'] ?: $s['key']) ?></strong></label>
                        <small class="muted"><?= htmlspecialchars($s['description']) ?></small>
                        <small class="setting-key"><code><?= htmlspecialchars($s['key']) ?></code></small>
                    </div>

                    <div class="setting-input">
                        <?php if ($s['type'] === 'bool'): ?>
                            <select name="value" class="setting-field">
                                <option value="1" <?= $s['parsed_value'] ? 'selected' : '' ?>>Aan</option>
                                <option value="0" <?= !$s['parsed_value'] ? 'selected' : '' ?>>Uit</option>
                            </select>
                        <?php elseif ($s['type'] === 'int' || $s['type'] === 'float'): ?>
                            <input type="number" name="value" value="<?= htmlspecialchars($s['value']) ?>"
                                   step="<?= $s['type'] === 'float' ? '0.001' : '1' ?>"
                                   class="setting-field">
                        <?php else: ?>
                            <input type="text" name="value" value="<?= htmlspecialchars($s['value']) ?>"
                                   class="setting-field">
                        <?php endif; ?>
                        <button type="submit" class="btn btn-gold">💾</button>
                    </div>
                </form>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>