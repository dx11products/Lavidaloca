<?php
require_once __DIR__ . '/config/db.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendetta — Maffia Browser Game</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=Cormorant+Garamond:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="landing">

<header class="topbar">
    <div class="logo">VEN<span>DETTA</span></div>
    <nav>
        <a href="login.php" class="btn btn-ghost">Inloggen</a>
        <a href="register.php" class="btn btn-gold">Registreren</a>
    </nav>
</header>

<section class="hero">
    <div class="hero-content">
        <h1>Heers over de <span>onderwereld</span></h1>
        <p>Bouw je imperium op, rekruteer bendeleden, verdien geld en klim van straatveger tot godfather. Welkom bij Vendetta.</p>
        <div class="hero-buttons">
            <a href="register.php" class="btn btn-gold btn-large">Start je imperium</a>
            <a href="login.php" class="btn btn-outline btn-large">Ik heb al een account</a>
        </div>
    </div>
    <div class="hero-stats">
        <div class="stat"><strong>12.4K</strong><span>Spelers</span></div>
        <div class="stat"><strong>3</strong><span>Steden</span></div>
        <div class="stat"><strong>24/7</strong><span>Online</span></div>
    </div>
</section>

<section class="features">
    <h2>Wat ga je doen?</h2>
    <div class="feature-grid">
        <div class="feature-card">
            <div class="icon">💰</div>
            <h3>Geld verdienen</h3>
            <p>Overval banken, run een casino, of doe het rustig aan met kleine klusjes.</p>
        </div>
        <div class="feature-card">
            <div class="icon">🔫</div>
            <h3>Bendes</h3>
            <p>Sluit je aan bij een familie of start je eigen bende en heers over de straten.</p>
        </div>
        <div class="feature-card">
            <div class="icon">🏙️</div>
            <h3>Steden veroveren</h3>
            <p>Van Rotterdam tot Amsterdam — breid je territorium uit en bescherm het.</p>
        </div>
        <div class="feature-card">
            <div class="icon">🏆</div>
            <h3>Ranks</h3>
            <p>Klim omhoog van Straatveger tot onbetwiste Godfather van de Lage Landen.</p>
        </div>
    </div>
</section>

<footer class="footer">
    <p>&copy; <?= date('Y') ?> Vendetta. Alle rechten voorbehouden.</p>
</footer>

<script src="assets/js/main.js"></script>
</body>
</html>