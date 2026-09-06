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
   - Se il database esisteva già prima dell'introduzione di profilo/log accessi/SMTP, esegui invece `mysql -u utente -p mappa_servizi < sql/migrazione_001_profilo_utenti.sql` (una tantum, non tocca i dati esistenti).
4. Punta un server PHP alla cartella `public/` (document root). In locale, ad esempio: `php -S localhost:8000 -t public`.
5. Apri `/admin/setup.php`: essendo il database senza utenti, mostra il wizard per creare il primo account (superadmin). Dopo la creazione verrai reindirizzato al login.
6. Da loggato, `admin/import.php` per importare `data/sample_punti.csv` come primo test, oppure `admin/punti.php` per inserire punti a mano.
7. Da superadmin, su `admin/impostazioni_smtp.php` configura un server SMTP (necessario per l'invio delle email di reset password e di creazione utenti — senza SMTP configurato il sistema mostra comunque il link da copiare/incollare a mano, non blocca il lavoro).

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
  lib/                autenticazione, helper, import CSV, invio email, impostazioni
  vendor/phpmailer/    libreria PHPMailer (sorgente, nessun Composer richiesto)
/sql/schema.sql       schema + dati iniziali
/sql/migrazione_001_profilo_utenti.sql  migrazione per DB creati prima di profilo/SMTP/log accessi
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
- **Utenti**: un `admin_comune` vede/modifica solo i punti del proprio comune (import CSV compreso); un `superadmin` gestisce tutti e 3 i comuni, le categorie e gli utenti (`admin/utenti.php`).
- **Creazione utenti**: il superadmin non imposta mai la password di un altro utente — alla creazione (o su richiesta, pulsante "Link reset") viene inviata un'email con un link valido 1 ora per scegliere la password. Se l'SMTP non è (ancora) configurato, il link viene comunque mostrato a video da copiare manualmente.
- **Password**: sempre salvate con hash bcrypt (`password_hash`), mai in chiaro né in forma reversibile.
- **Log accessi**: ogni tentativo di login (riuscito o fallito) viene registrato; ogni utente vede i propri ultimi accessi in `admin/profilo.php`.
