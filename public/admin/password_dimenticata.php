<?php
require_once __DIR__ . '/../../app/lib/auth.php';
require_once __DIR__ . '/../../app/lib/helpers.php';
require_once __DIR__ . '/../../app/lib/mailer.php';

if (utenteCorrente()) {
    redirect('/admin/dashboard.php');
}

$inviato = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerifica();
    $email = trim($_POST['email'] ?? '');
    $utente = trovaUtentePerEmail($email);
    if ($utente) {
        $token = generaTokenReset($utente['id']);
        $base = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
        $link = h($base . '/admin/reimposta_password.php?token=' . urlencode($token));
        $nome = h($utente['nome']);
        inviaEmail($utente['email'], $utente['nome'], 'Reimposta la tua password — Mappa Servizi', <<<HTML
            <p>Ciao {$nome},</p>
            <p>Hai richiesto di reimpostare la password del pannello Mappa Servizi Comunali.</p>
            <p><a href="{$link}">Clicca qui per scegliere una nuova password</a> (il link scade tra 1 ora).</p>
            <p>Se non hai richiesto tu il reset, ignora questa email.</p>
            HTML);
    }
    // Messaggio identico indipendentemente dall'esito, per non rivelare quali email sono registrate.
    $inviato = true;
}
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Password dimenticata — Mappa Servizi</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
<div class="bg-white shadow-sm rounded-lg p-8 w-full max-w-sm">
  <h1 class="text-lg font-semibold text-blue-900 mb-5">Password dimenticata</h1>

  <?php if ($inviato): ?>
    <p class="bg-green-100 text-green-800 text-sm px-3 py-2 rounded mb-4">
      Se l'indirizzo inserito corrisponde a un account, riceverai a breve un'email con le istruzioni per reimpostare la password.
    </p>
    <a href="/admin/login.php" class="text-sm text-blue-800 underline">Torna al login</a>
  <?php else: ?>
    <p class="text-sm text-gray-600 mb-4">Inserisci l'email con cui accedi: ti invieremo un link per scegliere una nuova password.</p>
    <form method="post" class="space-y-3">
      <?= csrfCampo() ?>
      <div>
        <label class="block text-sm font-medium mb-1" for="email">Email</label>
        <input id="email" name="email" type="email" required autofocus class="w-full border rounded px-3 py-2 text-sm">
      </div>
      <button type="submit" class="w-full bg-blue-800 text-white rounded py-2 text-sm font-medium">Invia link di reset</button>
    </form>
    <a href="/admin/login.php" class="block mt-4 text-sm text-blue-800 underline">Torna al login</a>
  <?php endif; ?>
</div>
</body>
</html>
