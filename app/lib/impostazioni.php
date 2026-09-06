<?php
require_once __DIR__ . '/../config/db.php';

function impostazione(string $chiave, ?string $default = null): ?string
{
    $stmt = db()->prepare('SELECT valore FROM impostazioni WHERE chiave = ?');
    $stmt->execute([$chiave]);
    $valore = $stmt->fetchColumn();
    return $valore !== false ? $valore : $default;
}

function salvaImpostazione(string $chiave, string $valore): void
{
    $stmt = db()->prepare('INSERT INTO impostazioni (chiave, valore) VALUES (?, ?) ON DUPLICATE KEY UPDATE valore = VALUES(valore)');
    $stmt->execute([$chiave, $valore]);
}
