<?php
require_once __DIR__ . '/../../app/lib/auth.php';
require_once __DIR__ . '/../../app/lib/helpers.php';

$numeroUtenti = (int) db()->query('SELECT COUNT(*) FROM utenti')->fetchColumn();
if ($numeroUtenti > 0) {
    redirect('/admin/login.php');
}

$errore = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerifica();
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $conferma = $_POST['password_conferma'] ?? '';

    if ($nome === '' || $email === '' || strlen($password) < 10) {
        $errore = 'Compila tutti i campi. La password deve avere almeno 10 caratteri.';
    } elseif ($password !== $conferma) {
        $errore = 'Le due password non coincidono.';
    } else {
        $stmt = db()->prepare('INSERT INTO utenti (comune_id, nome, email, password_hash, ruolo) VALUES (NULL, ?, ?, ?, \'superadmin\')');
        $stmt->execute([$nome, $email, password_hash($password, PASSWORD_DEFAULT)]);
        flash('successo', 'Account superadmin creato. Accedi con le credenziali appena impostate.');
        redirect('/admin/login.php');
    }
}
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Configurazione iniziale — Mappa Servizi</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
<div class="bg-white shadow-sm rounded-lg p-8 w-full max-w-md">
  <h1 class="text-lg font-semibold text-blue-900 mb-1">Configurazione iniziale</h1>
  <p class="text-sm text-gray-600 mb-5">Nessun utente presente: crea il primo account, con ruolo superadmin (gestisce tutti e 3 i comuni).</p>

  <?php if ($errore): ?>
    <p class="bg-red-100 text-red-800 text-sm px-3 py-2 rounded mb-4"><?= h($errore) ?></p>
  <?php endif; ?>

  <form method="post" class="space-y-3">
    <?= csrfCampo() ?>
    <div>
      <label class="block text-sm font-medium mb-1" for="nome">Nome e cognome</label>
      <input id="nome" name="nome" required class="w-full border rounded px-3 py-2 text-sm">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1" for="email">Email</label>
      <input id="email" name="email" type="email" required class="w-full border rounded px-3 py-2 text-sm">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1" for="password">Password (minimo 10 caratteri)</label>
      <input id="password" name="password" type="password" minlength="10" required class="w-full border rounded px-3 py-2 text-sm">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1" for="password_conferma">Conferma password</label>
      <input id="password_conferma" name="password_conferma" type="password" minlength="10" required class="w-full border rounded px-3 py-2 text-sm">
    </div>
    <button type="submit" class="w-full bg-blue-800 text-white rounded py-2 text-sm font-medium">Crea account</button>
  </form>
</div>
</body>
</html>
