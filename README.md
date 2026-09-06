# Mappa Servizi Comunali

Vedi [PROGETTO.md](PROGETTO.md) per la documentazione completa di scelte tecniche, modello dati e roadmap. Questo file contiene solo le istruzioni pratiche per far girare il progetto.

## Requisiti

- PHP 8.1+ con estensioni `pdo_mysql` abilitata (di serie in quasi tutti gli hosting condivisi)
- MySQL 5.7+/MariaDB equivalente
- Per la build di produzione del CSS (facoltativa, solo in locale): Node.js 18+

## Primo avvio in locale

1. Crea un database MySQL vuoto (es. `mappa_servizi`).
2. Copia la configurazione: `cp app/config/config.example.php app/config/config.php` e inserisci host/nome db/utente/password reali. Questo file resta escluso da git (contiene credenziali).
3. Carica lo schema: `mysql -u utente -p mappa_servizi < sql/schema.sql` (crea le tabelle e i dati iniziali: 3 comuni, 5 categorie).
4. Punta un server PHP alla cartella `public/` (document root). In locale, ad esempio: `php -S localhost:8000 -t public`.
5. Apri `/admin/setup.php`: essendo il database senza utenti, mostra il wizard per creare il primo account (superadmin). Dopo la creazione verrai reindirizzato al login.
6. Da loggato, `admin/import.php` per importare `data/sample_punti.csv` come primo test, oppure `admin/punti.php` per inserire punti a mano.

## Struttura cartelle

```
/public/            document root del webserver (tutto ciò che è raggiungibile da browser)
  index.php          mappa pubblica
  lista.php          vista tabellare/accessibile
  accessibilita.php  dichiarazione di accessibilità (da completare)
  api/punti.php       endpoint JSON pubblico usato dalla mappa
  admin/              pannello di gestione (login richiesto)
  assets/             CSS/JS statici
/app/
  config/             connessione DB e configurazione (config.php escluso da git)
  lib/                autenticazione, helper, import CSV
/sql/schema.sql       schema + dati iniziali
/data/sample_punti.csv  CSV di esempio per testare l'import
/resources/tailwind-input.css  sorgente per la build CSS di produzione
```

## CSS di produzione (Tailwind)

In sviluppo le pagine caricano Tailwind da CDN (`cdn.tailwindcss.com`, nessuna installazione richiesta). **Prima di andare in produzione** conviene passare alla build compilata, più leggera e senza dipendere da uno script esterno ad ogni caricamento pagina:

```
npm install
npm run build:css   # genera public/assets/css/tailwind.css
```

Poi, in ciascun file `.php` che oggi include `<script src="https://cdn.tailwindcss.com"></script>`, sostituirlo con:
```html
<link rel="stylesheet" href="/assets/css/tailwind.css">
```
Da rifare (`npm run build:css`) ogni volta che si aggiungono nuove classi Tailwind nel markup. Il file compilato va committato/caricato insieme al resto (l'hosting non deve avere Node).

## Note operative

- **Geocoding in fase di import**: usa Nominatim (OpenStreetMap), gratuito ma limitato a 1 richiesta/secondo; per import di poche decine di righe va bene, per import molto grandi valutare di inserire le coordinate già nel CSV.
- **Confini comunali**: non ancora inclusi. Quando disponibili, basta salvare il file in `data/confini.geojson` (GeoJSON, WGS84): la mappa lo rileva automaticamente e mostra il toggle "Mostra confini comunali" nel pannello filtri, senza bisogno di modificare il codice.
- **Utenti**: un `admin_comune` vede/modifica solo i punti del proprio comune (import CSV compreso); un `superadmin` gestisce tutti e 3 i comuni e le categorie.
