<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/lib/helpers.php';
require_once __DIR__ . '/../app/lib/avvisi.php';

$avvisi = avvisiAttivi();
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mappa Servizi Comunali</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = { theme: { extend: { fontFamily: { sans: ['"Titillium Web"', 'Arial', 'sans-serif'], serif: ['Lora', 'Georgia', 'serif'] }, colors: { navy: '#00194B', primary: '#0066CC', 'primary-dark': '#003399' } } } };
</script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Titillium+Web:wght@400;600;700&family=Lora:wght@400;700&display=swap" rel="stylesheet">
<link rel="icon" href="/assets/img/punti-in-comune_logo.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="h-screen flex flex-col bg-white text-gray-900" x-data="mappaServizi()" x-init="init($refs.mappa)">

<a href="#contenuto" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:text-blue-800 focus:px-4 focus:py-2">Vai al contenuto</a>

<header class="bg-white shadow-sm z-20 relative">
  <div class="flex items-center gap-3 px-3 py-2 sm:px-4 sm:py-3">
    <a href="/index.php" class="flex items-center gap-3 shrink-0" aria-label="Punti in comune — Mappa servizi">
      <img src="/assets/img/punti-in-comune_logo.svg" alt="Punti in comune" class="h-10 sm:h-12 w-auto">
    </a>

    <!-- Ricerca con autocompletamento (combobox accessibile) -->
    <div class="flex-1 min-w-0 flex justify-center items-center">
    <div class="relative w-full sm:w-1/2 min-w-0" @click.outside="chiudiSuggerimenti()">
      <label class="sr-only" for="ricerca">Cerca ufficio o servizio per nome, indirizzo o categoria</label>
      <input id="ricerca" type="text" x-model="ricerca"
             role="combobox" aria-autocomplete="list" aria-controls="lista-suggerimenti"
             :aria-expanded="suggerimentiAperti" :aria-activedescendant="indiceEvidenziato > -1 ? 'suggerimento-' + indiceEvidenziato : null"
             @input="onRicercaInput()" @focus="ricerca && (suggerimentiAperti = true)"
             @keydown.down.prevent="spostaEvidenziazione(1)"
             @keydown.up.prevent="spostaEvidenziazione(-1)"
             @keydown.enter.prevent="confermaEvidenziato()"
             @keydown.escape="chiudiSuggerimenti()"
             autocomplete="off"
             placeholder="Cerca per nome, indirizzo, categoria..."
             class="w-full border rounded-full px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-600">

      <ul x-show="suggerimentiAperti && suggerimenti.length" id="lista-suggerimenti" role="listbox"
          class="absolute z-30 mt-1 w-full bg-white rounded-lg shadow-lg border max-h-72 overflow-auto">
        <template x-for="(s, i) in suggerimenti" :key="s.id">
          <li :id="'suggerimento-' + i" role="option" :aria-selected="i === indiceEvidenziato">
            <button type="button" @click="selezionaSuggerimento(s)" @mouseenter="indiceEvidenziato = i"
                    :class="i === indiceEvidenziato ? 'bg-gray-100' : ''"
                    class="w-full text-left px-3 py-2">
              <span class="block text-sm font-medium" x-text="s.nome"></span>
              <span class="block text-xs text-gray-500" x-text="s.categoria_nome + ' · ' + s.comune_nome"></span>
            </button>
          </li>
        </template>
      </ul>
      <p x-show="suggerimentiAperti && ricerca.trim() && !suggerimenti.length"
         class="absolute z-30 mt-1 w-full bg-white rounded-lg shadow-lg border px-3 py-2 text-sm text-gray-500">
        Nessun risultato per "<span x-text="ricerca"></span>"
      </p>
    </div>
    <button type="button" @click="geolocalizza()" aria-label="Mostra la mia posizione sulla mappa" title="Mostra la mia posizione" class="shrink-0 ml-3 w-10 h-10 flex items-center justify-center rounded-full border border-gray-300 bg-white text-navy hover:bg-gray-100">
      <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="7"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="2" fill="currentColor"/></svg>
    </button>
    </div>

    <!-- Filtri: pannello laterale (desktop) o foglio dal basso (mobile) -->
    <button type="button" @click="filtriAperti = true" :aria-expanded="filtriAperti" aria-controls="pannello-filtri"
            class="relative shrink-0 border rounded-full px-3 sm:px-4 py-2 text-sm font-medium bg-white hover:bg-gray-100">
      <span>Filtri</span>
      <span x-show="numeroFiltriAttivi" x-text="numeroFiltriAttivi"
            class="absolute -top-1.5 -right-1.5 bg-primary text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"></span>
    </button>
  </div>

