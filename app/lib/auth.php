<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function tentaLogin(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM utenti WHERE email = ? AND attivo = 1');
    $stmt->execute([$email]);
    $utente = $stmt->fetch();

    if (!$utente || !password_verify($password, $utente['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['utente_id'] = $utente['id'];
    return true;
}

function logout(): void
{
    $_SESSION = [];
    session_destroy();
}

function utenteCorrente(): ?array
{
    static $utente = null;
    static $caricato = false;

    if ($caricato) {
        return $utente;
    }
    $caricato = true;

    if (empty($_SESSION['utente_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM utenti WHERE id = ? AND attivo = 1');
    $stmt->execute([$_SESSION['utente_id']]);
    $utente = $stmt->fetch() ?: null;
    return $utente;
}

function richiediLogin(): array
{
    $utente = utenteCorrente();
    if (!$utente) {
        redirect('/admin/login.php');
    }
    return $utente;
}

function isSuperadmin(array $utente): bool
{
    return $utente['ruolo'] === 'superadmin';
}

/** Restituisce il comune_id a cui l'utente è limitato, o null se non ha restrizioni (superadmin). */
function comuneAmbito(array $utente): ?int
{
    return isSuperadmin($utente) ? null : (int) $utente['comune_id'];
}
