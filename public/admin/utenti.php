<?php
require_once __DIR__ . '/../../app/lib/auth.php';
require_once __DIR__ . '/../../app/lib/helpers.php';
require_once __DIR__ . '/../../app/lib/mailer.php';

$utenteCorrente = richiediLogin();
$utente = $utenteCorrente; // usata da _nav.php
if (!isSuperadmin($utenteCorrente)) {
    http_response_code(403);
    exit('Solo il superadmin può gestire gli utenti.');
}
$pdo = db();

function linkResetPer(int $utenteId, string $nome, string $email): array
{
    $token = generaTokenReset($utenteId);
    $base = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
    $link = $base . '/admin/reimposta_password.php?token=' . urlencode($token);
    $nomeSicuro = h($nome);
    $linkSicuro = h($link);
    $esito = inviaEmail($email, $nome, 'Imposta la tua password — Mappa Servizi', <<<HTML
        <p>Ciao {$nomeSicuro},</p>
        <p>È stato creato un account per te sul pannello Mappa Servizi Comunali.</p>
        <p><a href="{$linkSicuro}">Clicca qui per impostare la tua password</a> (il link scade tra 1 ora).</p>
        HTML);
    return ['link' => $link, 'inviata' => $esito['ok'], 'errore' => $esito['errore']];
}

$linkGenerato = null;
$erroreForm = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerifica();
    $azione = $_POST['azione'] ?? '';

    if ($azione === 'toggle_attivo') {
        $id = (int) $_POST['id'];
        if ($id !== $utenteCorrente['id']) {
            $pdo->prepare('UPDATE utenti SET attivo = NOT attivo WHERE id = ?')->execute([$id]);
        }
        redirect('/admin/utenti.php');
    }

    if ($azione === 'invia_link') {
        $id = (int) $_POST['id'];
        $stmt = $pdo->prepare('SELECT nome, email FROM utenti WHERE id = ?');
        $stmt->execute([$id]);
        $u = $stmt->fetch();
        if ($u) {
            $linkGenerato = linkResetPer($id, $u['nome'], $u['email']);
        }
    }

    if ($azione === 'salva') {
        $id = (int) ($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $ruolo = $_POST['ruolo'] === 'superadmin' ? 'superadmin' : 'admin_comune';
        $comuneId = $ruolo === 'admin_comune' ? (int) ($_POST['comune_id'] ?? 0) ?: null : null;

        if ($nome === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erroreForm = 'Nome ed email (valida) sono obbligatori.';
        } elseif ($ruolo === 'admin_comune' && !$comuneId) {
            $erroreForm = 'Seleziona un comune per un account admin_comune.';
        } else {
            if ($id) {
                $pdo->prepare('UPDATE utenti SET nome = ?, email = ?, ruolo = ?, comune_id = ? WHERE id = ?')
                    ->execute([$nome, $email, $ruolo, $comuneId, $id]);
                flash('successo', 'Utente aggiornato.');
                redirect('/admin/utenti.php');
            } else {
                try {
                    $passwordPlaceholder = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                    $pdo->prepare('INSERT INTO utenti (comune_id, nome, email, password_hash, ruolo) VALUES (?,?,?,?,?)')
                        ->execute([$comuneId, $nome, $email, $passwordPlaceholder, $ruolo]);
                    $nuovoId = (int) $pdo->lastInsertId();
                    $linkGenerato = linkResetPer($nuovoId, $nome, $email);
                } catch (PDOException $e) {
                    $erroreForm = str_contains($e->getMessage(), 'Duplicate') ? 'Esiste già un utente con questa email.' : 'Errore durante la creazione.';
                }
            }
        }
    }
}

$comuni = $pdo->query('SELECT * FROM comuni ORDER BY nome')->fetchAll();

