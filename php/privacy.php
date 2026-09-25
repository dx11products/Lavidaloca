<?php
require_once __DIR__ . '/config/db.php';
$pageTitle = 'Privacybeleid';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Privacy<span>beleid</span></h1>
    <p>Laatst bijgewerkt: <?= date('d F Y') ?></p>
</div>

<div class="content-card">
    <h2>1. Welke gegevens verzamelen we?</h2>
    <p>We verzamelen het minimum aan gegevens dat nodig is om het spel te laten werken: gebruikersnaam, e-mailadres en een gehasht wachtwoord. We slaan ook in-game statistieken op (geld, XP, ranks, activiteiten).</p>

    <h2>2. Hoe gebruiken we je gegevens?</h2>
    <p>Uitsluitend voor het functioneren van het spel. We verkopen of delen je gegevens niet met derde partijen.</p>

    <h2>3. Wachtwoorden</h2>
    <p>Je wachtwoord wordt gehasht met bcrypt en is voor niemand, ook niet voor ons, leesbaar. We kunnen je wachtwoord niet opvragen of herstellen.</p>

    <h2>4. Cookies</h2>
    <p>We gebruiken één functionele sessie-cookie om je ingelogd te houden. Geen tracking cookies, geen advertenties.</p>

    <h2>5. Verwijdering</h2>
    <p>Wil je je account verwijderen? Neem contact op met de beheerder van het spel.</p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>