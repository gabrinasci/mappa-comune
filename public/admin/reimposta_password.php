<?php
require_once __DIR__ . '/../../app/lib/auth.php';
require_once __DIR__ . '/../../app/lib/helpers.php';

if (utenteCorrente()) {
    redirect('/admin/dashboard.php');
}

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$utente = $token ? trovaUtentePerTokenReset($token) : null;
$errore = null;
$completato = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $utente) {
    csrfVerifica();
    $password = $_POST['password'] ?? '';
    $conferma = $_POST['password_conferma'] ?? '';
    if (strlen($password) < 10) {
        $errore = 'La password deve avere almeno 10 caratteri.';
    } elseif ($password !== $conferma) {
        $errore = 'Le due password non coincidono.';
    } else {
        reimpostaPassword($utente['id'], $password);
        $completato = true;
    }
}
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reimposta password — Mappa Servizi</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
<div class="bg-white shadow-sm rounded-lg p-8 w-full max-w-sm">
  <h1 class="text-lg font-semibold text-blue-900 mb-5">Reimposta password</h1>

  <?php if ($completato): ?>
    <p class="bg-green-100 text-green-800 text-sm px-3 py-2 rounded mb-4">Password aggiornata. Ora puoi accedere.</p>
    <a href="/admin/login.php" class="text-sm text-blue-800 underline">Vai al login</a>

  <?php elseif (!$utente): ?>
    <p class="bg-red-100 text-red-800 text-sm px-3 py-2 rounded mb-4">
      Il link non è valido o è scaduto (i link durano 1 ora). Richiedine uno nuovo.
    </p>
    <a href="/admin/password_dimenticata.php" class="text-sm text-blue-800 underline">Richiedi nuovo link</a>

  <?php else: ?>
    <?php if ($errore): ?>
      <p class="bg-red-100 text-red-800 text-sm px-3 py-2 rounded mb-4"><?= h($errore) ?></p>
    <?php endif; ?>
    <form method="post" class="space-y-3">
      <?= csrfCampo() ?>
      <input type="hidden" name="token" value="<?= h($token) ?>">
      <div>
        <label class="block text-sm font-medium mb-1" for="password">Nuova password (minimo 10 caratteri)</label>
        <input id="password" name="password" type="password" minlength="10" required autofocus class="w-full border rounded px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1" for="password_conferma">Conferma password</label>
        <input id="password_conferma" name="password_conferma" type="password" minlength="10" required class="w-full border rounded px-3 py-2 text-sm">
      </div>
      <button type="submit" class="w-full bg-blue-800 text-white rounded py-2 text-sm font-medium">Salva nuova password</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
