<?php
require_once __DIR__ . '/config/db.php';
$pageTitle = 'Gebruiksvoorwaarden';
require __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Gebruiks<span>voorwaarden</span></h1>
    <p>Laatst bijgewerkt: <?= date('d F Y') ?></p>
</div>

<div class="content-card">
    <h2>1. Toegang</h2>
    <p>Om Vendetta te spelen moet je een account aanmaken. Je bent zelf verantwoordelijk voor de veiligheid van je inloggegevens.</p>

    <h2>2. Spelregels</h2>
    <p>Het is niet toegestaan om:</p>
    <ul>
        <li>Meerdere accounts aan te maken (multi-accounting) om voordeel te halen</li>
        <li>Bugs of exploits te misbruiken voor eigen gewin</li>
        <li>Andere spelers te beledigen of te intimideren</li>
        <li>Geautomatiseerde scripts te gebruiken (bots)</li>
    </ul>

    <h2>3. Sancties</h2>
    <p>Bij overtreding kunnen we je account tijdelijk blokkeren of permanent verwijderen, zonder recht op teruggave.</p>

    <h2>4. Aansprakelijkheid</h2>
    <p>Vendetta is een gratis spel dat "as is" wordt aangeboden. We zijn niet aansprakelijk voor verlies van gegevens of virtuele bezittingen.</p>

    <h2>5. Wijzigingen</h2>
    <p>We behouden ons het recht voor deze voorwaarden op elk moment aan te passen.</p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>