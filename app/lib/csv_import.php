<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';

// Colonne attese nel CSV, in quest'ordine (intestazione in prima riga).
const CSV_COLONNE = [
    'comune', 'categoria', 'nome', 'indirizzo', 'lat', 'lng',
    'telefono', 'email', 'sito_web', 'descrizione',
    'orario_lun', 'orario_mar', 'orario_mer', 'orario_gio', 'orario_ven', 'orario_sab', 'orario_dom',
];

const GIORNI_SETTIMANA = [
    'orario_lun' => 1, 'orario_mar' => 2, 'orario_mer' => 3, 'orario_gio' => 4,
    'orario_ven' => 5, 'orario_sab' => 6, 'orario_dom' => 7,
];

const MAX_GEOCODIFICHE_PER_IMPORT = 30;

/**
 * Legge il CSV caricato e prepara un'anteprima riga per riga: valida i campi,
 * risolve comune/categoria per nome, e tenta il geocoding (Nominatim) per le righe
 * senza lat/lng. Non scrive nulla sul database: l'admin deve confermare l'anteprima.
 */
function anteprimaImportCsv(string $percorsoFile, array $utente): array
{
    $handle = fopen($percorsoFile, 'r');
    if ($handle === false) {
        return ['errore_generale' => 'Impossibile leggere il file caricato.', 'righe' => []];
    }

    $intestazione = fgetcsv($handle, 0, ';');
    $separatore = ';';
    if ($intestazione === false || count($intestazione) <= 1) {
        // Il file non usa ';': si rilegge da capo assumendo la virgola come separatore.
        rewind($handle);
        $intestazione = fgetcsv($handle, 0, ',');
        $separatore = ',';
    }
    if ($intestazione === false) {
        fclose($handle);
        return ['errore_generale' => 'Il file è vuoto o non è un CSV valido.', 'righe' => []];
    }

    $intestazione = array_map(fn($c) => strtolower(trim($c)), $intestazione);
    $mancanti = array_diff(['comune', 'categoria', 'nome', 'indirizzo'], $intestazione);
    if ($mancanti) {
        fclose($handle);
        return ['errore_generale' => 'Colonne obbligatorie mancanti nell\'intestazione: ' . implode(', ', $mancanti), 'righe' => []];
    }

    $comuni = comuniPerNomeOSlug();
    $categorie = categoriePerNome();
    $comuneAmbitoId = comuneAmbito($utente);

    $righe = [];
    $numeroGeocodifiche = 0;
    $numeroRiga = 1;

    while (($campi = fgetcsv($handle, 0, $separatore)) !== false) {
        $numeroRiga++;
        if (count(array_filter($campi, fn($v) => trim((string) $v) !== '')) === 0) {
            continue; // riga vuota, si ignora
        }

        $dati = @array_combine($intestazione, array_pad($campi, count($intestazione), ''));
        if ($dati === false) {
            $righe[] = ['numero_riga' => $numeroRiga, 'dati' => [], 'errori' => ['Numero di colonne non corrispondente all\'intestazione.']];
            continue;
        }
        $dati = array_map('trim', $dati);

        $errori = [];
        $comuneChiave = strtolower($dati['comune'] ?? '');
        $comune = $comuni[$comuneChiave] ?? null;
        if (!$comune) {
            $errori[] = "Comune \"{$dati['comune']}\" non riconosciuto.";
        } elseif ($comuneAmbitoId !== null && $comune['id'] !== $comuneAmbitoId) {
            $errori[] = 'Non hai i permessi per importare punti per questo comune.';
        }

        $categoriaChiave = strtolower($dati['categoria'] ?? '');
        $categoria = $categorie[$categoriaChiave] ?? null;
        if (!$categoria) {
            $errori[] = "Categoria \"{$dati['categoria']}\" non riconosciuta.";
        }

        if ($dati['nome'] === '') {
            $errori[] = 'Il nome del punto servizio è obbligatorio.';
        }
        if ($dati['indirizzo'] === '') {
            $errori[] = 'L\'indirizzo è obbligatorio.';
        }

        $lat = $dati['lat'] !== '' ? (float) str_replace(',', '.', $dati['lat']) : null;
        $lng = $dati['lng'] !== '' ? (float) str_replace(',', '.', $dati['lng']) : null;
        $geocodificato = false;

        if (($lat === null || $lng === null) && $dati['indirizzo'] !== '' && $comune) {
            if ($numeroGeocodifiche < MAX_GEOCODIFICHE_PER_IMPORT) {
                $numeroGeocodifiche++;
                $coordinate = geocodificaIndirizzo($dati['indirizzo'] . ', ' . $comune['nome']);
                if ($coordinate) {
                    [$lat, $lng] = $coordinate;
                    $geocodificato = true;
                } else {
                    $errori[] = 'Coordinate mancanti e geocoding automatico non riuscito: inseriscile manualmente.';
                }
            } else {
                $errori[] = 'Coordinate mancanti: limite di geocodifiche automatiche per import raggiunto, inseriscile manualmente.';
            }
        }

        $orari = [];
        foreach (GIORNI_SETTIMANA as $colonna => $giorno) {
            $risultato = normalizzaOrario($dati[$colonna] ?? '');
            if ($risultato === null) {
                $errori[] = "Formato orario non valido nella colonna \"$colonna\" (usa HH:MM-HH:MM oppure \"chiuso\").";
                continue;
            }
            $orari[] = ['giorno_settimana' => $giorno] + $risultato;
        }

        $righe[] = [
            'numero_riga' => $numeroRiga,
            'dati' => $dati,
            'comune_id' => $comune['id'] ?? null,
            'categoria_id' => $categoria['id'] ?? null,
            'lat' => $lat,
            'lng' => $lng,
            'geocodificato' => $geocodificato,
            'orari' => $orari,
            'errori' => $errori,
        ];
    }

    fclose($handle);
    return ['errore_generale' => null, 'righe' => $righe];
}

