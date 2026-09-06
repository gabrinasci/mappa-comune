<?php
// Copia questo file in config.php (che resta escluso da git) e inserisci i valori reali.

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'mappa_servizi');
define('DB_USER', 'root');
define('DB_PASS', '');

// Usata solo lato admin per il geocoding assistito in fase di import (Nominatim richiede
// un User-Agent identificativo, non un'email pubblica: mettere un nome progetto + un contatto).
define('NOMINATIM_USER_AGENT', 'MappaServiziComunali/1.0 (referente@comune.it)');

define('APP_TIMEZONE', 'Europe/Rome');
