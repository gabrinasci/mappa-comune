<?php require_once __DIR__ . '/../app/config/config.php'; ?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mappa Servizi Comunali</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="h-screen flex flex-col bg-gray-50 text-gray-900" x-data="mappaServizi()" x-init="init($refs.mappa)">

<a href="#contenuto" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:text-blue-800 focus:px-4 focus:py-2">Vai al contenuto</a>

<header class="bg-white border-b shadow-sm z-20 relative">
  <div class="flex items-center gap-2 px-3 py-2 sm:px-4 sm:py-3">
    <h1 class="text-base sm:text-lg font-semibold text-blue-900 shrink-0">📍 <span class="hidden xs:inline">Mappa Servizi</span></h1>

    <!-- Ricerca con autocompletamento (combobox accessibile) -->
    <div class="relative flex-1 min-w-0" @click.outside="chiudiSuggerimenti()">
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
                    :class="i === indiceEvidenziato ? 'bg-blue-50' : ''"
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

    <!-- Pulsante filtri (comune + categoria), sostituisce le chip -->
    <div class="relative shrink-0" @click.outside="filtriAperti = false">
      <button type="button" @click="filtriAperti = !filtriAperti" :aria-expanded="filtriAperti" aria-haspopup="true"
              class="relative flex items-center gap-1.5 border rounded-full px-3 sm:px-4 py-2 text-sm font-medium bg-white">
        <span aria-hidden="true">▤</span> <span class="hidden sm:inline">Filtri</span>
        <span x-show="numeroFiltriAttivi" x-text="numeroFiltriAttivi"
              class="absolute -top-1.5 -right-1.5 bg-blue-800 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"></span>
      </button>

      <div x-show="filtriAperti" @click="filtriAperti = false" class="fixed inset-0 bg-black/30 z-30 sm:hidden"></div>

      <div x-show="filtriAperti"
           class="fixed inset-x-0 bottom-0 z-40 bg-white rounded-t-2xl shadow-lg p-4 max-h-[75vh] overflow-y-auto
                  sm:absolute sm:inset-x-auto sm:bottom-auto sm:top-full sm:right-0 sm:mt-2 sm:w-80 sm:rounded-lg sm:max-h-[70vh]">
        <div class="flex items-center justify-between mb-3 sm:hidden">
          <span class="font-semibold">Filtri</span>
          <button type="button" @click="filtriAperti = false" aria-label="Chiudi filtri" class="text-gray-400">✕</button>
        </div>

        <fieldset class="mb-4">
          <legend class="text-xs font-semibold uppercase text-gray-500 mb-2">Comune</legend>
          <div class="space-y-1">
            <template x-for="c in comuni" :key="c.id">
              <label class="flex items-center gap-2 text-sm py-1 cursor-pointer">
                <input type="checkbox" :checked="filtroComuni.includes(c.id)" @change="toggleFiltro('filtroComuni', c.id)"
                       class="rounded border-gray-300">
                <span x-text="c.nome"></span>
              </label>
            </template>
          </div>
        </fieldset>

        <fieldset class="mb-4">
          <legend class="text-xs font-semibold uppercase text-gray-500 mb-2">Area tematica</legend>
          <div class="space-y-1">
            <template x-for="cat in categorie" :key="cat.id">
              <label class="flex items-center gap-2 text-sm py-1 cursor-pointer">
                <input type="checkbox" :checked="filtroCategorie.includes(cat.id)" @change="toggleFiltro('filtroCategorie', cat.id)"
                       class="rounded border-gray-300">
                <span class="inline-block w-3 h-3 rounded-full shrink-0" :style="`background:${cat.colore_hex}`"></span>
                <span x-text="cat.nome"></span>
              </label>
            </template>
          </div>
        </fieldset>

        <label class="flex items-center gap-2 text-sm mb-4 cursor-pointer" x-show="confiniDisponibili">
          <input type="checkbox" @change="toggleConfini($event.target.checked)" class="rounded border-gray-300">
          Mostra confini comunali
        </label>

        <div class="flex gap-2">
          <button type="button" @click="azzeraFiltri()" class="flex-1 border rounded-lg py-2 text-sm font-medium">Azzera</button>
          <button type="button" @click="filtriAperti = false" class="flex-1 bg-blue-800 text-white rounded-lg py-2 text-sm font-medium">Applica</button>
        </div>
      </div>
    </div>
  </div>
</header>

<main id="contenuto" class="flex-1 flex overflow-hidden relative">
  <div class="relative flex-1">
    <div x-ref="mappa" id="mappa" class="absolute inset-0" role="application" aria-label="Mappa dei servizi comunali"></div>

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
      <p class="p-6 text-sm text-gray-500">Clicca un pin sulla mappa, oppure cerca un servizio, per vedere i dettagli.</p>
    </template>
    <template x-if="selezionato">
      <div class="p-5 pt-0 sm:pt-5">
        <div class="flex items-start justify-between gap-2">
          <span class="inline-block text-xs font-semibold px-2 py-1 rounded"
                :style="`background:${selezionato.colore_hex}20; color:${selezionato.colore_hex}`"
                x-text="selezionato.categoria_nome"></span>
          <button type="button" @click="chiudiDettaglio()" aria-label="Chiudi dettagli" class="text-gray-400 hover:text-gray-700">✕</button>
        </div>
        <h2 class="mt-2 text-xl font-semibold" x-text="selezionato.nome"></h2>
        <p class="text-sm text-gray-600 mt-1">📍 <span x-text="selezionato.indirizzo + ', ' + selezionato.comune_nome"></span></p>

        <template x-if="selezionato.orari.length">
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
          <p x-show="selezionato.telefono">☎️ <span x-text="selezionato.telefono"></span></p>
          <p x-show="selezionato.email">✉️ <a class="text-blue-800 underline" :href="'mailto:' + selezionato.email" x-text="selezionato.email"></a></p>
        </div>

        <div class="mt-5 space-y-2 pb-4">
          <a :href="linkIndicazioni(selezionato)" target="_blank" rel="noopener"
             class="block text-center bg-blue-800 text-white rounded-md py-2 text-sm font-medium">↗ Portami qui</a>
          <a x-show="selezionato.sito_web" :href="selezionato.sito_web" target="_blank" rel="noopener"
             class="block text-center border rounded-md py-2 text-sm font-medium text-gray-700">ⓘ Scopri di più</a>
        </div>
      </div>
    </template>
  </aside>
</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/assets/js/app.js"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js" defer></script>
</body>
</html>
