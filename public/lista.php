<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Elenco Servizi Comunali</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-900" x-data="mappaServizi()" x-init="init(null)">

<a href="#contenuto" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:text-blue-800 focus:px-4 focus:py-2">Vai al contenuto</a>

<header class="bg-white border-b shadow-sm sticky top-0 z-20">
  <div class="flex items-center gap-2 px-3 py-2 sm:px-4 sm:py-3 max-w-5xl mx-auto">
    <h1 class="text-base sm:text-lg font-semibold text-blue-900 shrink-0">📍 <span class="hidden xs:inline">Elenco Servizi</span></h1>

    <label class="sr-only" for="ricerca">Cerca ufficio o servizio</label>
    <input id="ricerca" type="text" x-model="ricerca" @input="aggiornaMarkers()"
           placeholder="Cerca per nome, indirizzo, categoria..."
           class="flex-1 min-w-0 border rounded-full px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-600">

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
                <input type="checkbox" :checked="filtroComuni.includes(c.id)" @change="toggleFiltro('filtroComuni', c.id)" class="rounded border-gray-300">
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
                <input type="checkbox" :checked="filtroCategorie.includes(cat.id)" @change="toggleFiltro('filtroCategorie', cat.id)" class="rounded border-gray-300">
                <span class="inline-block w-3 h-3 rounded-full shrink-0" :style="`background:${cat.colore_hex}`"></span>
                <span x-text="cat.nome"></span>
              </label>
            </template>
          </div>
        </fieldset>
        <div class="flex gap-2">
          <button type="button" @click="azzeraFiltri()" class="flex-1 border rounded-lg py-2 text-sm font-medium">Azzera</button>
          <button type="button" @click="filtriAperti = false" class="flex-1 bg-blue-800 text-white rounded-lg py-2 text-sm font-medium">Applica</button>
        </div>
      </div>
    </div>

    <a href="/index.php" aria-label="Torna alla mappa" class="shrink-0 text-lg sm:text-sm sm:text-blue-800 sm:underline">
      🗺️<span class="hidden sm:inline"> Mappa</span>
    </a>
  </div>
</header>

<main id="contenuto" class="max-w-5xl mx-auto px-3 sm:px-4 py-6">
  <p x-show="caricamentoFallito" class="bg-red-100 text-red-800 text-sm px-4 py-2 rounded mb-4">
    Non è stato possibile caricare i dati dei servizi. Riprova più tardi.
  </p>
  <p class="text-sm text-gray-500 mb-3" x-text="puntiFiltrati.length + ' servizi trovati'"></p>

  <!-- Card su mobile, tabella da sm in su -->
  <ul class="sm:hidden space-y-2">
    <template x-for="p in puntiFiltrati" :key="p.id">
      <li class="bg-white rounded-lg shadow-sm p-3">
        <div class="flex items-start justify-between gap-2">
          <span class="font-medium" x-text="p.nome"></span>
          <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded shrink-0"
                :style="`background:${p.colore_hex}20; color:${p.colore_hex}`" x-text="p.categoria_nome"></span>
        </div>
        <p class="text-sm text-gray-600 mt-1" x-text="p.indirizzo + ', ' + p.comune_nome"></p>
        <p class="text-sm text-gray-600" x-text="p.telefono || ''"></p>
        <a :href="linkIndicazioni(p)" target="_blank" rel="noopener" class="inline-block mt-2 text-sm text-blue-800 underline">Indicazioni</a>
      </li>
    </template>
  </ul>

  <table class="hidden sm:table w-full text-sm bg-white rounded-lg shadow-sm overflow-hidden">
    <caption class="sr-only">Elenco dei servizi comunali, filtrabile per comune, categoria e testo libero</caption>
    <thead class="bg-gray-100 text-left">
      <tr>
        <th scope="col" class="px-3 py-2">Nome</th>
        <th scope="col" class="px-3 py-2">Categoria</th>
        <th scope="col" class="px-3 py-2">Comune</th>
        <th scope="col" class="px-3 py-2">Indirizzo</th>
        <th scope="col" class="px-3 py-2">Telefono</th>
        <th scope="col" class="px-3 py-2"><span class="sr-only">Azioni</span></th>
      </tr>
    </thead>
    <tbody class="divide-y">
      <template x-for="p in puntiFiltrati" :key="p.id">
        <tr>
          <th scope="row" class="px-3 py-2 font-medium text-left" x-text="p.nome"></th>
          <td class="px-3 py-2">
            <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded"
                  :style="`background:${p.colore_hex}20; color:${p.colore_hex}`" x-text="p.categoria_nome"></span>
          </td>
          <td class="px-3 py-2" x-text="p.comune_nome"></td>
          <td class="px-3 py-2" x-text="p.indirizzo"></td>
          <td class="px-3 py-2" x-text="p.telefono || '—'"></td>
          <td class="px-3 py-2">
            <a :href="linkIndicazioni(p)" target="_blank" rel="noopener" class="text-blue-800 underline">Indicazioni</a>
          </td>
        </tr>
      </template>
    </tbody>
  </table>
</main>

<script src="/assets/js/app.js"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js" defer></script>
</body>
</html>
