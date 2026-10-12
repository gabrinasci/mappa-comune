<?php
require_once __DIR__ . '/../../app/lib/auth.php';
require_once __DIR__ . '/../../app/lib/helpers.php';

// Solo via POST con token CSRF: un link esterno non può disconnettere un amministratore.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerifica();
    logout();
}
redirect('/admin/login.php');
