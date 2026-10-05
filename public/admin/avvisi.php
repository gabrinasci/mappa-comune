<?php
require_once __DIR__ . '/../../app/lib/auth.php';
require_once __DIR__ . '/../../app/lib/helpers.php';
require_once __DIR__ . '/../../app/lib/avvisi.php';

$utente = richiediLogin();
$ambitoComuneId = comuneAmbito($utente);
$pdo = db();

try {
    $pdo->query('SELECT 1 FROM avvisi LIMIT 1');
} catch (PDOException $e) {
    http_response_code(503);
    exit('La tabella degli avvisi non esiste ancora: esegui sql/migrazione_006_avvisi.sql sul database.');
}

/** Un admin di comune gestisce solo gli avvisi del proprio comune; quelli per tutti i comuni restano al superadmin. */
function avvisoInAmbito(PDO $pdo, int $id, ?int $ambitoComuneId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM avvisi WHERE id = ?');
    $stmt->execute([$id]);
    $avviso = $stmt->fetch();
    if (!$avviso) return null;
    if ($ambitoComuneId !== null && (int) $avviso['comune_id'] !== $ambitoComuneId) return null;
    return $avviso;
}

function dataValida(string $data): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $data);
    return $d && $d->format('Y-m-d') === $data;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerifica();

    if (($_POST['azione'] ?? '') === 'elimina') {
        $id = (int) $_POST['id'];
        if (avvisoInAmbito($pdo, $id, $ambitoComuneId)) {
            $pdo->prepare('DELETE FROM avvisi WHERE id = ?')->execute([$id]);
            flash('successo', 'Avviso eliminato.');
        }
        redirect('/admin/avvisi.php');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $comuneId = $ambitoComuneId ?? ((int) ($_POST['comune_id'] ?? 0) ?: null);
    $dati = [
        'comune_id' => $comuneId,
        'tipo' => array_key_exists($_POST['tipo'] ?? '', TIPI_AVVISO) ? $_POST['tipo'] : 'info',
        'testo' => trim($_POST['testo'] ?? ''),
        'link_url' => trim($_POST['link_url'] ?? '') ?: null,
        'data_inizio' => trim($_POST['data_inizio'] ?? '') ?: null,
        'data_fine' => trim($_POST['data_fine'] ?? '') ?: null,
        'attivo' => !empty($_POST['attivo']) ? 1 : 0,
        'ordine' => (int) ($_POST['ordine'] ?? 0),
    ];

    $erroriForm = [];
    if ($dati['testo'] === '') $erroriForm[] = 'Il testo è obbligatorio.';
    if (mb_strlen($dati['testo']) > 255) $erroriForm[] = 'Il testo non può superare 255 caratteri.';
    if ($dati['data_inizio'] !== null && !dataValida($dati['data_inizio'])) $erroriForm[] = 'Data di inizio non valida.';
    if ($dati['data_fine'] !== null && !dataValida($dati['data_fine'])) $erroriForm[] = 'Data di fine non valida.';
    if ($dati['data_inizio'] && $dati['data_fine'] && $dati['data_fine'] < $dati['data_inizio']) $erroriForm[] = 'La data di fine è precedente a quella di inizio.';
    if ($id && !avvisoInAmbito($pdo, $id, $ambitoComuneId)) $erroriForm[] = 'Avviso non trovato o non modificabile.';
    if ($dati['link_url'] !== null && !preg_match('#^https?://#i', $dati['link_url'])) {
        $dati['link_url'] = 'https://' . ltrim($dati['link_url'], '/');
    }
    if ($dati['link_url'] !== null && !filter_var($dati['link_url'], FILTER_VALIDATE_URL)) $erroriForm[] = 'Link non valido.';

    if (!$erroriForm) {
        if ($id) {
            $stmt = $pdo->prepare('UPDATE avvisi SET comune_id=?, tipo=?, testo=?, link_url=?, data_inizio=?, data_fine=?, attivo=?, ordine=? WHERE id=?');
            $stmt->execute([...array_values($dati), $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO avvisi (comune_id, tipo, testo, link_url, data_inizio, data_fine, attivo, ordine, creato_da) VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->execute([...array_values($dati), $utente['id']]);
        }
        flash('successo', 'Avviso salvato.');
        redirect('/admin/avvisi.php');
    }
}

$comuni = $pdo->query('SELECT * FROM comuni ORDER BY nome')->fetchAll();

$azione = $_GET['azione'] ?? 'elenco';
$avvisoModifica = null;
if ($azione === 'modifica' && !empty($_GET['id'])) {
    $avvisoModifica = avvisoInAmbito($pdo, (int) $_GET['id'], $ambitoComuneId);
}
// Dopo un errore di validazione si ripropone quanto inserito, invece di svuotare il form.
if (!empty($erroriForm)) {
    $avvisoModifica = ['id' => $id] + $dati;
}
$mostraForm = $azione === 'nuovo' || $avvisoModifica;

$condizione = $ambitoComuneId !== null ? 'WHERE a.comune_id = ?' : '';
$parametri = $ambitoComuneId !== null ? [$ambitoComuneId] : [];
$stmt = $pdo->prepare("SELECT a.*, co.nome AS comune_nome FROM avvisi a
                        LEFT JOIN comuni co ON co.id = a.comune_id
                        $condizione ORDER BY a.ordine, a.id DESC");
$stmt->execute($parametri);
$elenco = $stmt->fetchAll();

function statoAvviso(array $a): array
{
    $oggi = date('Y-m-d');
    if (!$a['attivo']) return ['Disattivato', 'bg-gray-100 text-gray-700'];
    if ($a['data_inizio'] && $a['data_inizio'] > $oggi) return ['Programmato', 'bg-blue-100 text-blue-800'];
    if ($a['data_fine'] && $a['data_fine'] < $oggi) return ['Scaduto', 'bg-gray-100 text-gray-700'];
    return ['Visibile', 'bg-green-100 text-green-800'];
}

function formatoData(?string $data): string
{
    return $data ? date('d/m/Y', strtotime($data)) : '';
}
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Avvisi — Mappa Servizi</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = { theme: { extend: { fontFamily: { sans: ['"Titillium Web"', 'Arial', 'sans-serif'], serif: ['Lora', 'Georgia', 'serif'] }, colors: { navy: '#00194B', primary: '#0066CC', 'primary-dark': '#003399' } } } };
</script>
<link href="https://fonts.googleapis.com/css2?family=Titillium+Web:wght@400;600;700&family=Lora:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-white text-gray-900 min-h-screen">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="max-w-5xl mx-auto px-4 py-8">

  <?php if ($msg = flash('successo')): ?>
    <p class="bg-green-100 text-green-800 text-sm px-3 py-2 rounded mb-4"><?= h($msg) ?></p>
  <?php endif; ?>
  <?php if (!empty($erroriForm)): ?>
    <p class="bg-red-100 text-red-800 text-sm px-3 py-2 rounded mb-4"><?= h(implode(' ', $erroriForm)) ?></p>
  <?php endif; ?>

  <?php if ($mostraForm): ?>
    <h1 class="text-xl font-semibold mb-4"><?= !empty($avvisoModifica['id']) ? 'Modifica avviso' : 'Nuovo avviso' ?></h1>
    <form method="post" class="bg-white rounded-lg shadow-sm p-5 space-y-4 max-w-2xl">
      <?= csrfCampo() ?>
      <input type="hidden" name="id" value="<?= (int) ($avvisoModifica['id'] ?? 0) ?>">

      <div class="grid sm:grid-cols-2 gap-4">
        <?php if ($ambitoComuneId === null): ?>
          <div>
            <label for="comune_id" class="block text-sm font-medium mb-1">Comune</label>
            <select id="comune_id" name="comune_id" class="w-full border rounded px-3 py-2 text-sm">
              <option value="">Tutti i comuni</option>
              <?php foreach ($comuni as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($avvisoModifica['comune_id'] ?? null) == $c['id'] ? 'selected' : '' ?>><?= h($c['nome']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <div>
          <label for="tipo" class="block text-sm font-medium mb-1">Tipo</label>
          <select id="tipo" name="tipo" class="w-full border rounded px-3 py-2 text-sm">
            <?php foreach (TIPI_AVVISO as $chiave => $t): ?>
              <option value="<?= $chiave ?>" <?= ($avvisoModifica['tipo'] ?? 'info') === $chiave ? 'selected' : '' ?>><?= h($t['etichetta']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div>
        <label for="testo" class="block text-sm font-medium mb-1">Testo</label>
        <input id="testo" name="testo" required maxlength="255" value="<?= h($avvisoModifica['testo'] ?? '') ?>"
               placeholder="Es. Biblioteca chiusa sabato 12 ottobre per inventario" class="w-full border rounded px-3 py-2 text-sm">
        <p class="text-xs text-gray-500 mt-1">Una frase breve: entro circa 100 caratteri resta su una riga anche su schermi piccoli. Il nome del comune, se indicato, compare automaticamente davanti al testo.</p>
      </div>
      <div>
        <label for="link_url" class="block text-sm font-medium mb-1">Link per approfondire (facoltativo)</label>
        <input id="link_url" name="link_url" value="<?= h($avvisoModifica['link_url'] ?? '') ?>" placeholder="https://..." class="w-full border rounded px-3 py-2 text-sm">
      </div>

      <div class="grid sm:grid-cols-3 gap-4">
        <div>
          <label for="data_inizio" class="block text-sm font-medium mb-1">Visibile dal</label>
          <input id="data_inizio" name="data_inizio" type="date" value="<?= h($avvisoModifica['data_inizio'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
          <label for="data_fine" class="block text-sm font-medium mb-1">Visibile fino al</label>
          <input id="data_fine" name="data_fine" type="date" value="<?= h($avvisoModifica['data_fine'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
          <label for="ordine" class="block text-sm font-medium mb-1">Ordine</label>
          <input id="ordine" name="ordine" type="number" value="<?= (int) ($avvisoModifica['ordine'] ?? 0) ?>" class="w-full border rounded px-3 py-2 text-sm">
        </div>
      </div>
      <p class="text-xs text-gray-500 -mt-2">Lasciando vuote le date l'avviso resta visibile finché è attivo. Gli avvisi con ordine più basso compaiono per primi.</p>

      <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="attivo" <?= ($avvisoModifica['attivo'] ?? 1) ? 'checked' : '' ?>>
        Attivo (pubblicato nel banner della home)
      </label>

      <div class="flex gap-2">
        <button type="submit" class="bg-primary text-white rounded px-4 py-2 text-sm font-medium">Salva</button>
        <a href="/admin/avvisi.php" class="border rounded px-4 py-2 text-sm font-medium">Annulla</a>
      </div>
    </form>

  <?php else: ?>
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-semibold">Avvisi in home</h1>
      <a href="/admin/avvisi.php?azione=nuovo" class="bg-primary text-white rounded px-4 py-2 text-sm font-medium">+ Nuovo</a>
    </div>
    <p class="text-sm text-gray-600 mb-4">Comunicazioni brevi (cambi di orario, novità, chiusure) mostrate a scorrimento sopra la mappa.</p>

    <?php if (!$elenco): ?>
      <p class="text-sm text-gray-500">Nessun avviso inserito.</p>
    <?php else: ?>
      <table class="w-full text-sm bg-white rounded-lg shadow-sm overflow-hidden">
        <thead class="bg-gray-100 text-left">
          <tr><th class="px-3 py-2">Testo</th><th class="px-3 py-2">Comune</th><th class="px-3 py-2">Periodo</th><th class="px-3 py-2">Stato</th><th class="px-3 py-2"></th></tr>
        </thead>
        <tbody class="divide-y">
          <?php foreach ($elenco as $a): [$stato, $classeStato] = statoAvviso($a); $tipo = TIPI_AVVISO[$a['tipo']] ?? TIPI_AVVISO['info']; ?>
            <tr>
              <td class="px-3 py-2">
                <span class="flex items-start gap-2">
                  <span class="shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-white" style="background:<?= h($tipo['colore']) ?>" title="<?= h($tipo['etichetta']) ?>"><?= svgIconaAvviso($a['tipo']) ?></span>
                  <span><?= h($a['testo']) ?></span>
                </span>
              </td>
              <td class="px-3 py-2"><?= h($a['comune_nome'] ?? 'Tutti') ?></td>
              <td class="px-3 py-2 whitespace-nowrap">
                <?php if ($a['data_inizio'] || $a['data_fine']): ?>
                  <?= h(formatoData($a['data_inizio']) ?: '…') ?> – <?= h(formatoData($a['data_fine']) ?: '…') ?>
                <?php else: ?>
                  <span class="text-gray-500">Sempre</span>
                <?php endif; ?>
              </td>
              <td class="px-3 py-2"><span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $classeStato ?>"><?= $stato ?></span></td>
              <td class="px-3 py-2 text-right space-x-2 whitespace-nowrap">
                <a href="/admin/avvisi.php?azione=modifica&id=<?= $a['id'] ?>" class="text-blue-800 underline">Modifica</a>
                <form method="post" class="inline" onsubmit="return confirm('Eliminare questo avviso?');">
                  <?= csrfCampo() ?>
                  <input type="hidden" name="azione" value="elimina">
                  <input type="hidden" name="id" value="<?= $a['id'] ?>">
                  <button type="submit" class="text-red-700 underline">Elimina</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  <?php endif; ?>
</main>
</body>
</html>
