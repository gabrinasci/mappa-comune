<?php
require_once __DIR__ . '/../../app/lib/auth.php';
require_once __DIR__ . '/../../app/lib/helpers.php';
require_once __DIR__ . '/../../app/lib/impostazioni.php';
require_once __DIR__ . '/../../app/lib/mailer.php';

$utente = richiediLogin();
if (!isSuperadmin($utente)) {
    http_response_code(403);
    exit('Solo il superadmin può gestire le impostazioni SMTP.');
}

$esitoProva = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerifica();
    $azione = $_POST['azione'] ?? 'salva';

    if ($azione === 'salva') {
        salvaImpostazione('smtp_host', trim($_POST['smtp_host'] ?? ''));
        salvaImpostazione('smtp_porta', trim($_POST['smtp_porta'] ?? '587'));
        salvaImpostazione('smtp_utente', trim($_POST['smtp_utente'] ?? ''));
        if (trim($_POST['smtp_password'] ?? '') !== '') {
            salvaImpostazione('smtp_password', $_POST['smtp_password']);
        }
        salvaImpostazione('smtp_sicurezza', $_POST['smtp_sicurezza'] ?? 'tls');
        salvaImpostazione('smtp_mittente_email', trim($_POST['smtp_mittente_email'] ?? ''));
        salvaImpostazione('smtp_mittente_nome', trim($_POST['smtp_mittente_nome'] ?? ''));
        flash('successo', 'Impostazioni SMTP salvate.');
        redirect('/admin/impostazioni_smtp.php');
    }

    if ($azione === 'prova') {
        $esitoProva = inviaEmail($utente['email'], $utente['nome'], 'Email di prova — Mappa Servizi', '<p>Se leggi questa email, la configurazione SMTP funziona correttamente.</p>');
    }
}

$valori = [
    'smtp_host' => impostazione('smtp_host', ''),
    'smtp_porta' => impostazione('smtp_porta', '587'),
    'smtp_utente' => impostazione('smtp_utente', ''),
    'smtp_sicurezza' => impostazione('smtp_sicurezza', 'tls'),
    'smtp_mittente_email' => impostazione('smtp_mittente_email', ''),
    'smtp_mittente_nome' => impostazione('smtp_mittente_nome', 'Mappa Servizi Comunali'),
];
$passwordGiaImpostata = impostazione('smtp_password') !== null;
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Impostazioni SMTP — Mappa Servizi</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="max-w-xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-4">Impostazioni SMTP</h1>
  <p class="text-sm text-gray-600 mb-4">Configurazione del server usato per inviare le email (reset password, creazione utenti). Puoi usare l'SMTP fornito dal tuo hosting, oppure un servizio esterno (Gmail, SendGrid, ecc.).</p>

  <?php if ($msg = flash('successo')): ?>
    <p class="bg-green-100 text-green-800 text-sm px-3 py-2 rounded mb-4"><?= h($msg) ?></p>
  <?php endif; ?>
  <?php if ($esitoProva): ?>
    <p class="<?= $esitoProva['ok'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> text-sm px-3 py-2 rounded mb-4">
      <?= $esitoProva['ok'] ? 'Email di prova inviata con successo a ' . h($utente['email']) . '.' : 'Invio fallito: ' . h($esitoProva['errore']) ?>
    </p>
  <?php endif; ?>

  <form method="post" class="bg-white rounded-lg shadow-sm p-5 space-y-4">
    <?= csrfCampo() ?>
    <input type="hidden" name="azione" value="salva">
    <div class="grid sm:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">Host SMTP</label>
        <input name="smtp_host" value="<?= h($valori['smtp_host']) ?>" placeholder="mail.tuodominio.it" class="w-full border rounded px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Porta</label>
        <input name="smtp_porta" value="<?= h($valori['smtp_porta']) ?>" class="w-full border rounded px-3 py-2 text-sm">
      </div>
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Sicurezza</label>
      <select name="smtp_sicurezza" class="w-full border rounded px-3 py-2 text-sm">
        <option value="tls" <?= $valori['smtp_sicurezza'] === 'tls' ? 'selected' : '' ?>>STARTTLS (porta tipica 587)</option>
        <option value="ssl" <?= $valori['smtp_sicurezza'] === 'ssl' ? 'selected' : '' ?>>SSL/TLS implicito (porta tipica 465)</option>
        <option value="nessuna" <?= $valori['smtp_sicurezza'] === 'nessuna' ? 'selected' : '' ?>>Nessuna (sconsigliato)</option>
      </select>
    </div>
    <div class="grid sm:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">Utente SMTP</label>
        <input name="smtp_utente" value="<?= h($valori['smtp_utente']) ?>" class="w-full border rounded px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Password SMTP</label>
        <input name="smtp_password" type="password" placeholder="<?= $passwordGiaImpostata ? '•••••••• (lascia vuoto per non cambiarla)' : '' ?>" class="w-full border rounded px-3 py-2 text-sm">
      </div>
    </div>
    <div class="grid sm:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">Email mittente</label>
        <input name="smtp_mittente_email" type="email" value="<?= h($valori['smtp_mittente_email']) ?>" placeholder="noreply@tuodominio.it" class="w-full border rounded px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Nome mittente</label>
        <input name="smtp_mittente_nome" value="<?= h($valori['smtp_mittente_nome']) ?>" class="w-full border rounded px-3 py-2 text-sm">
      </div>
    </div>
    <button type="submit" class="bg-blue-800 text-white rounded px-4 py-2 text-sm font-medium">Salva impostazioni</button>
  </form>

  <form method="post" class="mt-4">
    <?= csrfCampo() ?>
    <input type="hidden" name="azione" value="prova">
    <button type="submit" class="border rounded px-4 py-2 text-sm font-medium">Invia email di prova a me stesso</button>
  </form>
</main>
</body>
</html>
