<?php
require_once __DIR__ . '/../config/db.php';

// Foto dei punti servizio: ogni immagine caricata viene verificata e ricodificata (WebP, o JPEG
// se il server non supporta WebP) in due misure. La ricodifica elimina i dati EXIF, compresa
// l'eventuale posizione GPS dello scatto, e garantisce che nella cartella finiscano solo immagini.
const CARTELLA_FOTO = __DIR__ . '/../../public/uploads/punti';
const URL_FOTO = '/uploads/punti';
const MAX_FOTO_PER_PUNTO = 12;
const LATO_GRANDE = 1600;
const LATO_MEDIO = 800;
const MAX_PIXEL_ORIGINALE = 40_000_000; // oltre, la decodifica rischia di esaurire la memoria del server
const TIPI_IMMAGINE_AMMESSI = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

function elaborazioneFotoDisponibile(): bool
{
    return function_exists('imagecreatetruecolor');
}

function fotoDelPunto(int $puntoId): array
{
    $stmt = db()->prepare('SELECT * FROM foto_punti WHERE punto_id = ? ORDER BY ordine, id');
    $stmt->execute([$puntoId]);
    return $stmt->fetchAll();
}

/** Foto di tutti i punti, raggruppate per punto (per l'API pubblica). */
function fotoPerPunto(): array
{
    $gruppi = [];
    try {
        foreach (db()->query('SELECT * FROM foto_punti ORDER BY ordine, id') as $f) {
            $gruppi[$f['punto_id']][] = [
                'grande' => URL_FOTO . '/' . $f['file_grande'],
                'media' => URL_FOTO . '/' . $f['file_medio'],
                'alt' => $f['alt_testo'],
                'larghezza' => (int) $f['larghezza'],
                'altezza' => (int) $f['altezza'],
            ];
        }
    } catch (PDOException $e) {
        error_log('Foto non disponibili: ' . $e->getMessage()); // es. migrazione 009 non ancora eseguita
    }
    return $gruppi;
}

/** Messaggio leggibile per gli errori di caricamento di PHP. */
function erroreCaricamento(int $codice): string
{
    return match ($codice) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'file troppo grande per il server',
        UPLOAD_ERR_PARTIAL => 'caricamento interrotto',
        UPLOAD_ERR_NO_FILE => 'nessun file',
        default => 'errore del server durante il caricamento',
    };
}

/**
 * Verifica, ricodifica e salva una foto caricata. Restituisce null se tutto va bene,
 * altrimenti il motivo dell'errore.
 */
function salvaFoto(int $puntoId, string $percorsoTemporaneo, ?string $altTesto, int $utenteId): ?string
{
    if (!elaborazioneFotoDisponibile()) {
        return 'il server non ha l\'estensione PHP GD per elaborare le immagini';
    }
    if (count(fotoDelPunto($puntoId)) >= MAX_FOTO_PER_PUNTO) {
        return 'raggiunto il massimo di ' . MAX_FOTO_PER_PUNTO . ' foto per punto';
    }
    $info = @getimagesize($percorsoTemporaneo);
    if (!$info || !in_array($info[2], TIPI_IMMAGINE_AMMESSI, true)) {
        return 'formato non supportato (usa JPEG, PNG o WebP)';
    }
    [$larghezza, $altezza] = $info;
    if ($larghezza * $altezza > MAX_PIXEL_ORIGINALE) {
        return 'immagine troppo grande (oltre 40 megapixel)';
    }

    $immagine = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($percorsoTemporaneo),
        IMAGETYPE_PNG => @imagecreatefrompng($percorsoTemporaneo),
        IMAGETYPE_WEBP => @imagecreatefromwebp($percorsoTemporaneo),
    };
    if (!$immagine) {
        return 'immagine danneggiata o non leggibile';
    }
    $immagine = raddrizzaSecondoExif($immagine, $percorsoTemporaneo, $info[2]);

    $cartella = CARTELLA_FOTO . '/' . $puntoId;
    if (!is_dir($cartella) && !@mkdir($cartella, 0755, true)) {
        imagedestroy($immagine);
        return 'impossibile creare la cartella delle foto sul server (controlla i permessi di public/uploads)';
    }

    $estensione = function_exists('imagewebp') ? 'webp' : 'jpg';
    $base = bin2hex(random_bytes(8));
    $grande = ridimensiona($immagine, LATO_GRANDE);
    $media = ridimensiona($immagine, LATO_MEDIO);
    $nomeGrande = "$base-g.$estensione";
    $nomeMedio = "$base-m.$estensione";
    $ok = scriviImmagine($grande, "$cartella/$nomeGrande", $estensione)
        && scriviImmagine($media, "$cartella/$nomeMedio", $estensione);
    $dimensioniGrande = [imagesx($grande), imagesy($grande)];
    imagedestroy($immagine);
    imagedestroy($grande);
    imagedestroy($media);
    if (!$ok) {
        @unlink("$cartella/$nomeGrande");
        @unlink("$cartella/$nomeMedio");
        return 'impossibile salvare l\'immagine sul server';
    }

    $stmt = db()->prepare('SELECT COALESCE(MAX(ordine), 0) + 1 FROM foto_punti WHERE punto_id = ?');
    $stmt->execute([$puntoId]);
    $ordine = (int) $stmt->fetchColumn();
    db()->prepare('INSERT INTO foto_punti (punto_id, file_grande, file_medio, larghezza, altezza, alt_testo, ordine, creato_da) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$puntoId, "$puntoId/$nomeGrande", "$puntoId/$nomeMedio", $dimensioniGrande[0], $dimensioniGrande[1], $altTesto ?: null, $ordine, $utenteId]);
    return null;
}

