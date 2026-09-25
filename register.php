<?php
require_once __DIR__ . '/config/db.php';

if (isLoggedIn()) redirect('dashboard.php');

$error = '';
$username_post = '';
$email_post = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_post = trim($_POST['username'] ?? '');
    $email_post    = trim($_POST['email'] ?? '');
    $password      = $_POST['password'] ?? '';
    $confirm       = $_POST['confirm'] ?? '';
    $csrf          = $_POST['csrf'] ?? '';

    if (!hash_equals(csrf_token(), $csrf)) {
        $error = 'Ongeldige sessie.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username_post)) {
        $error = 'Gebruikersnaam: 3-20 tekens (letters, cijfers, _).';
    } elseif (!filter_var($email_post, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ongeldig e-mailadres.';
    } elseif (strlen($password) < 8) {
        $error = 'Wachtwoord moet minimaal 8 tekens zijn.';
    } elseif ($password !== $confirm) {
        $error = 'Wachtwoorden komen niet overeen.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username_post, $email_post]);
        if ($stmt->fetch()) {
            $error = 'Gebruikersnaam of e-mail is al in gebruik.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)")
                ->execute([$username_post, $email_post, $hash]);

            session_regenerate_id(true);
            $_SESSION['user_id']  = $pdo->lastInsertId();
            $_SESSION['username'] = $username_post;
            redirect('dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registreren — Vendetta</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">

<div class="auth-container">
    <a href="index.php" class="logo logo-center">VEN<span>DETTA</span></a>

    <div class="auth-card">
        <h1>Word een <span>baas</span></h1>
        <p class="subtitle">Maak je account aan en begin je imperium.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">

            <label>Gebruikersnaam</label>
            <input type="text" name="username" required value="<?= htmlspecialchars($username_post) ?>">

            <label>E-mail</label>
            <input type="email" name="email" required value="<?= htmlspecialchars($email_post) ?>">

            <label>Wachtwoord</label>
            <input type="password" name="password" required>

            <label>Bevestig wachtwoord</label>
            <input type="password" name="confirm" required>

            <button type="submit" class="btn btn-gold btn-full">Account aanmaken</button>
        </form>

        <p class="auth-switch">Al een account? <a href="login.php">Inloggen</a></p>
    </div>

    <a href="index.php" class="back-link">← Terug naar home</a>
</div>

<script src="assets/js/main.js"></script>
</body>
</html>