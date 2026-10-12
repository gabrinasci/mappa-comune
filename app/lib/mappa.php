<?php
require_once __DIR__ . '/impostazioni.php';

// Fornitori della mappa di sfondo selezionabili dal superadmin (Impostazioni mappa).
const FORNITORI_MAPPA = [
    'mapbox' => [
        'etichetta' => 'Mapbox',
        'descrizione' => 'Stile curato, richiede un token. Gratuito fino a 200.000 tasselli al mese, poi a pagamento.',
    ],
    'openfreemap' => [
        'etichetta' => 'OpenStreetMap (OpenFreeMap)',
        'descrizione' => 'Gratuito, senza chiave né registrazione. Dati OpenStreetMap; se il browser non supporta la grafica WebGL si usa la mappa OpenStreetMap standard.',
    ],
];

// Token pubblico (pk.*) usato finora: resta il valore predefinito se non se ne imposta un altro.
const MAPBOX_TOKEN_PREDEFINITO = 'pk.eyJ1IjoiZ2FicmluYXNjaSIsImEiOiJjbXV1ZDloZXIwc3o3MndzZG03OWViajNuIn0.T0CD_t-fU1yrPmylzjItVQ';

/** Configurazione passata al JavaScript della mappa pubblica. */
function configMappa(): array
{
    try {
        $fornitore = impostazione('mappa_fornitore', 'mapbox');
        $token = impostazione('mapbox_token', '') ?: MAPBOX_TOKEN_PREDEFINITO;
    } catch (PDOException $e) {
        error_log('Impostazioni mappa non disponibili: ' . $e->getMessage());
        $fornitore = 'mapbox';
        $token = MAPBOX_TOKEN_PREDEFINITO;
    }
    if (!array_key_exists($fornitore, FORNITORI_MAPPA)) {
        $fornitore = 'mapbox';
    }
    return [
        'fornitore' => $fornitore,
        'mapboxToken' => $fornitore === 'mapbox' ? $token : null,
    ];
}
