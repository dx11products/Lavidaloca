<?php
require_once __DIR__ . '/config/db.php';
if (!isLoggedIn()) redirect('login.php');

// Kluizen zijn verplaatst naar het inbox-systeem
redirect('inbox.php');