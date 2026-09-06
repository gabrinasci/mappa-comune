# Mappa Servizi Comunali — Documento di Progetto

## 1. Obiettivo

Portale unico che mostra su un'unica mappa i servizi offerti da 3 comuni (uffici, sportelli, centri, biblioteche, ecc.), aggiornabile autonomamente dai dipendenti comunali tramite pannello di amministrazione, con filtri per comune e per area tematica, ricerca testuale, layer dei confini amministrativi, e import massivo da CSV/Excel. Vincoli guida: costi di esercizio bassi/nulli, semplicità di manutenzione futura, conformità ai requisiti di accessibilità AGID/WCAG 2.1 AA.

## 2. Stack tecnologico

### Backend: **PHP 8 + MySQL, senza framework pesante**

Motivazione: è già disponibile hosting condiviso PHP per i 3 comuni → costo aggiuntivo zero, deploy via semplice upload file (nessun processo di build, nessun servizio da tenere sempre attivo come richiederebbe Node.js). PHP+MySQL è inoltre lo stack più diffuso e facilmente sostituibile da qualunque futuro manutentore (è lo standard de facto per i siti della PA italiana).

Scelte di dettaglio:
- **Nessun framework "pesante"** (Laravel/Symfony) in prima battuta: richiedono Composer/SSH e più risorse, non garantiti su ogni hosting condiviso economico. Si struttura il codice in stile MVC-lite fatto a mano (routing minimale, cartelle separate per pagine pubbliche / admin / API), che resta comunque leggibile e ordinato.
- **PDO con prepared statement** per tutte le query (protezione SQL injection).
- **Import CSV nativo** (`fgetcsv`, zero dipendenze). Per Excel (.xlsx) due strade, da confermare in base a cosa supporta l'hosting (vedi §11):
  - se l'hosting consente Composer → libreria `PhpSpreadsheet` per leggere .xlsx direttamente;
  - altrimenti si richiede agli utenti di esportare/salvare il file come CSV prima di importarlo (percorso a costo zero, sempre disponibile).
- Autenticazione: sessioni PHP native + password con `password_hash()`/`password_verify()`. Nessun servizio esterno di auth (costo zero).

Alternative valutate e scartate (per completezza):
| Stack | Perché scartato |
|---|---|
| Node.js/Express + React | richiede hosting Node sempre attivo (VPS/PaaS), costo e manutenzione maggiori; non sfrutta l'hosting già disponibile |
| Python/Django | ottimo admin nativo, ma hosting WSGI raro e più costoso su provider italiani economici |
| Laravel + Filament | in astratto ideale (admin CRUD e import quasi gratis), ma richiede Composer/SSH e PHP ≥8.1 sull'hosting: da rivalutare come *upgrade path* se si verifica che l'hosting li supporta (vedi §11) |

### Frontend: **HTML + Tailwind CSS + Alpine.js + Leaflet.js**

Motivazione: nessun framework JS pesante (React/Vue) → i file si caricano così come sono su hosting condiviso, zero manutenzione lato server. Per il CSS si usa **Tailwind**, ma con un accorgimento importante per restare coerenti con "niente build step sull'hosting":
- **Modalità consigliata (produzione)**: Tailwind viene compilato **una volta, in locale** (sul computer di sviluppo, con Node usato solo come strumento di build, non come runtime del sito) generando un unico file `assets/css/tailwind.css` statico, minificato e "purgato" (solo le classi effettivamente usate). Quel file va poi caricato via FTP insieme al resto — l'hosting non deve avere Node, PHP basta come sempre. Si ricompila solo quando si cambia qualcosa nel markup/stile, non ad ogni richiesta.
- **Alternativa rapida per la fase di prototipazione**: Tailwind Play CDN (`<script src="https://cdn.tailwindcss.com">`), zero installazione, comodo per iterare velocemente sui mockup; da sostituire con la build compilata prima del rilascio in produzione (il CDN compila le classi nel browser ad ogni caricamento pagina, più pesante e non raccomandato da Tailwind stesso per siti pubblici).

**Alpine.js** (~15KB, un solo `<script>` da CDN o file locale, nessuna compilazione) gestisce in modo dichiarativo lo stato dell'interfaccia — filtri attivi, testo di ricerca, apertura/chiusura del pannello dettagli, toggle legenda — senza dover scrivere a mano `addEventListener`/manipolazione DOM sparsa, restando comunque leggibile da chiunque conosca HTML/JS di base (nessuna curva di apprendimento tipo React). Il markup resta HTML semantico "vero", compatibile con l'accessibilità (niente hydration, niente virtual DOM).

