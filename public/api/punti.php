<?php
require_once __DIR__ . '/../../app/config/db.php';
require_once __DIR__ . '/../../app/lib/foto.php';

header('Content-Type: application/json; charset=utf-8');
// Il browser riverifica sempre (le modifiche dall'admin si vedono subito), ma se i dati non sono
// cambiati riceve una risposta vuota (304) invece di riscaricare tutto.
header('Cache-Control: no-cache');

$pdo = db();

$comuni = $pdo->query('SELECT id, nome, slug FROM comuni ORDER BY nome')->fetchAll();
$categorie = $pdo->query('SELECT id, nome, colore_hex, icona FROM categorie ORDER BY ordine')->fetchAll();

$punti = $pdo->query(
    'SELECT p.id, p.comune_id, p.categoria_id, p.nome, p.indirizzo, p.lat, p.lng,
            p.orario_testo, p.sovracomunale,
            p.descrizione, p.telefono, p.email, p.sito_web, p.immagine_url,
            c.nome AS categoria_nome, c.colore_hex, c.icona AS categoria_icona,
            co.nome AS comune_nome
     FROM punti_servizio p
     JOIN categorie c ON c.id = p.categoria_id
     JOIN comuni co ON co.id = p.comune_id
     ORDER BY p.nome'
)->fetchAll();

$orariPerPunto = [];
foreach ($pdo->query('SELECT punto_id, giorno_settimana, apertura, chiusura, chiuso FROM orari_apertura ORDER BY giorno_settimana') as $orario) {
    $orariPerPunto[$orario['punto_id']][] = [
        'giorno' => (int) $orario['giorno_settimana'],
        'apertura' => $orario['apertura'],
        'chiusura' => $orario['chiusura'],
        'chiuso' => (bool) $orario['chiuso'],
    ];
}

$fotoPerPunto = fotoPerPunto();

foreach ($punti as &$punto) {
    $punto['lat'] = (float) $punto['lat'];
    $punto['lng'] = (float) $punto['lng'];
    $punto['sovracomunale'] = (bool) $punto['sovracomunale'];
    $punto['orari'] = $orariPerPunto[$punto['id']] ?? [];
    $punto['foto'] = $fotoPerPunto[$punto['id']] ?? [];
}
unset($punto);

$json = json_encode([
    'comuni' => $comuni,
    'categorie' => $categorie,
    'punti' => $punti,
], JSON_UNESCAPED_UNICODE);
$etag = '"' . md5($json) . '"';
header('ETag: ' . $etag);
// Con la compressione di Apache l'ETag può tornare con il suffisso -gzip.
if (str_replace('-gzip', '', trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}
echo $json;
