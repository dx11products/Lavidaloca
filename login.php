<?php
require_once __DIR__ . '/config/db.php';

if (isLoggedIn()) redirect('dashboard.php');

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf     = $_POST['csrf'] ?? '';

    if (!hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
        $error = 'Ongeldige sessie. Probeer opnieuw.';
    } elseif ($username === '' || $password === '') {
        $error = 'Vul je gebruikersnaam en wachtwoord in.';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];

            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

            redirect('dashboard.php');
        } else {
            $error = 'Onbekende gebruikersnaam of wachtwoord.';
        }
    }
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inloggen — La Vida Loca</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">

<div class="auth-container">
    <a href="index.php" class="logo logo-center">LA VIDA <span>LOCA</span></a>

    <div class="auth-card">
        <h1>Welkom terug, <span>baas</span></h1>
        <p class="subtitle">Log in om verder te gaan met je imperium.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">

            <label for="username">Gebruikersnaam</label>
            <input type="text" id="username" name="username" required
                   value="<?= htmlspecialchars($username) ?>" autofocus>

            <label for="password">Wachtwoord</label>
            <input type="password" id="password" name="password" required>

            <button type="submit" class="btn btn-gold btn-full">Inloggen</button>
        </form>

        <p class="auth-switch">
            Nog geen account? <a href="register.php">Registreer hier</a>
        </p>
    </div>

    <a href="index.php" class="back-link">← Terug naar home</a>
</div>

<script src="assets/js/main.js"></script>
</body>
</html>