<?php
require_once __DIR__ . '/../../app/lib/auth.php';
require_once __DIR__ . '/../../app/lib/helpers.php';

$utente = richiediLogin();
$pdo = db();
$errore = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerifica();
    $attuale = $_POST['password_attuale'] ?? '';
    $nuova = $_POST['password_nuova'] ?? '';
    $conferma = $_POST['password_conferma'] ?? '';

    if (!password_verify($attuale, $utente['password_hash'])) {
        $errore = 'La password attuale non è corretta.';
    } elseif (strlen($nuova) < 10) {
        $errore = 'La nuova password deve avere almeno 10 caratteri.';
    } elseif ($nuova !== $conferma) {
        $errore = 'Le due password non coincidono.';
    } else {
        $pdo->prepare('UPDATE utenti SET password_hash = ? WHERE id = ?')->execute([password_hash($nuova, PASSWORD_DEFAULT), $utente['id']]);
        flash('successo', 'Password aggiornata.');
        redirect('/admin/profilo.php');
    }
}

$stmt = $pdo->prepare('SELECT * FROM log_accessi WHERE utente_id = ? ORDER BY creato_il DESC LIMIT 10');
$stmt->execute([$utente['id']]);
$accessi = $stmt->fetchAll();

$nomeComune = null;
if ($utente['comune_id']) {
    $stmt = $pdo->prepare('SELECT nome FROM comuni WHERE id = ?');
    $stmt->execute([$utente['comune_id']]);
    $nomeComune = $stmt->fetchColumn();
}
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Il mio profilo — Mappa Servizi</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="max-w-2xl mx-auto px-4 py-8 space-y-8">
  <h1 class="text-xl font-semibold">Il mio profilo</h1>

  <section class="bg-white rounded-lg shadow-sm p-5">
    <dl class="text-sm space-y-1">
      <div class="flex justify-between"><dt class="text-gray-500">Nome</dt><dd><?= h($utente['nome']) ?></dd></div>
      <div class="flex justify-between"><dt class="text-gray-500">Email</dt><dd><?= h($utente['email']) ?></dd></div>
      <div class="flex justify-between"><dt class="text-gray-500">Ruolo</dt><dd><?= $utente['ruolo'] === 'superadmin' ? 'Superadmin' : 'Admin comune' ?></dd></div>
      <?php if ($nomeComune): ?>
        <div class="flex justify-between"><dt class="text-gray-500">Comune</dt><dd><?= h($nomeComune) ?></dd></div>
      <?php endif; ?>
    </dl>
  </section>

  <section class="bg-white rounded-lg shadow-sm p-5">
    <h2 class="text-sm font-semibold uppercase text-gray-500 mb-3">Cambia password</h2>
    <?php if ($msg = flash('successo')): ?>
      <p class="bg-green-100 text-green-800 text-sm px-3 py-2 rounded mb-4"><?= h($msg) ?></p>
    <?php endif; ?>
    <?php if ($errore): ?>
      <p class="bg-red-100 text-red-800 text-sm px-3 py-2 rounded mb-4"><?= h($errore) ?></p>
    <?php endif; ?>
    <form method="post" class="space-y-3 max-w-sm">
      <?= csrfCampo() ?>
      <div>
        <label class="block text-sm font-medium mb-1">Password attuale</label>
        <input name="password_attuale" type="password" required class="w-full border rounded px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Nuova password (minimo 10 caratteri)</label>
        <input name="password_nuova" type="password" minlength="10" required class="w-full border rounded px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Conferma nuova password</label>
        <input name="password_conferma" type="password" minlength="10" required class="w-full border rounded px-3 py-2 text-sm">
      </div>
      <button type="submit" class="bg-blue-800 text-white rounded px-4 py-2 text-sm font-medium">Aggiorna password</button>
    </form>
  </section>

  <section class="bg-white rounded-lg shadow-sm p-5">
    <h2 class="text-sm font-semibold uppercase text-gray-500 mb-3">Ultimi accessi</h2>
    <?php if (!$accessi): ?>
      <p class="text-sm text-gray-500">Nessun accesso registrato.</p>
    <?php else: ?>
      <table class="w-full text-sm">
        <thead class="text-left text-gray-500">
          <tr><th class="py-1">Data</th><th class="py-1">Esito</th><th class="py-1">IP</th></tr>
        </thead>
        <tbody class="divide-y">
          <?php foreach ($accessi as $a): ?>
            <tr>
              <td class="py-1"><?= h($a['creato_il']) ?></td>
              <td class="py-1"><?= $a['esito'] === 'successo' ? '<span class="text-green-700">Riuscito</span>' : '<span class="text-red-700">Fallito</span>' ?></td>
              <td class="py-1 text-gray-500"><?= h($a['ip'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</main>
</body>
</html>
