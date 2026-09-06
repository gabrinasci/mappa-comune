<?php
require_once __DIR__ . '/../../app/lib/auth.php';
require_once __DIR__ . '/../../app/lib/helpers.php';

$utente = richiediLogin();
$ambitoComuneId = comuneAmbito($utente);

$condizione = $ambitoComuneId !== null ? 'WHERE comune_id = ?' : '';
$parametri = $ambitoComuneId !== null ? [$ambitoComuneId] : [];

$stmt = db()->prepare("SELECT co.nome, COUNT(p.id) AS totale FROM comuni co
                        LEFT JOIN punti_servizio p ON p.comune_id = co.id
                        " . ($ambitoComuneId !== null ? 'AND p.comune_id = ?' : '') . "
                        " . ($ambitoComuneId !== null ? 'WHERE co.id = ?' : '') . "
                        GROUP BY co.id, co.nome ORDER BY co.nome");
$stmt->execute($ambitoComuneId !== null ? [$ambitoComuneId, $ambitoComuneId] : []);
$perComune = $stmt->fetchAll();
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard — Mappa Servizi</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">
<?php require __DIR__ . '/_nav.php'; ?>
<main class="max-w-5xl mx-auto px-4 py-8">
  <h1 class="text-xl font-semibold mb-4">Dashboard</h1>
  <div class="grid sm:grid-cols-3 gap-4">
    <?php foreach ($perComune as $riga): ?>
      <div class="bg-white rounded-lg shadow-sm p-4">
        <p class="text-sm text-gray-500"><?= h($riga['nome']) ?></p>
        <p class="text-2xl font-semibold"><?= (int) $riga['totale'] ?></p>
        <p class="text-xs text-gray-400">punti servizio pubblicati</p>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="mt-8 flex gap-3">
    <a href="/admin/punti.php?azione=nuovo" class="bg-blue-800 text-white rounded px-4 py-2 text-sm font-medium">+ Nuovo punto servizio</a>
    <a href="/admin/import.php" class="border rounded px-4 py-2 text-sm font-medium">Importa da CSV</a>
    <a href="/index.php" target="_blank" class="border rounded px-4 py-2 text-sm font-medium">Vedi mappa pubblica ↗</a>
  </div>
</main>
</body>
</html>
