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
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $inviato)) {
        http_response_code(403);
        exit('Token di sicurezza non valido. Ricarica la pagina e riprova.');
    }
}