/** Scrive su database le righe confermate dall'admin (dopo l'anteprima). */
function confermaImportCsv(array $righe, array $utente): array
{
    $pdo = db();
    $importate = 0;
    $errori = 0;
    $dettagliErrore = [];

    $pdo->beginTransaction();
    try {
        $inserisciPunto = $pdo->prepare(
            'INSERT INTO punti_servizio (comune_id, categoria_id, nome, indirizzo, lat, lng, descrizione, telefono, email, sito_web, creato_da)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $inserisciOrario = $pdo->prepare(
            'INSERT INTO orari_apertura (punto_id, giorno_settimana, apertura, chiusura, chiuso) VALUES (?, ?, ?, ?, ?)'
        );

        foreach ($righe as $riga) {
            if (!empty($riga['errori']) || $riga['lat'] === null || $riga['lng'] === null) {
                $errori++;
                $dettagliErrore[] = "Riga {$riga['numero_riga']}: " . implode(' ', $riga['errori'] ?: ['dati incompleti']);
                continue;
            }
            $dati = $riga['dati'];
            $inserisciPunto->execute([
                $riga['comune_id'], $riga['categoria_id'], $dati['nome'], $dati['indirizzo'],
                $riga['lat'], $riga['lng'], $dati['descrizione'] ?: null, $dati['telefono'] ?: null,
                $dati['email'] ?: null, $dati['sito_web'] ?: null, $utente['id'],
            ]);
            $puntoId = (int) $pdo->lastInsertId();
            foreach ($riga['orari'] as $orario) {
                $inserisciOrario->execute([$puntoId, $orario['giorno_settimana'], $orario['apertura'], $orario['chiusura'], $orario['chiuso'] ? 1 : 0]);
            }
            $importate++;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return ['importate' => $importate, 'errori' => $errori, 'dettagli_errore' => $dettagliErrore];
}

function normalizzaOrario(string $testo): ?array
{
    $testo = trim($testo);
    if ($testo === '' || strtolower($testo) === 'chiuso') {
        return ['apertura' => null, 'chiusura' => null, 'chiuso' => true];
    }
    if (preg_match('/^([0-2]\d:[0-5]\d)\s*-\s*([0-2]\d:[0-5]\d)$/', $testo, $m)) {
        return ['apertura' => $m[1], 'chiusura' => $m[2], 'chiuso' => false];
    }
    return null;
}

/** @return array{0: float, 1: float}|null [lat, lng] oppure null se non trovato. */
function geocodificaIndirizzo(string $indirizzo): ?array
{
    static $ultimaChiamata = 0.0;
    $trascorso = microtime(true) - $ultimaChiamata;
    if ($trascorso < 1.0) {
        usleep((int) ((1.0 - $trascorso) * 1_000_000)); // rispetta il limite di 1 richiesta/secondo di Nominatim
    }
    $ultimaChiamata = microtime(true);

    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => $indirizzo, 'format' => 'json', 'limit' => 1, 'countrycodes' => 'it',
    ]);
    $contesto = stream_context_create(['http' => [
        'header' => 'User-Agent: ' . NOMINATIM_USER_AGENT,
        'timeout' => 5,
    ]]);
    $risposta = @file_get_contents($url, false, $contesto);
    if ($risposta === false) {
        return null;
    }
    $risultati = json_decode($risposta, true);
    if (empty($risultati[0]['lat']) || empty($risultati[0]['lon'])) {
        return null;
    }
    return [(float) $risultati[0]['lat'], (float) $risultati[0]['lon']];
}

function comuniPerNomeOSlug(): array
{
    $stmt = db()->query('SELECT * FROM comuni');
    $mappa = [];
    foreach ($stmt->fetchAll() as $comune) {
        $mappa[strtolower($comune['nome'])] = $comune;
        $mappa[strtolower($comune['slug'])] = $comune;
    }
    return $mappa;
}

function categoriePerNome(): array
{
    $stmt = db()->query('SELECT * FROM categorie');
    $mappa = [];
    foreach ($stmt->fetchAll() as $categoria) {
        $mappa[strtolower($categoria['nome'])] = $categoria;
    }
    return $mappa;
}
