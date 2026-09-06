<?php
require_once __DIR__ . '/../../app/lib/auth.php';
require_once __DIR__ . '/../../app/lib/helpers.php';

$utente = richiediLogin();
$ambitoComuneId = comuneAmbito($utente);
$pdo = db();

const NOMI_GIORNI_ADMIN = [1 => 'Lunedì', 2 => 'Martedì', 3 => 'Mercoledì', 4 => 'Giovedì', 5 => 'Venerdì', 6 => 'Sabato', 7 => 'Domenica'];

function puntoInAmbito(PDO $pdo, int $id, ?int $ambitoComuneId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM punti_servizio WHERE id = ?');
    $stmt->execute([$id]);
    $punto = $stmt->fetch();
    if (!$punto) return null;
    if ($ambitoComuneId !== null && (int) $punto['comune_id'] !== $ambitoComuneId) return null;
    return $punto;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerifica();

    if (($_POST['azione'] ?? '') === 'elimina') {
        $id = (int) $_POST['id'];
        if (puntoInAmbito($pdo, $id, $ambitoComuneId)) {
            $pdo->prepare('DELETE FROM punti_servizio WHERE id = ?')->execute([$id]);
            flash('successo', 'Punto servizio eliminato.');
        }
        redirect('/admin/punti.php');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $comuneId = $ambitoComuneId ?? (int) $_POST['comune_id'];
    $dati = [
        'comune_id' => $comuneId,
        'categoria_id' => (int) $_POST['categoria_id'],
        'nome' => trim($_POST['nome']),
        'indirizzo' => trim($_POST['indirizzo']),
        'lat' => (float) str_replace(',', '.', $_POST['lat']),
        'lng' => (float) str_replace(',', '.', $_POST['lng']),
        'descrizione' => trim($_POST['descrizione']) ?: null,
        'telefono' => trim($_POST['telefono']) ?: null,
        'email' => trim($_POST['email']) ?: null,
        'sito_web' => trim($_POST['sito_web']) ?: null,
    ];

    $erroriForm = [];
    if ($dati['nome'] === '') $erroriForm[] = 'Il nome è obbligatorio.';
    if ($dati['indirizzo'] === '') $erroriForm[] = 'L\'indirizzo è obbligatorio.';
    if (!$dati['lat'] || !$dati['lng']) $erroriForm[] = 'Coordinate lat/lng non valide.';
    if ($ambitoComuneId !== null && $comuneId !== $ambitoComuneId) $erroriForm[] = 'Non hai i permessi su questo comune.';

    if (!$erroriForm) {
        if ($id && puntoInAmbito($pdo, $id, $ambitoComuneId)) {
            $stmt = $pdo->prepare('UPDATE punti_servizio SET comune_id=?, categoria_id=?, nome=?, indirizzo=?, lat=?, lng=?, descrizione=?, telefono=?, email=?, sito_web=? WHERE id=?');
            $stmt->execute([...array_values($dati), $id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO punti_servizio (comune_id, categoria_id, nome, indirizzo, lat, lng, descrizione, telefono, email, sito_web, creato_da) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([...array_values($dati), $utente['id']]);
            $id = (int) $pdo->lastInsertId();
        }

        $pdo->prepare('DELETE FROM orari_apertura WHERE punto_id = ?')->execute([$id]);
        $inserisciOrario = $pdo->prepare('INSERT INTO orari_apertura (punto_id, giorno_settimana, apertura, chiusura, chiuso) VALUES (?,?,?,?,?)');
        foreach (NOMI_GIORNI_ADMIN as $giorno => $nome) {
            $chiuso = !empty($_POST["chiuso_$giorno"]);
            $apertura = trim($_POST["apertura_$giorno"] ?? '') ?: null;
            $chiusura = trim($_POST["chiusura_$giorno"] ?? '') ?: null;
            if ($chiuso || (!$apertura && !$chiusura)) {
                $inserisciOrario->execute([$id, $giorno, null, null, 1]);
            } else {
                $inserisciOrario->execute([$id, $giorno, $apertura, $chiusura, 0]);
            }
        }

        flash('successo', 'Punto servizio salvato.');
        redirect('/admin/punti.php');
    }
}

$comuni = $pdo->query('SELECT * FROM comuni ORDER BY nome')->fetchAll();
$categorie = $pdo->query('SELECT * FROM categorie ORDER BY ordine')->fetchAll();

$azione = $_GET['azione'] ?? 'elenco';
$puntoModifica = null;
$orariModifica = [];
if ($azione === 'modifica' && !empty($_GET['id'])) {
    $puntoModifica = puntoInAmbito($pdo, (int) $_GET['id'], $ambitoComuneId);
    if ($puntoModifica) {
        $stmt = $pdo->prepare('SELECT * FROM orari_apertura WHERE punto_id = ?');
        $stmt->execute([$puntoModifica['id']]);
        foreach ($stmt->fetchAll() as $o) {
            $orariModifica[(int) $o['giorno_settimana']] = $o;
        }
    }
}
$mostraForm = $azione === 'nuovo' || $puntoModifica;

$condizione = $ambitoComuneId !== null ? 'WHERE p.comune_id = ?' : '';
$parametri = $ambitoComuneId !== null ? [$ambitoComuneId] : [];
$stmt = $pdo->prepare("SELECT p.*, c.nome AS categoria_nome, co.nome AS comune_nome FROM punti_servizio p
                        JOIN categorie c ON c.id = p.categoria_id
                        JOIN comuni co ON co.id = p.comune_id
                        $condizione ORDER BY p.nome");
$stmt->execute($parametri);
$elenco = $stmt->fetchAll();
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Punti servizio — Mappa Servizi</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="max-w-5xl mx-auto px-4 py-8">

  <?php if ($msg = flash('successo')): ?>
    <p class="bg-green-100 text-green-800 text-sm px-3 py-2 rounded mb-4"><?= h($msg) ?></p>
  <?php endif; ?>
  <?php if (!empty($erroriForm)): ?>
    <p class="bg-red-100 text-red-800 text-sm px-3 py-2 rounded mb-4"><?= h(implode(' ', $erroriForm)) ?></p>
  <?php endif; ?>

  <?php if ($mostraForm): ?>
    <h1 class="text-xl font-semibold mb-4"><?= $puntoModifica ? 'Modifica punto servizio' : 'Nuovo punto servizio' ?></h1>
    <form method="post" class="bg-white rounded-lg shadow-sm p-5 space-y-4 max-w-2xl">
      <?= csrfCampo() ?>
      <input type="hidden" name="id" value="<?= (int) ($puntoModifica['id'] ?? 0) ?>">

      <div class="grid sm:grid-cols-2 gap-4">
        <?php if ($ambitoComuneId === null): ?>
          <div>
            <label class="block text-sm font-medium mb-1">Comune</label>
            <select name="comune_id" required class="w-full border rounded px-3 py-2 text-sm">
              <?php foreach ($comuni as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($puntoModifica['comune_id'] ?? null) == $c['id'] ? 'selected' : '' ?>><?= h($c['nome']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <div>
          <label class="block text-sm font-medium mb-1">Categoria</label>
          <select name="categoria_id" required class="w-full border rounded px-3 py-2 text-sm">
            <?php foreach ($categorie as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($puntoModifica['categoria_id'] ?? null) == $c['id'] ? 'selected' : '' ?>><?= h($c['nome']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Nome</label>
        <input name="nome" required value="<?= h($puntoModifica['nome'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Indirizzo</label>
        <input name="indirizzo" required value="<?= h($puntoModifica['indirizzo'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
      </div>
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Latitudine</label>
          <input name="lat" required value="<?= h($puntoModifica['lat'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Longitudine</label>
          <input name="lng" required value="<?= h($puntoModifica['lng'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
        </div>
      </div>
      <p class="text-xs text-gray-500 -mt-2">Suggerimento: cerca l'indirizzo su
        <a class="underline" href="https://www.openstreetmap.org" target="_blank" rel="noopener">openstreetmap.org</a>,
        clic destro sul punto → "Mostra indirizzo" per leggere lat/lng.</p>

      <div>
        <label class="block text-sm font-medium mb-1">Descrizione</label>
        <textarea name="descrizione" rows="3" class="w-full border rounded px-3 py-2 text-sm"><?= h($puntoModifica['descrizione'] ?? '') ?></textarea>
      </div>
      <div class="grid sm:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Telefono</label>
          <input name="telefono" value="<?= h($puntoModifica['telefono'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Email</label>
          <input name="email" type="email" value="<?= h($puntoModifica['email'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Sito web</label>
          <input name="sito_web" value="<?= h($puntoModifica['sito_web'] ?? '') ?>" class="w-full border rounded px-3 py-2 text-sm">
        </div>
      </div>

      <fieldset class="border rounded p-3">
        <legend class="text-sm font-medium px-1">Orari di apertura</legend>
        <div class="space-y-1">
          <?php foreach (NOMI_GIORNI_ADMIN as $giorno => $nomeGiorno): $o = $orariModifica[$giorno] ?? null; ?>
            <div class="flex items-center gap-2 text-sm">
              <span class="w-24 shrink-0"><?= h($nomeGiorno) ?></span>
              <input type="time" name="apertura_<?= $giorno ?>" value="<?= h($o['apertura'] ?? '') ?>" class="border rounded px-2 py-1">
              <span>–</span>
              <input type="time" name="chiusura_<?= $giorno ?>" value="<?= h($o['chiusura'] ?? '') ?>" class="border rounded px-2 py-1">
              <label class="flex items-center gap-1 ml-2">
                <input type="checkbox" name="chiuso_<?= $giorno ?>" <?= ($o['chiuso'] ?? false) ? 'checked' : '' ?>> chiuso
              </label>
            </div>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <div class="flex gap-2">
        <button type="submit" class="bg-blue-800 text-white rounded px-4 py-2 text-sm font-medium">Salva</button>
        <a href="/admin/punti.php" class="border rounded px-4 py-2 text-sm font-medium">Annulla</a>
      </div>
    </form>

  <?php else: ?>
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-xl font-semibold">Punti servizio</h1>
      <a href="/admin/punti.php?azione=nuovo" class="bg-blue-800 text-white rounded px-4 py-2 text-sm font-medium">+ Nuovo</a>
    </div>
    <table class="w-full text-sm bg-white rounded-lg shadow-sm overflow-hidden">
      <thead class="bg-gray-100 text-left">
        <tr><th class="px-3 py-2">Nome</th><th class="px-3 py-2">Categoria</th><th class="px-3 py-2">Comune</th><th class="px-3 py-2"></th></tr>
      </thead>
      <tbody class="divide-y">
        <?php foreach ($elenco as $p): ?>
          <tr>
            <td class="px-3 py-2 font-medium"><?= h($p['nome']) ?></td>
            <td class="px-3 py-2"><?= h($p['categoria_nome']) ?></td>
            <td class="px-3 py-2"><?= h($p['comune_nome']) ?></td>
            <td class="px-3 py-2 text-right space-x-2">
              <a href="/admin/punti.php?azione=modifica&id=<?= $p['id'] ?>" class="text-blue-800 underline">Modifica</a>
              <form method="post" class="inline" onsubmit="return confirm('Eliminare questo punto servizio?');">
                <?= csrfCampo() ?>
                <input type="hidden" name="azione" value="elimina">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button type="submit" class="text-red-700 underline">Elimina</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</main>
</body>
</html>