- **Leaflet.js**: libreria mappe open source, gratuita, nessuna API key richiesta, leggera, con buon supporto ad accessibilità/plugin da tastiera.
- **Tile provider (mappa di sfondo)**, in ordine di preferenza, tutti a costo zero:
  1. **CARTO Voyager/Positron** (quello usato nei mockup allegati) — stile pulito, gratuito per traffico moderato, richiede solo attribuzione.
  2. **OpenStreetMap standard tiles** — sempre gratuiti, nessuna registrazione, ma con policy d'uso da rispettare (no traffico intensivo).
  3. **MapTiler free tier** (100k tile/mese gratis, richiede una API key gratuita) — da tenere come piano B se il traffico dovesse crescere oltre i limiti "informali" di CARTO/OSM.
- Alpine.js gestisce anche la sincronizzazione tra mappa e vista lista (stessi filtri, stesso stato di ricerca), evitando di duplicare la logica in due punti.

### Costi ricorrenti stimati
- Hosting: quello già esistente (nessun costo aggiuntivo).
- Mappa/tiles: 0€ (CARTO/OSM gratuiti; eventuale MapTiler resta gratis fino a 100k tile/mese).
- Nessuna API a pagamento (niente Google Maps, niente geocoding a pagamento — se serve geocodificare indirizzi in fase di import si userà Nominatim, il geocoder gratuito di OSM, con rate limit da rispettare).

## 3. Modello dati (schema MySQL)

```
comuni
  id, nome, slug, colore_tema (opzionale, per branding)

utenti
  id, comune_id (NULL se super-admin), nome, email, password_hash,
  ruolo ENUM('admin_comune','superadmin'), attivo, creato_il

categorie
  id, nome, colore_hex, icona (nome icona/pin), ordine

punti_servizio
  id, comune_id, categoria_id, nome, indirizzo, lat, lng,
  descrizione, telefono, email, sito_web, immagine_url,
  creato_da (utente_id), aggiornato_il

orari_apertura
  id, punto_id, giorno_settimana (0-6), apertura, chiusura, chiuso (bool)
  -- oppure colonna JSON su punti_servizio, da decidere in fase di implementazione

import_log
  id, utente_id, nome_file, righe_importate, righe_errore, eseguito_il
```

Il layer dei **confini amministrativi** dei 3 comuni NON entra nel database: è un file GeoJSON statico (`/data/confini.geojson`) caricato da Leaflet come overlay. Scelta deliberata: permette di aggiungerlo in un secondo momento (come da vostra indicazione) senza toccare lo schema dati né il codice dell'applicazione. Quando sarà il momento, fonte gratuita consigliata: confini ISTAT (dataset open data dei limiti amministrativi comunali).

## 4. Permessi e ruoli

- **Admin di comune**: vede e modifica solo i punti servizio del proprio `comune_id`.
- **Superadmin**: vede e modifica i punti di tutti e 3 i comuni, gestisce anche utenti e categorie.
- Nessun flusso di approvazione in questa fase (le modifiche sono immediatamente pubbliche): è la scelta più semplice indicata. Se in futuro servisse una bozza/approvazione, si aggiungerà una colonna `stato` a `punti_servizio` senza impatti sul resto.

## 5. Funzionalità

