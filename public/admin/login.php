<?php
require_once __DIR__ . '/../../app/lib/auth.php';
require_once __DIR__ . '/../../app/lib/helpers.php';

$numeroUtenti = (int) db()->query('SELECT COUNT(*) FROM utenti')->fetchColumn();
if ($numeroUtenti === 0) {
    redirect('/admin/setup.php');
}
if (utenteCorrente()) {
    redirect('/admin/dashboard.php');
}

$errore = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerifica();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (tentaLogin($email, $password)) {
        redirect('/admin/dashboard.php');
    }
    $errore = 'Email o password non corrette.';
}
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Accesso — Mappa Servizi</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
<div class="bg-white shadow-sm rounded-lg p-8 w-full max-w-sm">
  <h1 class="text-lg font-semibold text-blue-900 mb-5">Accesso pannello amministrazione</h1>

  <?php if ($errore): ?>
    <p class="bg-red-100 text-red-800 text-sm px-3 py-2 rounded mb-4"><?= h($errore) ?></p>
  <?php endif; ?>
  <?php if ($msg = flash('successo')): ?>
    <p class="bg-green-100 text-green-800 text-sm px-3 py-2 rounded mb-4"><?= h($msg) ?></p>
  <?php endif; ?>

  <form method="post" class="space-y-3">
    <?= csrfCampo() ?>
    <div>
      <label class="block text-sm font-medium mb-1" for="email">Email</label>
      <input id="email" name="email" type="email" required autofocus class="w-full border rounded px-3 py-2 text-sm">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1" for="password">Password</label>
      <input id="password" name="password" type="password" required class="w-full border rounded px-3 py-2 text-sm">
    </div>
    <button type="submit" class="w-full bg-blue-800 text-white rounded py-2 text-sm font-medium">Accedi</button>
  </form>
  <a href="/admin/password_dimenticata.php" class="block mt-4 text-sm text-blue-800 underline text-center">Password dimenticata?</a>
</div>
</body>
</html>
