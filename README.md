# Mappa Servizi Comunali

Vedi [PROGETTO.md](PROGETTO.md) per la documentazione completa di scelte tecniche, modello dati e roadmap. Questo file contiene solo le istruzioni pratiche per far girare il progetto.

## Requisiti

- PHP 8.1+ con estensioni `pdo_mysql` abilitata (di serie in quasi tutti gli hosting condivisi)
- MySQL 5.7+/MariaDB equivalente
- Per la build di produzione del CSS (facoltativa, solo in locale): Node.js 18+

## Primo avvio in locale

1. Crea un database MySQL vuoto (es. `mappa_servizi`).
2. Copia la configurazione: `cp app/config/config.example.php app/config/config.php` e inserisci host/nome db/utente/password reali. Questo file resta escluso da git (contiene credenziali).
3. Carica lo schema: `mysql -u utente -p mappa_servizi < sql/schema.sql` (crea le tabelle e i dati iniziali: 4 comuni, 6 categorie).
   - Se il database esisteva già prima dell'introduzione di profilo/log accessi/SMTP, esegui anche `mysql -u utente -p mappa_servizi < sql/migrazione_001_profilo_utenti.sql`.
   - Se esisteva prima dell'introduzione dell'orario in testo libero, esegui anche `sql/migrazione_002_orario_testo.sql`.
   - Se esisteva prima del flag "servizio sovracomunale", esegui anche `sql/migrazione_003_sovracomunale.sql`.
   - Se esisteva prima del banner avvisi in home, esegui anche `sql/migrazione_006_avvisi.sql`.
   - Se esisteva prima dell'aggiunta di Refrontolo, esegui anche `sql/migrazione_007_comune_refrontolo.sql`.
   - Per completare con `https://` i siti web salvati senza protocollo, esegui `sql/migrazione_008_siti_web_https.sql`.
   - Tutte le migrazioni sono da eseguire una tantum e non toccano i dati esistenti.
