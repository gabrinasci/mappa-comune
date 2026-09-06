<?php
require_once __DIR__ . '/../../app/config/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = db();

$comuni = $pdo->query('SELECT id, nome, slug FROM comuni ORDER BY nome')->fetchAll();
$categorie = $pdo->query('SELECT id, nome, colore_hex, icona FROM categorie ORDER BY ordine')->fetchAll();

$punti = $pdo->query(
    'SELECT p.id, p.comune_id, p.categoria_id, p.nome, p.indirizzo, p.lat, p.lng,
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

foreach ($punti as &$punto) {
    $punto['lat'] = (float) $punto['lat'];
    $punto['lng'] = (float) $punto['lng'];
    $punto['orari'] = $orariPerPunto[$punto['id']] ?? [];
}
unset($punto);

echo json_encode([
    'comuni' => $comuni,
    'categorie' => $categorie,
    'punti' => $punti,
], JSON_UNESCAPED_UNICODE);