</header>

<?php if ($avvisi): $totaleAvvisi = count($avvisi); ?>
<section x-data="bannerAvvisi(<?= $totaleAvvisi ?>)" @mouseenter="sospeso = true" @mouseleave="sospeso = false"
         @focusin="sospeso = true" @focusout="sospeso = false"
         aria-roledescription="carosello" aria-label="Avvisi"
         class="relative z-10 bg-[#EEF4FB] border-b border-[#D3E2F3] text-navy">
  <div class="flex items-center gap-2 px-3 sm:px-4 py-1.5">
    <div class="flex-1 min-w-0 overflow-hidden">
      <ul class="flex transition-transform duration-500 ease-in-out motion-reduce:transition-none"
          :style="`transform: translateX(-${attivo * 100}%)`" aria-live="off">
        <?php foreach ($avvisi as $i => $a): $tipo = TIPI_AVVISO[$a['tipo']] ?? TIPI_AVVISO['info']; ?>
          <li class="w-full shrink-0 flex items-center gap-2.5 min-w-0"
              role="group" aria-roledescription="avviso" aria-label="<?= $i + 1 ?> di <?= $totaleAvvisi ?>"
              <?php if ($i > 0): ?>inert aria-hidden="true"<?php endif; ?>
              :inert="attivo !== <?= $i ?>" :aria-hidden="attivo !== <?= $i ?> ? 'true' : 'false'">
            <span class="shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-white" style="background:<?= h($tipo['colore']) ?>">
              <?= svgIconaAvviso($a['tipo']) ?>
            </span>
            <p class="min-w-0 text-sm leading-snug line-clamp-2 sm:line-clamp-1">
              <span class="sr-only"><?= h($tipo['etichetta']) ?>: </span>
              <?php if ($a['comune_nome']): ?><strong class="font-semibold"><?= h($a['comune_nome']) ?> ·</strong><?php endif; ?>
              <?php if ($a['link_url']): ?>
                <a href="<?= h($a['link_url']) ?>" target="_blank" rel="noopener" class="underline underline-offset-2 hover:text-primary"><?= h($a['testo']) ?><span class="sr-only"> (si apre in una nuova scheda)</span></a>
              <?php else: ?>
                <?= h($a['testo']) ?>
              <?php endif; ?>
            </p>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <?php if ($totaleAvvisi > 1): ?>
      <div class="shrink-0 flex items-center sm:gap-0.5 -mr-1.5 sm:mr-0 text-navy">
        <button type="button" @click="precedente()" aria-label="Avviso precedente" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-white">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m15 6-6 6 6 6"/></svg>
        </button>
        <span class="hidden sm:inline text-xs tabular-nums w-8 text-center" aria-hidden="true"><span x-text="attivo + 1">1</span>/<?= $totaleAvvisi ?></span>
        <button type="button" @click="successivo()" aria-label="Avviso successivo" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-white">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m9 6 6 6-6 6"/></svg>
        </button>
        <button type="button" @click="inPausa = !inPausa" :aria-label="inPausa ? 'Avvia scorrimento automatico degli avvisi' : 'Metti in pausa lo scorrimento degli avvisi'"
                aria-label="Metti in pausa lo scorrimento degli avvisi" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-white">
          <svg x-show="!inPausa" class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M7 5h3v14H7zM14 5h3v14h-3z"/></svg>
          <svg x-show="inPausa" x-cloak class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M8 5v14l11-7z"/></svg>
        </button>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<main id="contenuto" class="flex-1 flex overflow-hidden relative">
  <div class="relative flex-1">
    <div x-ref="mappa" id="mappa" class="absolute inset-0" role="application" aria-label="Mappa dei servizi comunali"></div>

    <p x-show="statoGeo" x-text="statoGeo" role="status" class="absolute top-3 left-3 bg-white shadow rounded px-3 py-2 text-sm text-gray-700"></p>
    <p x-show="caricamentoFallito" class="absolute inset-x-0 top-0 bg-red-100 text-red-800 text-sm px-4 py-2">
      Non è stato possibile caricare i dati dei servizi. Riprova più tardi.
    </p>
    <p x-show="!caricamentoFallito && puntiFiltrati.length === 0" class="absolute top-3 left-1/2 -translate-x-1/2 bg-white shadow rounded-full px-4 py-2 text-sm text-gray-600">
      Nessun servizio corrisponde ai filtri attuali.
    </p>
  </div>

  <!-- Sfondo scuro dietro al pannello dettagli su mobile (bottom sheet) -->
  <div x-show="selezionato" @click="chiudiDettaglio()" class="fixed inset-0 bg-black/30 z-30 sm:hidden"></div>

  <aside :class="selezionato ? 'fixed inset-x-0 bottom-0 z-40 max-h-[75vh] rounded-t-2xl shadow-lg' : 'hidden sm:block'"
         class="w-full sm:w-96 sm:static sm:max-h-none sm:rounded-none sm:shadow-none shrink-0 border-l bg-white overflow-y-auto"
         aria-live="polite">
    <div class="flex justify-center py-2 sm:hidden">
      <span class="w-10 h-1.5 bg-gray-300 rounded-full"></span>
    </div>

    <template x-if="!selezionato">
      <div class="p-4">
        <p class="text-sm text-gray-600 mb-2" x-text="puntiFiltrati.length + ' servizi'"></p>
        <ul class="divide-y border-t">
          <template x-for="p in puntiFiltrati" :key="p.id">
            <li>
              <button type="button" @click="seleziona(p)" class="w-full text-left flex items-start gap-3 py-3 px-2 hover:bg-gray-100">
                <span class="shrink-0 mt-0.5 w-9 h-9 rounded-full flex items-center justify-center" :style="`background:${p.colore_hex}`" x-html="svgIconaCategoria(p)"></span>
                <span class="min-w-0">
                  <span class="block text-sm font-semibold text-navy" x-text="p.nome"></span>
                  <span class="inline-block mt-1 text-xs font-semibold px-2 py-0.5 rounded-full" :style="`background:${p.colore_hex};color:${coloreTesto(p.colore_hex)}`" x-text="p.categoria_nome"></span>
                  <span class="block mt-1 text-xs text-gray-600" x-text="p.comune_nome"></span>
                </span>
              </button>
            </li>
          </template>
        </ul>
      </div>
    </template>
    <template x-if="selezionato">
      <div class="p-5 pt-0 sm:pt-5">
        <div class="flex items-start justify-between gap-2">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="inline-block text-xs font-semibold px-2 py-1 rounded"
                  :style="`background:${selezionato.colore_hex}20; color:${selezionato.colore_hex}`"
                  x-text="selezionato.categoria_nome"></span>
            <span x-show="selezionato.sovracomunale" class="inline-block text-xs font-semibold px-2 py-1 rounded bg-gray-100 text-gray-800">Sovracomunale</span>
          </div>
          <button type="button" @click="chiudiDettaglio()" aria-label="Chiudi dettagli" class="text-gray-400 hover:text-gray-700">×</button>
        </div>
        <h2 class="mt-2 text-xl font-semibold" x-text="selezionato.nome"></h2>
        <p class="text-sm text-gray-600 mt-1"><span x-text="selezionato.indirizzo + ', ' + selezionato.comune_nome"></span></p>

        <template x-if="selezionato.orario_testo">
          <div class="mt-4">
            <h3 class="text-xs font-semibold uppercase text-gray-500">Orari di apertura</h3>
            <p class="mt-1 text-sm whitespace-pre-line" x-text="selezionato.orario_testo"></p>
          </div>
        </template>
        <template x-if="!selezionato.orario_testo && selezionato.orari.length">
          <div class="mt-4">
            <h3 class="text-xs font-semibold uppercase text-gray-500">Orari di apertura</h3>
            <dl class="mt-1 text-sm">
              <template x-for="o in selezionato.orari" :key="o.giorno">
                <div class="flex justify-between py-0.5">
                  <dt x-text="nomiGiorni[o.giorno]"></dt>
                  <dd :class="o.chiuso ? 'text-gray-400 italic' : ''" x-text="o.chiuso ? 'Chiuso' : o.apertura + ' - ' + o.chiusura"></dd>
                </div>
              </template>
            </dl>
          </div>
        </template>

        <p class="mt-4 text-sm whitespace-pre-line" x-text="selezionato.descrizione"></p>

        <div class="mt-4 text-sm space-y-1">
          <p x-show="selezionato.telefono">Tel. <span x-text="selezionato.telefono"></span></p>
          <p x-show="selezionato.email">Email: <a class="text-blue-800 underline" :href="'mailto:' + selezionato.email" x-text="selezionato.email"></a></p>
        </div>

        <div class="mt-5 space-y-2 pb-4">
          <a :href="linkIndicazioni(selezionato)" target="_blank" rel="noopener"
             class="block text-center bg-primary text-white hover:bg-primary-dark rounded-md py-2 text-sm font-medium">Indicazioni stradali</a>
          <a x-show="selezionato.sito_web" :href="selezionato.sito_web" target="_blank" rel="noopener"
             class="block text-center border rounded-md py-2 text-sm font-medium text-gray-700">Sito del servizio</a>
        </div>
      </div>
    </template>
  </aside>
