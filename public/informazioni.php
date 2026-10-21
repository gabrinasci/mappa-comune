<?php
require_once __DIR__ . '/../app/config/db.php';
require_once __DIR__ . '/../app/lib/helpers.php';

// Portali istituzionali dei comuni aderenti, per slug (il comune compare comunque anche senza link).
const SITI_COMUNI = [
    'farra-di-soligo' => 'https://www.comune.farradisoligo.tv.it/',
    'pieve-di-soligo' => 'https://www.comune.pievedisoligo.tv.it/',
    'refrontolo' => 'https://www.comune.refrontolo.tv.it/',
    'sernaglia-della-battaglia' => 'https://www.comune.sernaglia.tv.it/',
];

try {
    $comuni = db()->query('SELECT nome, slug FROM comuni ORDER BY nome')->fetchAll();
    $categorie = db()->query('SELECT nome, colore_hex FROM categorie ORDER BY ordine')->fetchAll();
} catch (PDOException $e) {
    error_log('Pagina informazioni: ' . $e->getMessage());
    $comuni = [];
    $categorie = [];
}

function icona(string $path): string
{
    return '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
}

$passi = [
    ['Cerca', 'Scrivi nella barra in alto il nome di un ufficio, un indirizzo o un argomento (es. "anagrafe", "biblioteca"). I risultati compaiono mentre scrivi.', '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>'],
    ['Filtra', 'Con il pulsante Filtri scegli uno o più comuni e le aree tematiche che ti interessano. I servizi sovracomunali restano sempre visibili.', '<path d="M3 5h18M6 12h12M10 19h4"/>'],
    ['Apri il dettaglio', 'Tocca un punto sulla mappa o un servizio dell\'elenco per vedere foto, indirizzo, orari, descrizione e contatti.', '<path d="M12 21s-7-6.2-7-11a7 7 0 1 1 14 0c0 4.8-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/>'],
    ['Chiama o raggiungi', 'Dal dettaglio puoi chiamare l\'ufficio con un tocco, scrivere un\'email o aprire le indicazioni stradali nella tua app di navigazione.', '<path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25c1.12.37 2.33.57 3.6.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1L6.6 10.8Z"/>'],
    ['Trova i servizi vicini', 'Il pulsante con il mirino mostra la tua posizione sulla mappa, se dai il consenso al browser. La posizione non viene salvata né inviata al Comune.', '<circle cx="12" cy="12" r="7"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="1.5"/>'],
    ['Semplifica la mappa', 'Nei Filtri puoi nascondere negozi, locali e altri punti di interesse dello sfondo, per vedere solo i servizi comunali.', '<path d="M3 6l6-3 6 3 6-3v15l-6 3-6-3-6 3Z"/><path d="M9 3v15M15 6v15"/>'],
];
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Il servizio — Punti in comune</title>
<meta name="description" content="Che cos'è Punti in comune, come usare la mappa dei servizi comunali e i link ai portali dei comuni aderenti.">
<?= stiliComuni() ?>
</head>
<body class="bg-white text-gray-900 font-sans">

<a href="#contenuto" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:text-blue-800 focus:px-4 focus:py-2">Vai al contenuto</a>

<!-- Testata istituzionale "slim" (modello Designers Italia) -->
<div class="bg-navy text-white text-xs sm:text-sm">
  <div class="max-w-5xl mx-auto px-4 py-2 truncate">
    Comuni di <?= h(implode(', ', array_column($comuni, 'nome')) ?: 'Farra di Soligo, Pieve di Soligo, Refrontolo, Sernaglia della Battaglia') ?>
  </div>
</div>

<header class="bg-white border-b">
  <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
    <a href="/index.php" class="flex items-center gap-3" aria-label="Punti in comune — torna alla mappa">
      <img src="/assets/img/punti-in-comune_logo.svg" alt="Punti in comune" class="h-12 w-auto">
    </a>
    <a href="/index.php" class="inline-flex items-center gap-2 bg-primary hover:bg-primary-dark text-white rounded-md px-4 py-2 text-sm font-semibold">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 6l6-3 6 3 6-3v15l-6 3-6-3-6 3Z"/><path d="M9 3v15M15 6v15"/></svg>
      Apri la mappa
    </a>
  </div>