4. Punta un server PHP alla cartella `public/` (document root). In locale, ad esempio: `php -S localhost:8000 -t public`.
5. Apri `/admin/setup.php`: essendo il database senza utenti, mostra il wizard per creare il primo account (superadmin). Dopo la creazione verrai reindirizzato al login.
6. Da loggato, `admin/import.php` per importare `data/sample_punti.csv` come primo test, oppure `admin/punti.php` per inserire punti a mano.
7. Da superadmin, su `admin/impostazioni_smtp.php` configura un server SMTP (necessario per l'invio delle email di reset password e di creazione utenti — senza SMTP configurato il sistema mostra comunque il link da copiare/incollare a mano, non blocca il lavoro).

## Struttura cartelle

```
/public/            document root del webserver (tutto ciò che è raggiungibile da browser)
  index.php          mappa pubblica
  informazioni.php   pagina "Il servizio": progetto, funzionamento, link ai comuni
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

## CSS (Tailwind compilato)

Le pagine usano il file compilato `public/assets/css/tailwind.css` (colori e font del progetto in `tailwind.config.js`), incluso tramite `stiliComuni()` in `app/lib/helpers.php`. Dopo aver aggiunto o cambiato classi Tailwind nel markup va rigenerato:

```
npm install          # una volta sola
npm run build:css    # rigenera public/assets/css/tailwind.css
```

Il file compilato va committato e caricato insieme al resto (l'hosting non deve avere Node). CSS, JS e GeoJSON sono richiamati con `?v=<data di modifica>` (funzione `asset()`): dopo un aggiornamento i browser scaricano subito la versione nuova.

## Mappa di sfondo

Il superadmin sceglie il fornitore da **Impostazioni mappa**:
- **Mapbox** (predefinito): richiede un token pubblico `pk.` (facoltativo, altrimenti si usa quello del progetto); gratuito fino a 200.000 tasselli al mese.
- **OpenStreetMap (OpenFreeMap)**: gratuito e senza chiave, disegnato con MapLibre (caricato solo in questo caso). Se il browser non supporta WebGL si passa da soli ai tasselli OpenStreetMap standard.

Nel pannello Filtri i visitatori possono attivare la **mappa semplificata**, che nasconde negozi e punti di interesse dello sfondo (Mapbox: stile "light"; OpenFreeMap: livelli `poi*` spenti). La scelta resta salvata nel browser. Le mappe CARTO, citate in PROGETTO.md, oggi richiedono una chiave API e non sono più un'alternativa gratuita.

## Test automatici

Due script, da lanciare in locale o contro il sito online dopo ogni pubblicazione (serve Google Chrome installato):

```
npm install
BASE_URL=http://127.0.0.1:8000 ADMIN_EMAIL=... ADMIN_PASSWORD=... npm run test:e2e
BASE_URL=http://127.0.0.1:8000 ADMIN_EMAIL=... ADMIN_PASSWORD=... COMUNE_EMAIL=... COMUNE_PASSWORD=... npm run test:sicurezza
```

- `tests/e2e.mjs`: mappa su desktop e mobile (clic sui punti con i filtri aperti, telefoni cliccabili, mappa semplificata, nessun errore JS né scorrimento orizzontale), pagine pubbliche e cambio di fornitore della mappa (ripristinato alla fine).
- `tests/sicurezza.mjs`: pagine admin senza login, CSRF, input malformati, file sensibili non raggiungibili, XSS e link `javascript:` in punti/avvisi/import CSV, permessi dell'admin di comune sui punti di altri comuni, blocco dopo 5 accessi falliti.

Le credenziali `ADMIN_*` (superadmin) e `COMUNE_*` (admin di un comune) sono facoltative: senza, si eseguono solo i controlli anonimi. Il test del blocco accessi blocca per 15 minuti l'account `COMUNE_*`: usare un account di prova. `CHROME_PATH` permette di indicare un percorso di Chrome diverso da quello predefinito su macOS.

## Pubblicazione (FTP)

Caricare sempre l'intera cartella `public/` e `app/` (tranne `app/config/config.php`, che sul server ha valori propri). Dopo l'aggiornamento, eseguire da phpMyAdmin le migrazioni non ancora applicate (`sql/migrazione_00X_*.sql`, in ordine) e lanciare i test contro l'indirizzo online.

## Note operative

- **Geocoding in fase di import**: usa Nominatim (OpenStreetMap), gratuito ma limitato a 1 richiesta/secondo; per import di poche decine di righe va bene, per import molto grandi valutare di inserire le coordinate già nel CSV.
- **Confini comunali**: non ancora inclusi. Quando disponibili, basta salvare il file in `data/confini.geojson` (GeoJSON, WGS84): la mappa lo rileva automaticamente e mostra il toggle "Mostra confini comunali" nel pannello filtri, senza bisogno di modificare il codice.
- **Utenti**: un `admin_comune` vede/modifica solo i punti del proprio comune (import CSV compreso); un `superadmin` gestisce tutti i comuni, le categorie e gli utenti (`admin/utenti.php`).
- **Creazione utenti**: il superadmin non imposta mai la password di un altro utente — alla creazione (o su richiesta, pulsante "Link reset") viene inviata un'email con un link valido 1 ora per scegliere la password. Se l'SMTP non è (ancora) configurato, il link viene comunque mostrato a video da copiare manualmente.
- **Password**: sempre salvate con hash bcrypt (`password_hash`), mai in chiaro né in forma reversibile.
- **Log accessi**: ogni tentativo di login (riuscito o fallito) viene registrato; ogni utente vede i propri ultimi accessi in `admin/profilo.php`.
- **Orario**: due modalità alternative, in CSV e nel form manuale. Se si compila `orario_testo` (testo libero, utile per orari irregolari o "su appuntamento") ha la precedenza e sostituisce l'eventuale orario strutturato giorno-per-giorno; altrimenti si usano le colonne `orario_lun`...`orario_dom` (formato `HH:MM-HH:MM` o `chiuso`).
- **Servizio sovracomunale**: colonna `sovracomunale` (si/no in CSV, checkbox nel form) per i servizi offerti a residenti di più comuni pur avendo una sede fisica in uno solo. Un punto sovracomunale resta visibile sulla mappa pubblica indipendentemente dal filtro comune selezionato.
