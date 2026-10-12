<?php
require_once __DIR__ . '/../../app/lib/auth.php';
require_once __DIR__ . '/../../app/lib/helpers.php';
require_once __DIR__ . '/../../app/lib/mappa.php';

$utente = richiediLogin();
if (!isSuperadmin($utente)) {
    http_response_code(403);
    exit('Solo il superadmin può gestire le impostazioni della mappa.');
}

$errore = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerifica();
    $fornitore = (string) ($_POST['mappa_fornitore'] ?? '');
    $token = trim((string) ($_POST['mapbox_token'] ?? ''));

    if (!array_key_exists($fornitore, FORNITORI_MAPPA)) {
        $errore = 'Fornitore della mappa non valido.';
    } elseif ($token !== '' && !preg_match('/^pk\.[A-Za-z0-9._-]+$/', $token)) {
        // Il token finisce nel codice della pagina pubblica: deve essere un token pubblico (pk.), mai uno segreto (sk.).
        $errore = 'Il token Mapbox deve essere un token pubblico (inizia con "pk.").';
    } else {
        salvaImpostazione('mappa_fornitore', $fornitore);
        salvaImpostazione('mapbox_token', $token);
        flash('successo', 'Impostazioni della mappa salvate.');
        redirect('/admin/impostazioni_mappa.php');
    }
}

$fornitoreAttuale = $_POST['mappa_fornitore'] ?? impostazione('mappa_fornitore', 'mapbox');
$tokenAttuale = $_POST['mapbox_token'] ?? impostazione('mapbox_token', '');
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Impostazioni mappa — Mappa Servizi</title>
<?= stiliComuni() ?>
</head>
<body class="bg-white text-gray-900 min-h-screen">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="max-w-3xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-2">Impostazioni mappa</h1>
  <p class="text-sm text-gray-600 mb-6">Sceglie la mappa di sfondo della pagina pubblica. I punti, i filtri e i confini restano gli stessi con entrambi i fornitori.</p>

  <?php if ($msg = flash('successo')): ?>
    <p class="bg-green-100 text-green-800 text-sm px-3 py-2 rounded mb-4"><?= h($msg) ?></p>
  <?php endif; ?>
  <?php if ($errore): ?>
    <p class="bg-red-100 text-red-800 text-sm px-3 py-2 rounded mb-4"><?= h($errore) ?></p>
  <?php endif; ?>

  <form method="post" class="bg-white rounded-lg shadow-sm p-5 space-y-5">
    <?= csrfCampo() ?>
    <fieldset>
      <legend class="block text-sm font-medium mb-2">Fornitore della mappa</legend>
      <div class="space-y-3">
        <?php foreach (FORNITORI_MAPPA as $chiave => $f): ?>
          <label class="flex items-start gap-3 border rounded-lg p-3 cursor-pointer has-[:checked]:border-primary has-[:checked]:bg-blue-50">
            <input type="radio" name="mappa_fornitore" value="<?= h($chiave) ?>" class="mt-1" <?= $fornitoreAttuale === $chiave ? 'checked' : '' ?>>
            <span>
              <span class="block text-sm font-semibold"><?= h($f['etichetta']) ?></span>
              <span class="block text-sm text-gray-600"><?= h($f['descrizione']) ?></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <div>
      <label for="mapbox_token" class="block text-sm font-medium mb-1">Token Mapbox (facoltativo)</label>
      <input id="mapbox_token" name="mapbox_token" value="<?= h($tokenAttuale) ?>" placeholder="pk.…" autocomplete="off" spellcheck="false" class="w-full border rounded px-3 py-2 text-sm font-mono">
      <p class="text-xs text-gray-500 mt-1">Usato solo con Mapbox. Lasciandolo vuoto si usa il token attuale del progetto. Inserire solo token pubblici (pk.) e limitarli all'indirizzo del sito dal pannello Mapbox.</p>
    </div>

    <p class="text-xs text-gray-500">Su entrambe le mappe i visitatori possono attivare dal pannello Filtri l'opzione per nascondere negozi e punti di interesse.</p>

    <button type="submit" class="bg-primary text-white rounded px-4 py-2 text-sm font-medium">Salva</button>
  </form>
</main>
</body>
</html>