### MVP (prima release)
- Mappa pubblica con marker colorati per categoria (icona/colore ≠ solo colore, per non escludere utenti daltonici — vedi §7).
- Pannello laterale dettagli al click su un marker (come da mockup): nome, indirizzo, orari, descrizione, contatti, pulsante "Portami qui" (link a Google/Apple Maps per il routing, che resta gratuito da usare come semplice link).
- Filtri: per comune (3 pulsanti/toggle) e per categoria (pulsanti colorati con legenda).
- **Ricerca avanzata multi-campo**: un'unica barra di ricerca che, mentre l'utente digita, filtra i punti su più campi contemporaneamente:
  - `nome` del punto servizio
  - `indirizzo`
  - `categoria`/area tematica (es. digitando "sociale" escono tutti i punti di quella categoria, come un filtro testuale equivalente ai pulsanti)
  - altri attributi testuali (`descrizione`, `comune`, eventuali tag/parole chiave aggiunti in fase di import)
  Tecnicamente: un match "un termine cerca in tutti i campi" (OR tra campi, AND tra parole se l'utente digita più parole), case-insensitive e senza accenti (es. "citta" trova "città"). Realizzata client-side in JS/Alpine: con poche centinaia di punti tutto il dataset viene caricato come JSON e filtrato in tempo reale, senza bisogno di un endpoint di ricerca lato server né di un motore di full-text esterno (costo zero, nessuna dipendenza). Se in futuro il numero di punti crescesse molto (migliaia), si potrà spostare il filtro lato server con `MySQL FULLTEXT INDEX` (nativo, nessun costo aggiuntivo) senza cambiare l'interfaccia.
- Vista lista/tabella alternativa alla mappa (requisito di accessibilità, vedi §7).
- Pannello admin: login, CRUD punti servizio (scoping per comune), gestione categorie (solo superadmin), import CSV.
- Pagina "Dichiarazione di accessibilità" (obbligatoria per siti PA).

### Roadmap successiva (non bloccante per l'MVP)
- Import .xlsx diretto (se l'hosting supporta Composer/PhpSpreadsheet).
- Layer confini comunali (appena disponibili i dati).
- Eventuale flusso bozza/approvazione.
- **Geocoding assistito in fase di import** (da indirizzo a lat/lng): riguarda solo il pannello admin, non la ricerca pubblica. Su cosa si basa: **Nominatim**, il servizio di geocoding gratuito di OpenStreetMap (nessuna API key, nessun costo). Funzionamento previsto: se in fase di import CSV una riga ha `indirizzo` ma non `lat`/`lng`, il sistema interroga Nominatim con l'indirizzo (comune + via) e propone le coordinate trovate; l'admin le vede a video e le conferma/corregge prima di salvare (mai salvataggio automatico "alla cieca", perché il geocoding di un indirizzo può essere ambiguo). Limiti da rispettare: policy d'uso di Nominatim (max 1 richiesta al secondo, uso non massivo/commerciale) — compatibile con un import occasionale di poche decine/centinaia di righe alla volta, non con geocoding in tempo reale su grandi volumi.

## 6. Accessibilità (AGID / WCAG 2.1 AA)

Punti critici specifici per un sito con mappa interattiva:
- **Non affidarsi al solo colore** per distinguere le categorie: ogni pin ha anche un'icona/forma diversa, e la legenda riporta testo oltre al colore.
- **Alternativa non cartografica obbligatoria**: la vista a lista/tabella (ordinabile, filtrabile con gli stessi controlli della mappa) garantisce l'accesso alle stesse informazioni a chi usa screen reader o non può interagire con la mappa.
- **Navigabilità da tastiera**: filtri, ricerca e apertura dei dettagli devono funzionare senza mouse (focus visibile, `tabindex` corretti, plugin Leaflet per marker raggiungibili da tastiera).
- **Markup semantico**: landmark ARIA, label sui campi di form/ricerca, testo alternativo su eventuali immagini dei punti servizio, contrasto colori verificato (compresi i colori delle categorie sulla mappa/legenda).
- **Dichiarazione di accessibilità** pubblicata (obbligo di legge per siti PA, Legge Stanca/AGID).

## 7. Struttura cartelle proposta

```
/public/                 <- document root del webserver
  index.php              <- pagina pubblica con mappa
  lista.php              <- vista tabellare alternativa
  accessibilita.php
  /assets/css/tailwind.css   <- compilato in locale, caricato già pronto
  /assets/js
  /admin/
    login.php, dashboard.php, punti.php, import.php, categorie.php
/app/
  /config/ (connessione db, config generale)
  /lib/ (funzioni auth, helper DB, parsing CSV)
/data/
  confini.geojson         <- da aggiungere in futuro
/sql/
  schema.sql               <- script di creazione tabelle
```

## 8. Punti aperti da verificare prima di iniziare lo sviluppo

1. **Versione PHP e disponibilità Composer/SSH** sull'hosting: da controllare (pannello hosting o supporto del provider) per confermare se restare su PHP "puro" o se conviene usare qualche libreria via Composer (es. PhpSpreadsheet per xlsx). Non blocca l'inizio: si parte comunque in PHP puro e si valuta dopo.
2. **Formato di partenza dei dati dei punti servizio**: non avete ancora un CSV di esempio. Prima di costruire l'importer definiamo insieme le colonne minime necessarie (proposta: `comune, categoria, nome, indirizzo, lat, lng, telefono, email, sito_web, descrizione, orari_lun...orari_ven, ecc.`).
3. **Confini comunali**: rimandato, verranno aggiunti come file GeoJSON quando disponibili (fonte gratuita di riferimento: ISTAT).
4. **Elenco categorie tematiche e colori**: da definire con voi (es. Anagrafe, Tributi, Sociale, Istruzione, Biblioteca, come nei mockup) — servirà una tabella colore↔categoria per la legenda.

## 9. Prossimi passi

1. Confermare/correggere questo documento.
2. Definire insieme lo schema colonne del CSV di esempio (punto 2 sopra) e l'elenco categorie/colori (punto 4).
3. Creare `sql/schema.sql` e la struttura cartelle.
4. Sviluppare l'MVP: mappa pubblica + filtri + ricerca + vista lista, poi pannello admin con login e CRUD, poi import CSV.