$azione = $_GET['azione'] ?? 'elenco';
$utenteModifica = null;
if ($azione === 'modifica' && !empty($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM utenti WHERE id = ?');
    $stmt->execute([(int) $_GET['id']]);
    $utenteModifica = $stmt->fetch() ?: null;
}
$mostraForm = $azione === 'nuovo' || $utenteModifica;

$elenco = $pdo->query(
    "SELECT u.*, co.nome AS comune_nome FROM utenti u
     LEFT JOIN comuni co ON co.id = u.comune_id
     ORDER BY u.nome"
)->fetchAll();
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Utenti — Mappa Servizi</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="max-w-4xl mx-auto px-4 py-8">
  <?php if ($msg = flash('successo')): ?>
    <p class="bg-green-100 text-green-800 text-sm px-3 py-2 rounded mb-4"><?= h($msg) ?></p>
  <?php endif; ?>
  <?php if ($erroreForm): ?>
    <p class="bg-red-100 text-red-800 text-sm px-3 py-2 rounded mb-4"><?= h($erroreForm) ?></p>
  <?php endif; ?>
  <?php if ($linkGenerato): ?>
    <div class="rounded mb-4 px-3 py-3 text-sm <?= $linkGenerato['inviata'] ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-900' ?>">
      <?php if ($linkGenerato['inviata']): ?>
        Email inviata con il link per impostare la password.
      <?php else: ?>
        <p class="mb-1">Impossibile inviare l'email (<?= h($linkGenerato['errore'] ?? 'SMTP non configurato') ?>).
          Copia e invia tu questo link all'utente (valido 1 ora):</p>
        <code class="block bg-white/60 px-2 py-1 rounded break-all"><?= h($linkGenerato['link']) ?></code>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($mostraForm): ?>
    <h1 class="text-xl font-semibold mb-4"><?= $utenteModifica ? 'Modifica utente' : 'Nuovo utente' ?></h1>
    <form method="post" class="bg-white rounded-lg shadow-sm p-5 space-y-4 max-w-md">
      <?= csrfCampo() ?>
      <input type="hidden" name="azione" value="salva">
      <input type="hidden" name="id" value="<?= (int) ($utenteModifica['id'] ?? 0) ?>">
      <div>
        <label class="block text-sm font-medium mb-1">Nome</label>
        <input name="nome" required value="<?= h($utenteModifica['nome'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Email</label>
        <input name="email" type="email" required value="<?= h($utenteModifica['email'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Ruolo</label>
        <select name="ruolo" class="w-full border rounded px-3 py-2 text-sm">
          <option value="admin_comune" <?= ($utenteModifica['ruolo'] ?? '') === 'admin_comune' ? 'selected' : '' ?>>Admin comune</option>
          <option value="superadmin" <?= ($utenteModifica['ruolo'] ?? '') === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Comune (solo per Admin comune)</label>
        <select name="comune_id" class="w-full border rounded px-3 py-2 text-sm">
          <option value="">—</option>
          <?php foreach ($comuni as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($utenteModifica['comune_id'] ?? null) == $c['id'] ? 'selected' : '' ?>><?= h($c['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if (!$utenteModifica): ?>
        <p class="text-xs text-gray-500">Non serve impostare una password: all'utente arriverà un'email per sceglierne una propria.</p>
      <?php endif; ?>
      <div class="flex gap-2">
        <button type="submit" class="bg-blue-800 text-white rounded px-4 py-2 text-sm font-medium">Salva</button>
        <a href="/admin/utenti.php" class="border rounded px-4 py-2 text-sm font-medium">Annulla</a>
      </div>
    </form>

  <?php else: ?>
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-semibold">Utenti</h1>
      <a href="/admin/utenti.php?azione=nuovo" class="bg-blue-800 text-white rounded px-4 py-2 text-sm font-medium">+ Nuovo utente</a>
    </div>
    <table class="w-full text-sm bg-white rounded-lg shadow-sm overflow-hidden">
      <thead class="bg-gray-100 text-left">
        <tr><th class="px-3 py-2">Nome</th><th class="px-3 py-2">Email</th><th class="px-3 py-2">Ruolo</th><th class="px-3 py-2">Comune</th><th class="px-3 py-2">Ultimo accesso</th><th class="px-3 py-2">Stato</th><th class="px-3 py-2"></th></tr>
      </thead>
      <tbody class="divide-y">
        <?php foreach ($elenco as $u): ?>
          <tr class="<?= $u['attivo'] ? '' : 'opacity-50' ?>">
            <td class="px-3 py-2 font-medium"><?= h($u['nome']) ?></td>
            <td class="px-3 py-2"><?= h($u['email']) ?></td>
            <td class="px-3 py-2"><?= $u['ruolo'] === 'superadmin' ? 'Superadmin' : 'Admin comune' ?></td>
            <td class="px-3 py-2"><?= h($u['comune_nome'] ?? '—') ?></td>
            <td class="px-3 py-2 text-gray-500"><?= h($u['ultimo_accesso'] ?? 'mai') ?></td>
            <td class="px-3 py-2"><?= $u['attivo'] ? '<span class="text-green-700">Attivo</span>' : '<span class="text-gray-500">Disattivato</span>' ?></td>
            <td class="px-3 py-2 text-right space-x-2 whitespace-nowrap">
              <a href="/admin/utenti.php?azione=modifica&id=<?= $u['id'] ?>" class="text-blue-800 underline">Modifica</a>
              <form method="post" class="inline">
                <?= csrfCampo() ?>
                <input type="hidden" name="azione" value="invia_link">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button type="submit" class="text-blue-800 underline">Link reset</button>
              </form>
              <?php if ($u['id'] !== $utenteCorrente['id']): ?>
                <form method="post" class="inline">
                  <?= csrfCampo() ?>
                  <input type="hidden" name="azione" value="toggle_attivo">
                  <input type="hidden" name="id" value="<?= $u['id'] ?>">
                  <button type="submit" class="text-red-700 underline"><?= $u['attivo'] ? 'Disattiva' : 'Riattiva' ?></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</main>
</body>
</html>