/** Le foto scattate col telefono girato hanno l'orientamento solo nei dati EXIF: lo si applica ai pixel. */
function raddrizzaSecondoExif(GdImage $immagine, string $percorso, int $tipo): GdImage
{
    if ($tipo !== IMAGETYPE_JPEG || !function_exists('exif_read_data')) {
        return $immagine;
    }
    $exif = @exif_read_data($percorso);
    $gradi = match ((int) ($exif['Orientation'] ?? 1)) {
        3 => 180,
        6 => -90,
        8 => 90,
        default => 0,
    };
    if ($gradi === 0) {
        return $immagine;
    }
    $ruotata = imagerotate($immagine, $gradi, 0);
    if (!$ruotata) {
        return $immagine;
    }
    imagedestroy($immagine);
    return $ruotata;
}

/** Copia ridotta in modo che il lato lungo non superi $lato (l'originale resta intatto). */
function ridimensiona(GdImage $immagine, int $lato): GdImage
{
    $l = imagesx($immagine);
    $a = imagesy($immagine);
    $scala = min(1, $lato / max($l, $a));
    $nuovaL = max(1, (int) round($l * $scala));
    $nuovaA = max(1, (int) round($a * $scala));
    $copia = imagecreatetruecolor($nuovaL, $nuovaA);
    // Sfondo bianco per i PNG con trasparenza (il formato finale può essere JPEG).
    imagefill($copia, 0, 0, imagecolorallocate($copia, 255, 255, 255));
    imagecopyresampled($copia, $immagine, 0, 0, 0, 0, $nuovaL, $nuovaA, $l, $a);
    return $copia;
}

function scriviImmagine(GdImage $immagine, string $percorso, string $estensione): bool
{
    return $estensione === 'webp' ? imagewebp($immagine, $percorso, 80) : imagejpeg($immagine, $percorso, 82);
}

function eliminaFoto(array $foto): void
{
    @unlink(CARTELLA_FOTO . '/' . $foto['file_grande']);
    @unlink(CARTELLA_FOTO . '/' . $foto['file_medio']);
    db()->prepare('DELETE FROM foto_punti WHERE id = ?')->execute([$foto['id']]);
}

/** Da chiamare prima di eliminare un punto: le righe sparirebbero con il punto, i file no. */
function eliminaFotoDelPunto(int $puntoId): void
{
    foreach (fotoDelPunto($puntoId) as $foto) {
        eliminaFoto($foto);
    }
    @rmdir(CARTELLA_FOTO . '/' . $puntoId);
}
