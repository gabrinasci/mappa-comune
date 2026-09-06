<?php
require_once __DIR__ . '/../../app/lib/auth.php';
require_once __DIR__ . '/../../app/lib/helpers.php';
require_once __DIR__ . '/../../app/lib/csv_import.php';

$utente = richiediLogin();
$anteprima = null;
$risultatoFinale = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerifica();
    $azione = $_POST['azione'] ?? '';

    if ($azione === 'carica' && !empty($_FILES['csv']['tmp_name'])) {
        $risultato = anteprimaImportCsv($_FILES['csv']['tmp_name'], $utente);
        if ($risultato['errore_generale']) {
            flash('errore', $risultato['errore_generale']);
            redirect('/admin/import.php');
        }
        $_SESSION['import_preview'] = $risultato['righe'];
        $_SESSION['import_nome_file'] = $_FILES['csv']['name'];
        redirect('/admin/import.php');
    }

    if ($azione === 'conferma' && !empty($_SESSION['import_preview'])) {
        $righe = $_SESSION['import_preview'];
        foreach ($righe as $indice => &$riga) {
            $latPost = trim($_POST['lat'][$indice] ?? '');
            $lngPost = trim($_POST['lng'][$indice] ?? '');
            if ($latPost !== '' && $lngPost !== '') {
                $riga['lat'] = (float) str_replace(',', '.', $latPost);
                $riga['lng'] = (float) str_replace(',', '.', $lngPost);
                $riga['errori'] = array_values(array_filter($riga['errori'], fn($e) => !str_contains($e, 'Coordinate')));
            }
        }
        unset($riga);

        $risultatoFinale = confermaImportCsv($righe, $utente);
        db()->prepare('INSERT INTO import_log (utente_id, nome_file, righe_importate, righe_errore, dettagli_errore) VALUES (?,?,?,?,?)')
            ->execute([$utente['id'], $_SESSION['import_nome_file'] ?? 'sconosciuto', $risultatoFinale['importate'], $risultatoFinale['errori'], implode("\n", $risultatoFinale['dettagli_errore'])]);

        unset($_SESSION['import_preview'], $_SESSION['import_nome_file']);
    }
}

if (!$risultatoFinale && !empty($_SESSION['import_preview'])) {
    $anteprima = $_SESSION['import_preview'];
}
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Importa CSV — Mappa Servizi</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="max-w-5xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-4">Importa punti servizio da CSV</h1>

  <?php if ($msg = flash('errore')): ?>
    <p class="bg-red-100 text-red-800 text-sm px-3 py-2 rounded mb-4"><?= h($msg) ?></p>
  <?php endif; ?>

  <?php if ($risultatoFinale): ?>
    <div class="bg-white rounded-lg shadow-sm p-5">
      <p class="text-green-700 font-medium">Importazione completata: <?= $risultatoFinale['importate'] ?> righe importate, <?= $risultatoFinale['errori'] ?> con errori.</p>
      <?php if ($risultatoFinale['dettagli_errore']): ?>
        <ul class="mt-3 text-sm text-red-700 list-disc pl-5">
          <?php foreach ($risultatoFinale['dettagli_errore'] as $d): ?><li><?= h($d) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <a href="/admin/import.php" class="inline-block mt-4 text-blue-800 underline">Nuovo import</a>
    </div>

  <?php elseif ($anteprima): ?>
    <p class="text-sm text-gray-600 mb-3">
      Anteprima di <?= count($anteprima) ?> righe. Le coordinate proposte automaticamente (via geocoding) sono da verificare;
      correggi lat/lng dove necessario, poi conferma. Le righe con errori bloccanti non verranno importate.
    </p>
    <form method="post">
      <?= csrfCampo() ?>
      <input type="hidden" name="azione" value="conferma">
      <div class="overflow-x-auto bg-white rounded-lg shadow-sm">
        <table class="w-full text-sm">
          <thead class="bg-gray-100 text-left">
            <tr>
              <th class="px-3 py-2">Riga</th><th class="px-3 py-2">Nome</th><th class="px-3 py-2">Comune</th>
              <th class="px-3 py-2">Categoria</th><th class="px-3 py-2">Lat</th><th class="px-3 py-2">Lng</th>
              <th class="px-3 py-2">Stato</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <?php foreach ($anteprima as $indice => $riga): ?>
              <tr class="<?= $riga['errori'] ? 'bg-red-50' : '' ?>">
                <td class="px-3 py-2"><?= $riga['numero_riga'] ?></td>
                <td class="px-3 py-2"><?= h($riga['dati']['nome'] ?? '') ?></td>
                <td class="px-3 py-2"><?= h($riga['dati']['comune'] ?? '') ?></td>
                <td class="px-3 py-2"><?= h($riga['dati']['categoria'] ?? '') ?></td>
                <td class="px-3 py-2"><input name="lat[<?= $indice ?>]" value="<?= h((string) ($riga['lat'] ?? '')) ?>" class="w-28 border rounded px-2 py-1"></td>
                <td class="px-3 py-2"><input name="lng[<?= $indice ?>]" value="<?= h((string) ($riga['lng'] ?? '')) ?>" class="w-28 border rounded px-2 py-1"></td>
                <td class="px-3 py-2 text-xs">
                  <?php if ($riga['errori']): ?>
                    <span class="text-red-700"><?= h(implode(' ', $riga['errori'])) ?></span>
                  <?php elseif ($riga['geocodificato']): ?>
                    <span class="text-amber-700">Coordinate da geocoding: verifica</span>
                  <?php else: ?>
                    <span class="text-green-700">OK</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <button type="submit" class="mt-4 bg-blue-800 text-white rounded px-4 py-2 text-sm font-medium">Conferma import</button>
      <a href="/admin/import.php" class="ml-2 text-sm text-gray-600 underline">Annulla</a>
    </form>

  <?php else: ?>
    <div class="bg-white rounded-lg shadow-sm p-5 max-w-xl">
      <p class="text-sm text-gray-600 mb-4">
        Colonne attese (intestazione in prima riga, separatore <code>,</code> o <code>;</code>):<br>
        <code class="text-xs"><?= h(implode(', ', CSV_COLONNE)) ?></code>
      </p>
      <p class="text-sm text-gray-600 mb-4">
        Se <code>lat</code>/<code>lng</code> sono vuote, il sistema prova a calcolarle dall'indirizzo (max
        <?= MAX_GEOCODIFICHE_PER_IMPORT ?> per import): le coordinate proposte andranno comunque verificate
        nell'anteprima prima di confermare.
      </p>
      <form method="post" enctype="multipart/form-data">
        <?= csrfCampo() ?>
        <input type="hidden" name="azione" value="carica">
        <input type="file" name="csv" accept=".csv" required class="block w-full text-sm mb-4">
        <button type="submit" class="bg-blue-800 text-white rounded px-4 py-2 text-sm font-medium">Carica e mostra anteprima</button>
      </form>
    </div>
  <?php endif; ?>
</main>
</body>
</html>
