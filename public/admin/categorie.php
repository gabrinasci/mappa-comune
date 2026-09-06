<?php
require_once __DIR__ . '/../../app/lib/auth.php';
require_once __DIR__ . '/../../app/lib/helpers.php';

$utente = richiediLogin();
if (!isSuperadmin($utente)) {
    http_response_code(403);
    exit('Solo il superadmin può gestire le categorie.');
}
$pdo = db();

const ICONE_DISPONIBILI = ['anagrafe', 'tributi', 'sociale', 'istruzione', 'biblioteca'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerifica();

    if (($_POST['azione'] ?? '') === 'elimina') {
        $id = (int) $_POST['id'];
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM punti_servizio WHERE categoria_id = ?');
        $stmt->execute([$id]);
        $inUso = (int) $stmt->fetchColumn();

        if ($inUso > 0) {
            flash('errore', "Impossibile eliminare: $inUso punti servizio usano ancora questa categoria. Riassegnali prima di eliminarla.");
        } else {
            $pdo->prepare('DELETE FROM categorie WHERE id = ?')->execute([$id]);
            flash('successo', 'Categoria eliminata.');
        }
        redirect('/admin/categorie.php');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $colore = trim($_POST['colore_hex'] ?? '#2563eb');
    $icona = in_array($_POST['icona'] ?? '', ICONE_DISPONIBILI, true) ? $_POST['icona'] : 'sociale';
    $ordine = (int) ($_POST['ordine'] ?? 0);

    if ($nome !== '') {
        if ($id) {
            $pdo->prepare('UPDATE categorie SET nome=?, colore_hex=?, icona=?, ordine=? WHERE id=?')->execute([$nome, $colore, $icona, $ordine, $id]);
        } else {
            $pdo->prepare('INSERT INTO categorie (nome, colore_hex, icona, ordine) VALUES (?,?,?,?)')->execute([$nome, $colore, $icona, $ordine]);
        }
        flash('successo', 'Categoria salvata.');
    }
    redirect('/admin/categorie.php');
}

$categorie = $pdo->query('SELECT * FROM categorie ORDER BY ordine')->fetchAll();
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Categorie — Mappa Servizi</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="max-w-3xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-4">Categorie (aree tematiche)</h1>

  <?php if ($msg = flash('successo')): ?>
    <p class="bg-green-100 text-green-800 text-sm px-3 py-2 rounded mb-4"><?= h($msg) ?></p>
  <?php endif; ?>
  <?php if ($msg = flash('errore')): ?>
    <p class="bg-red-100 text-red-800 text-sm px-3 py-2 rounded mb-4"><?= h($msg) ?></p>
  <?php endif; ?>

  <table class="w-full text-sm bg-white rounded-lg shadow-sm overflow-hidden mb-8">
    <thead class="bg-gray-100 text-left">
      <tr><th class="px-3 py-2">Colore</th><th class="px-3 py-2">Nome</th><th class="px-3 py-2">Icona</th><th class="px-3 py-2">Ordine</th><th class="px-3 py-2"></th></tr>
    </thead>
    <tbody class="divide-y">
      <?php foreach ($categorie as $c): ?>
        <tr>
          <td class="px-3 py-2"><span class="inline-block w-5 h-5 rounded-full" style="background:<?= h($c['colore_hex']) ?>"></span></td>
          <td class="px-3 py-2 font-medium"><?= h($c['nome']) ?></td>
          <td class="px-3 py-2"><?= h($c['icona']) ?></td>
          <td class="px-3 py-2"><?= (int) $c['ordine'] ?></td>
          <td class="px-3 py-2 text-right">
            <form method="post" class="inline" onsubmit="return confirm('Eliminare questa categoria? I punti servizio collegati non verranno eliminati automaticamente se il database lo impedisce.');">
              <?= csrfCampo() ?>
              <input type="hidden" name="azione" value="elimina">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button type="submit" class="text-red-700 underline">Elimina</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <h2 class="text-lg font-semibold mb-3">Nuova categoria</h2>
  <form method="post" class="bg-white rounded-lg shadow-sm p-5 space-y-4 max-w-md">
    <?= csrfCampo() ?>
    <div>
      <label class="block text-sm font-medium mb-1">Nome</label>
      <input name="nome" required class="w-full border rounded px-3 py-2 text-sm">
    </div>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">Colore</label>
        <input name="colore_hex" type="color" value="#2563eb" class="w-full h-10 border rounded">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Icona</label>
        <select name="icona" class="w-full border rounded px-3 py-2 text-sm">
          <?php foreach (ICONE_DISPONIBILI as $i): ?>
            <option value="<?= $i ?>"><?= $i ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Ordine di visualizzazione</label>
      <input name="ordine" type="number" value="0" class="w-full border rounded px-3 py-2 text-sm">
    </div>
    <button type="submit" class="bg-blue-800 text-white rounded px-4 py-2 text-sm font-medium">Aggiungi categoria</button>
  </form>
</main>
</body>
</html>
