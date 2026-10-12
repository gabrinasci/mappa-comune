<?php

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function slugify(string $testo): string
{
    $testo = strtolower(trim($testo));
    $testo = iconv('UTF-8', 'ASCII//TRANSLIT', $testo);
    $testo = preg_replace('/[^a-z0-9]+/', '-', $testo);
    return trim($testo, '-');
}

function flash(string $chiave, ?string $messaggio = null): ?string
{
    if ($messaggio !== null) {
        $_SESSION['flash'][$chiave] = $messaggio;
        return null;
    }
    $valore = $_SESSION['flash'][$chiave] ?? null;
    unset($_SESSION['flash'][$chiave]);
    return $valore;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfCampo(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">';
}

function csrfVerifica(): void
{
    $inviato = $_POST['csrf_token'] ?? '';
    $atteso = $_SESSION['csrf_token'] ?? '';
    // Senza token in sessione la richiesta va rifiutata: altrimenti "vuoto" coinciderebbe con "vuoto".
    if ($atteso === '' || !is_string($inviato) || !hash_equals($atteso, $inviato)) {
        http_response_code(403);
        exit('Token di sicurezza non valido. Ricarica la pagina e riprova.');
    }
}

/** Percorso di un file statico con la data di modifica in coda: il browser riscarica il file solo quando cambia. */
function asset(string $percorso): string
{
    $file = __DIR__ . '/../../public' . $percorso;
    return $percorso . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/** Font, fogli di stile e icona comuni a tutte le pagine (da mettere nel <head>). */
function stiliComuni(): string
{
    return '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link href="https://fonts.googleapis.com/css2?family=Titillium+Web:wght@400;600;700&display=swap" rel="stylesheet">'
        . '<link rel="stylesheet" href="' . h(asset('/assets/css/tailwind.css')) . '">'
        . '<link rel="stylesheet" href="' . h(asset('/assets/css/style.css')) . '">'
        . '<link rel="icon" href="/assets/img/punti-in-comune_logo.svg" type="image/svg+xml">';
}
