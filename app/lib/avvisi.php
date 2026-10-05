<?php
require_once __DIR__ . '/../config/db.php';

// Tipi di avviso del banner in home: etichetta, colore del cerchio e icona (path SVG 24x24 a tratto).
const TIPI_AVVISO = [
    'info' => [
        'etichetta' => 'Informazione',
        'colore' => '#0066CC',
        'icona' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
    ],
    'orari' => [
        'etichetta' => 'Cambio orari',
        'colore' => '#8A5A00',
        'icona' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    ],
    'novita' => [
        'etichetta' => 'Novità',
        'colore' => '#0B7A35',
        'icona' => '<path d="M3 10v4h3l6 4V6L6 10H3Z"/><path d="M16 9a4 4 0 0 1 0 6M19 6a8 8 0 0 1 0 12"/>',
    ],
    'urgente' => [
        'etichetta' => 'Avviso importante',
        'colore' => '#C0122C',
        'icona' => '<path d="M12 3 2 20h20L12 3Z"/><path d="M12 10v4M12 17h.01"/>',
    ],
];

function svgIconaAvviso(string $tipo): string
{
    $icona = TIPI_AVVISO[$tipo]['icona'] ?? TIPI_AVVISO['info']['icona'];
    return '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $icona . '</svg>';
}

/** Avvisi da mostrare oggi in home: attivi ed entro le eventuali date di inizio/fine. */
function avvisiAttivi(): array
{
    try {
        return db()->query("SELECT a.*, co.nome AS comune_nome FROM avvisi a
                            LEFT JOIN comuni co ON co.id = a.comune_id
                            WHERE a.attivo = 1
                              AND (a.data_inizio IS NULL OR a.data_inizio <= CURDATE())
                              AND (a.data_fine IS NULL OR a.data_fine >= CURDATE())
                            ORDER BY a.ordine, a.id DESC")->fetchAll();
    } catch (PDOException $e) {
        // Es. tabella non ancora creata (migrazione 006 non eseguita): la home resta utilizzabile senza banner.
        error_log('Avvisi non disponibili: ' . $e->getMessage());
        return [];
    }
}
