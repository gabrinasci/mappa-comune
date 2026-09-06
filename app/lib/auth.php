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
        registraAccesso($utente['id'] ?? null, $email, 'fallito');
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['utente_id'] = $utente['id'];
    db()->prepare('UPDATE utenti SET ultimo_accesso = NOW() WHERE id = ?')->execute([$utente['id']]);
    registraAccesso($utente['id'], $email, 'successo');
    return true;
}

function registraAccesso(?int $utenteId, string $email, string $esito): void
{
    $stmt = db()->prepare('INSERT INTO log_accessi (utente_id, email_tentativo, esito, ip, user_agent) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        $utenteId,
        $email,
        $esito,
        $_SERVER['REMOTE_ADDR'] ?? null,
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255) ?: null,
    ]);
}

/** Genera un token di reset in chiaro (da inviare via email) e ne salva solo l'hash, valido 1 ora. */
function generaTokenReset(int $utenteId): string
{
    $token = bin2hex(random_bytes(32));
    $stmt = db()->prepare('UPDATE utenti SET reset_token_hash = ?, reset_token_scadenza = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?');
    $stmt->execute([hash('sha256', $token), $utenteId]);
    return $token;
}

function trovaUtentePerEmail(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM utenti WHERE email = ? AND attivo = 1');
    $stmt->execute([$email]);
    return $stmt->fetch() ?: null;
}

function trovaUtentePerTokenReset(string $token): ?array
{
    $stmt = db()->prepare('SELECT * FROM utenti WHERE reset_token_hash = ? AND reset_token_scadenza > NOW() AND attivo = 1');
    $stmt->execute([hash('sha256', $token)]);
    return $stmt->fetch() ?: null;
}

function reimpostaPassword(int $utenteId, string $nuovaPassword): void
{
    $stmt = db()->prepare('UPDATE utenti SET password_hash = ?, reset_token_hash = NULL, reset_token_scadenza = NULL WHERE id = ?');
    $stmt->execute([password_hash($nuovaPassword, PASSWORD_DEFAULT), $utenteId]);
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
