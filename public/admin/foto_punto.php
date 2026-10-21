<?php
// Azioni sulle foto di un punto (carica, descrizione, copertina, elimina), richiamate dalla scheda in punti.php.
require_once __DIR__ . '/../../app/lib/auth.php';
require_once __DIR__ . '/../../app/lib/helpers.php';
require_once __DIR__ . '/../../app/lib/foto.php';

$utente = richiediLogin();
$ambitoComuneId = comuneAmbito($utente);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/punti.php');
}
// L'id del punto arriva anche nell'indirizzo del form: serve a tornare alla scheda giusta
// quando l'invio supera post_max_size e PHP scarta tutti i campi (token CSRF compreso).
$puntoId = (int) ($_POST['punto_id'] ?? $_GET['punto_id'] ?? 0);
if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('errore', 'Invio troppo grande per il server: carica meno foto per volta.');
    redirect('/admin/punti.php?azione=modifica&id=' . $puntoId . '#foto');
}
csrfVerifica();

$stmt = $pdo->prepare('SELECT comune_id FROM punti_servizio WHERE id = ?');
$stmt->execute([$puntoId]);
$comunePunto = $stmt->fetchColumn();
if ($comunePunto === false || ($ambitoComuneId !== null && (int) $comunePunto !== $ambitoComuneId)) {
    http_response_code(403);
    exit('Punto servizio non trovato o non modificabile.');
}
$ritorno = '/admin/punti.php?azione=modifica&id=' . $puntoId . '#foto';

/** Foto indicata nel form, solo se appartiene al punto (un id inventato non tocca le foto di altri punti). */
function fotoDelForm(PDO $pdo, int $puntoId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM foto_punti WHERE id = ? AND punto_id = ?');
    $stmt->execute([(int) ($_POST['foto_id'] ?? 0), $puntoId]);
    return $stmt->fetch() ?: null;
}

$azione = (string) ($_POST['azione'] ?? '');

if ($azione === 'carica') {
    $file = $_FILES['foto'] ?? null;
    $alt = trim((string) ($_POST['alt_testo'] ?? '')) ?: null;
    if ($alt !== null) {
        $alt = mb_substr($alt, 0, 255);
    }
    $caricate = 0;
    $errori = [];
    if ($file && is_array($file['name'])) {
        foreach ($file['name'] as $i => $nome) {
            $nomeVisibile = mb_substr((string) $nome, 0, 80);
            if ($file['error'][$i] !== UPLOAD_ERR_OK) {
                $errori[] = "$nomeVisibile: " . erroreCaricamento($file['error'][$i]);
                continue;
            }
            if (!is_uploaded_file($file['tmp_name'][$i])) {
                $errori[] = "$nomeVisibile: file non valido";
                continue;
            }
            $errore = salvaFoto($puntoId, $file['tmp_name'][$i], $alt, (int) $utente['id']);
            if ($errore) {
                $errori[] = "$nomeVisibile: $errore";
            } else {
                $caricate++;
            }
        }
    }
    if ($caricate) {
        flash('successo', $caricate === 1 ? 'Foto caricata.' : "$caricate foto caricate.");
    }
    if ($errori) {
        flash('errore', 'Non caricate: ' . implode('; ', $errori) . '.');
    } elseif (!$caricate) {
        flash('errore', 'Nessuna foto selezionata.');
    }
    redirect($ritorno);
}

$foto = fotoDelForm($pdo, $puntoId);
if (!$foto) {
    redirect($ritorno);
}

if ($azione === 'alt') {
    $alt = mb_substr(trim((string) ($_POST['alt_testo'] ?? '')), 0, 255);
    $pdo->prepare('UPDATE foto_punti SET alt_testo = ? WHERE id = ?')->execute([$alt ?: null, $foto['id']]);
    flash('successo', 'Descrizione della foto salvata.');
} elseif ($azione === 'copertina') {
    // Ordine più basso del punto: la foto diventa la prima, cioè la copertina.
    $stmt = $pdo->prepare('SELECT COALESCE(MIN(ordine), 0) - 1 FROM foto_punti WHERE punto_id = ?');
    $stmt->execute([$puntoId]);
    $pdo->prepare('UPDATE foto_punti SET ordine = ? WHERE id = ?')->execute([(int) $stmt->fetchColumn(), $foto['id']]);
    flash('successo', 'Copertina aggiornata.');
} elseif ($azione === 'elimina') {
    eliminaFoto($foto);
    flash('successo', 'Foto eliminata.');
}
redirect($ritorno);