</header>

<main id="contenuto">
  <div class="max-w-5xl mx-auto px-4">
    <nav aria-label="Percorso di navigazione" class="pt-5 text-sm">
      <ol class="flex flex-wrap items-center gap-2 text-gray-600">
        <li><a href="/index.php" class="text-primary underline underline-offset-2">Mappa</a></li>
        <li aria-hidden="true">/</li>
        <li aria-current="page">Il servizio</li>
      </ol>
    </nav>

    <!-- Introduzione -->
    <section class="py-8 sm:py-12 grid gap-8 md:grid-cols-[1fr_auto] md:items-center" aria-labelledby="titolo-pagina">
      <div>
        <p class="text-sm font-semibold uppercase tracking-wider text-primary">Il servizio</p>
        <h1 id="titolo-pagina" class="mt-2 text-3xl sm:text-4xl font-bold text-navy leading-tight">Punti in comune: i servizi dei comuni su un'unica mappa</h1>
        <p class="mt-4 text-lg text-gray-700 max-w-2xl">Uffici, sportelli, scuole, biblioteche e servizi sociali e sanitari dei comuni aderenti, raccolti in un solo posto. Trovi dove si trovano, quando sono aperti e come contattarli, senza dover cercare sui siti dei singoli comuni.</p>
        <div class="mt-6 flex flex-wrap gap-3">
          <a href="/index.php" class="inline-flex items-center bg-primary hover:bg-primary-dark text-white rounded-md px-5 py-2.5 font-semibold">Vai alla mappa</a>
          <a href="/lista.php" class="inline-flex items-center border-2 border-primary text-primary hover:bg-blue-50 rounded-md px-5 py-2 font-semibold">Consulta l'elenco</a>
        </div>
      </div>
      <img src="/assets/img/punti-in-comune_logo.svg" alt="" class="hidden md:block w-56 h-auto">
    </section>
  </div>

  <!-- Il progetto -->
  <section class="bg-slate-50 border-y" aria-labelledby="titolo-progetto">
    <div class="max-w-5xl mx-auto px-4 py-10 sm:py-12 grid gap-8 md:grid-cols-2">
      <div>
        <h2 id="titolo-progetto" class="text-2xl font-bold text-navy">Il progetto</h2>
        <p class="mt-3 text-gray-700">Punti in comune nasce dalla collaborazione tra comuni vicini che offrono servizi spesso usati da cittadini di tutto il territorio. La mappa li mette insieme, con le stesse informazioni e lo stesso aspetto per tutti.</p>
        <p class="mt-3 text-gray-700">I dati sono inseriti e aggiornati direttamente dagli uffici di ciascun comune: ogni comune cura le schede dei propri servizi.</p>
      </div>
      <div>
        <h3 class="text-lg font-semibold text-navy">Cosa trovi sulla mappa</h3>
        <?php if ($categorie): ?>
          <ul class="mt-3 flex flex-wrap gap-2" aria-label="Aree tematiche">
            <?php foreach ($categorie as $c): ?>
              <li class="inline-flex items-center gap-2 bg-white border rounded-full px-3 py-1 text-sm font-semibold">
                <span class="w-3 h-3 rounded-full shrink-0" style="background:<?= h($c['colore_hex']) ?>" aria-hidden="true"></span>
                <?= h($c['nome']) ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <p class="mt-4 text-gray-700">Per ogni servizio: indirizzo, orari di apertura, descrizione, telefono, email e sito web, quando disponibili. In alto nella mappa compaiono anche gli avvisi su cambi di orario e novità.</p>
      </div>
    </div>
  </section>

  <!-- Come funziona -->
  <section class="max-w-5xl mx-auto px-4 py-10 sm:py-12" aria-labelledby="titolo-funzionamento">
    <h2 id="titolo-funzionamento" class="text-2xl font-bold text-navy">Come funziona la mappa</h2>
    <ul class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($passi as [$titolo, $testo, $path]): ?>
        <li class="bg-white border rounded-lg shadow-sm p-5">
          <span class="inline-flex w-11 h-11 items-center justify-center rounded-full bg-blue-50 text-primary"><?= icona($path) ?></span>
          <h3 class="mt-3 text-lg font-semibold text-navy"><?= h($titolo) ?></h3>
          <p class="mt-1 text-gray-700"><?= h($testo) ?></p>
        </li>
      <?php endforeach; ?>
    </ul>
    <div class="mt-6 border-l-4 border-primary bg-blue-50 px-5 py-4 rounded-r-lg">
      <h3 class="font-semibold text-navy">Preferisci un elenco?</h3>
      <p class="mt-1 text-gray-700">Tutti i servizi sono consultabili anche in forma di <a href="/lista.php" class="text-primary font-semibold underline underline-offset-2">elenco testuale</a>, con gli stessi filtri e la stessa ricerca della mappa: è pensato anche per chi usa la tastiera o un lettore di schermo.</p>
    </div>
  </section>

  <!-- Comuni coinvolti -->
  <section class="bg-slate-50 border-y" aria-labelledby="titolo-comuni">
    <div class="max-w-5xl mx-auto px-4 py-10 sm:py-12">
      <h2 id="titolo-comuni" class="text-2xl font-bold text-navy">I comuni coinvolti</h2>
      <p class="mt-2 text-gray-700">Per pratiche, modulistica e notizie ufficiali fa sempre fede il portale istituzionale di ciascun comune.</p>
      <ul class="mt-6 grid gap-4 sm:grid-cols-2">
        <?php foreach ($comuni as $c): $sito = SITI_COMUNI[$c['slug']] ?? null; ?>
          <li class="bg-white border rounded-lg shadow-sm p-5 flex flex-col">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Comune di</p>
            <h3 class="text-xl font-semibold text-navy"><?= h($c['nome']) ?></h3>
            <?php if ($sito): ?>
              <a href="<?= h($sito) ?>" target="_blank" rel="noopener" class="mt-4 inline-flex items-center gap-1.5 self-start text-primary font-semibold underline underline-offset-2 hover:text-primary-dark">
                Vai al sito istituzionale
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>
                <span class="sr-only">(si apre in una nuova scheda)</span>
              </a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <!-- Segnalazioni -->
  <section class="max-w-5xl mx-auto px-4 py-10 sm:py-12" aria-labelledby="titolo-segnalazioni">
    <h2 id="titolo-segnalazioni" class="text-2xl font-bold text-navy">Hai trovato un'informazione da correggere?</h2>
    <p class="mt-3 text-gray-700 max-w-3xl">Orari e contatti possono cambiare. Se un dato non è aggiornato, segnalalo all'ufficio del comune interessato attraverso i contatti del suo sito istituzionale: le schede vengono corrette direttamente dal personale comunale.</p>
  </section>
</main>

<footer class="bg-navy text-white">
  <div class="max-w-5xl mx-auto px-4 py-8 grid gap-6 sm:grid-cols-2">
    <div>
      <img src="/assets/img/punti-in-comune_logo.svg" alt="" class="h-10 w-auto bg-white rounded p-1">
      <p class="mt-3 text-sm text-blue-100">Punti in comune · mappa dei servizi comunali</p>
    </div>
    <nav aria-label="Link utili" class="text-sm">
      <ul class="flex flex-col gap-2 sm:items-end">
        <li><a href="/index.php" class="underline hover:text-blue-200">Mappa dei servizi</a></li>
        <li><a href="/lista.php" class="underline hover:text-blue-200">Elenco dei servizi</a></li>
        <li><a href="/accessibilita.php" class="underline hover:text-blue-200">Dichiarazione di accessibilità</a></li>
        <li><a href="/privacy.php" class="underline hover:text-blue-200">Privacy policy</a></li>
        <li><a href="/cookie.php" class="underline hover:text-blue-200">Cookie policy</a></li>
      </ul>
    </nav>
  </div>
</footer>
</body>
</html>