</main>

<footer class="border-t bg-white text-xs text-gray-600">
  <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-1.5 sm:px-4">
    <span>Punti in comune · Farra di Soligo, Pieve di Soligo, Sernaglia della Battaglia</span>
    <nav aria-label="Informazioni legali" class="flex gap-4">
      <a href="/privacy.php" class="underline hover:text-primary">Privacy policy</a>
      <a href="/cookie.php" class="underline hover:text-primary">Cookie policy</a>
      <a href="/accessibilita.php" class="underline hover:text-primary">Accessibilità</a>
    </nav>
  </div>
</footer>

<div x-cloak class="fixed inset-0 z-[1200] pointer-events-none" @keydown.escape.window="filtriAperti = false">
  <aside x-show="filtriAperti" id="pannello-filtri" role="dialog" aria-modal="true" aria-labelledby="titolo-filtri"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-y-full sm:translate-y-0 sm:translate-x-full"
         x-transition:enter-end="translate-y-0 sm:translate-x-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-y-0 sm:translate-x-0"
         x-transition:leave-end="translate-y-full sm:translate-y-0 sm:translate-x-full"
         class="pointer-events-auto absolute inset-y-0 right-0 w-full bg-white shadow-lg flex flex-col sm:w-96">
      <div class="flex items-center justify-between px-5 py-4 border-b">
        <h2 id="titolo-filtri" class="text-lg font-semibold text-navy">Filtri</h2>
        <button type="button" @click="filtriAperti = false" class="text-sm text-primary hover:underline">Chiudi</button>
      </div>

      <div class="flex-1 overflow-y-auto px-5 py-4 space-y-6">
        <fieldset>
          <legend class="text-xs font-semibold uppercase tracking-wide text-gray-600 mb-3">Comune</legend>
          <div class="space-y-2">
            <template x-for="c in comuni" :key="c.id">
              <label class="flex items-center gap-3 text-sm cursor-pointer">
                <input type="checkbox" :checked="filtroComuni.includes(c.id)" @change="toggleFiltro('filtroComuni', c.id)"
                       class="w-4 h-4 rounded border-gray-400 text-primary">
                <span x-text="c.nome"></span>
              </label>
            </template>
          </div>
        </fieldset>

        <fieldset>
          <legend class="text-xs font-semibold uppercase tracking-wide text-gray-600 mb-3">Area tematica</legend>
          <div class="space-y-2">
            <template x-for="cat in categorie" :key="cat.id">
              <label class="flex items-center gap-3 text-sm cursor-pointer">
                <input type="checkbox" :checked="filtroCategorie.includes(cat.id)" @change="toggleFiltro('filtroCategorie', cat.id)"
                       class="w-4 h-4 rounded border-gray-400 text-primary">
                <span class="inline-block w-3 h-3 rounded-full shrink-0" :style="`background:${cat.colore_hex}`"></span>
                <span x-text="cat.nome"></span>
              </label>
            </template>
          </div>
        </fieldset>

        <fieldset x-show="confiniDisponibili">
          <legend class="text-xs font-semibold uppercase tracking-wide text-gray-600 mb-3">Opzioni</legend>
          <label class="flex items-center gap-3 text-sm cursor-pointer">
            <input type="checkbox" checked @change="toggleConfini($event.target.checked)" class="w-4 h-4 rounded border-gray-400 text-primary">
            Mostra confini comunali
          </label>
        </fieldset>
      </div>

      <div class="px-5 py-4 border-t">
        <button type="button" @click="azzeraFiltri()" class="w-full border border-gray-400 rounded py-2 text-sm font-medium hover:bg-gray-100">Azzera filtri</button>
      </div>
    </aside>
  </div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script src="/assets/js/app.js"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js" defer></script>
</body>
</html>
