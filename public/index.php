<?php
require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/lib/helpers.php';
require_once __DIR__ . '/../app/lib/avvisi.php';
require_once __DIR__ . '/../app/lib/mappa.php';

$avvisi = avvisiAttivi();
$configMappa = configMappa() + ['confiniUrl' => asset('/assets/geo/confini.geojson')];
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Punti in comune — Mappa dei servizi comunali</title>
<meta name="description" content="Mappa dei servizi dei comuni di Farra di Soligo, Pieve di Soligo, Refrontolo e Sernaglia della Battaglia: uffici, sportelli, scuole, biblioteche e servizi sociali e sanitari.">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
<?= stiliComuni() ?>
</head>
<body class="h-screen h-dvh flex flex-col bg-white text-gray-900" x-data="mappaServizi()">

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

    <a href="/informazioni.php" class="hidden md:flex shrink-0 items-center gap-1.5 text-sm font-semibold text-primary hover:underline px-2">
      <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
      Come funziona
    </a>

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
    <div x-ref="mappa" id="mappa" class="absolute inset-0 z-0" role="application" aria-label="Mappa dei servizi comunali"></div>

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
                <span class="min-w-0 flex-1">
                  <span class="block text-sm font-semibold text-navy" x-text="p.nome"></span>
                  <span class="inline-block mt-1 text-xs font-semibold px-2 py-0.5 rounded-full" :style="`background:${p.colore_hex};color:${coloreTesto(p.colore_hex)}`" x-text="p.categoria_nome"></span>
                  <span class="block mt-1 text-xs text-gray-600" x-text="p.comune_nome"></span>
                </span>
                <template x-if="p.foto?.length">
                  <img :src="p.foto[0].media" alt="" loading="lazy" class="shrink-0 w-16 h-16 rounded-md object-cover bg-gray-100">
                </template>
              </button>
            </li>
          </template>
        </ul>
      </div>
    </template>
    <template x-if="selezionato">
      <div class="p-5 pt-0 sm:pt-5">
        <template x-if="fotoCorrenti.length">
          <div class="-mx-5 sm:-mt-5 mb-4">
            <button type="button" @click="apriGalleria(0)" class="relative block w-full h-44 sm:h-52 bg-gray-100 overflow-hidden group"
                    :aria-label="'Apri le foto di ' + selezionato.nome + ' (' + fotoCorrenti.length + ')'">
              <img :src="fotoCorrenti[0].media" :alt="fotoCorrenti[0].alt || ''" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105 motion-reduce:transition-none">
              <span class="absolute bottom-2 left-2 flex items-center gap-1.5 bg-black/65 text-white text-xs font-semibold rounded-full px-2.5 py-1">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1Z"/><circle cx="12" cy="13.5" r="3.5"/></svg>
                <span x-text="fotoCorrenti.length === 1 ? '1 foto' : fotoCorrenti.length + ' foto'"></span>
              </span>
            </button>
            <template x-if="fotoCorrenti.length > 1">
              <ul class="flex gap-2 overflow-x-auto px-5 pt-2 pb-1" aria-label="Altre foto">
                <template x-for="(f, i) in fotoCorrenti.slice(1)" :key="f.media">
                  <li class="shrink-0">
                    <button type="button" @click="apriGalleria(i + 1)" class="block w-20 h-16 rounded-md overflow-hidden bg-gray-100 hover:opacity-90" :aria-label="'Apri la foto ' + (i + 2)">
                      <img :src="f.media" :alt="f.alt || ''" loading="lazy" class="w-full h-full object-cover">
                    </button>
                  </li>
                </template>
              </ul>
            </template>
          </div>
        </template>
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

        <template x-if="selezionato?.orario_testo">
          <div class="mt-4">
            <h3 class="text-xs font-semibold uppercase text-gray-500">Orari di apertura</h3>
            <p class="mt-1 text-sm whitespace-pre-line" x-text="selezionato?.orario_testo"></p>
          </div>
        </template>
        <template x-if="selezionato && !selezionato.orario_testo && selezionato.orari?.length">
          <div class="mt-4">
            <h3 class="text-xs font-semibold uppercase text-gray-500">Orari di apertura</h3>
            <dl class="mt-1 text-sm">
              <template x-for="o in (selezionato?.orari ?? [])" :key="o.giorno">
                <div class="flex justify-between py-0.5">
                  <dt x-text="nomiGiorni[o.giorno]"></dt>
                  <dd :class="o.chiuso ? 'text-gray-400 italic' : ''" x-text="o.chiuso ? 'Chiuso' : (o.apertura || '').slice(0, 5) + ' - ' + (o.chiusura || '').slice(0, 5)"></dd>
                </div>
              </template>
            </dl>
          </div>
        </template>

        <p class="mt-4 text-sm whitespace-pre-line" x-text="selezionato.descrizione"></p>

        <div class="mt-4 text-sm space-y-2">
          <div x-show="selezionato?.telefono" class="flex items-start gap-2.5">
            <span class="shrink-0 mt-0.5 text-primary"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25c1.12.37 2.33.57 3.6.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1L6.6 10.8Z"/></svg></span>
            <p class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
              <span class="sr-only">Telefono:</span>
              <template x-for="(pezzo, i) in pezziContatto(selezionato?.telefono)" :key="i">
                <span>
                  <a x-show="pezzo.tipo === 'tel'" :href="pezzo.href" :aria-label="'Chiama il numero ' + pezzo.testo" class="font-semibold text-primary underline underline-offset-2 hover:text-primary-dark whitespace-nowrap" x-text="pezzo.testo"></a>
                  <a x-show="pezzo.tipo === 'email'" :href="pezzo.href" class="text-primary underline underline-offset-2 hover:text-primary-dark break-all" x-text="pezzo.testo"></a>
                  <span x-show="pezzo.tipo === 'testo'" class="text-gray-700" x-text="pezzo.testo"></span>
                </span>
              </template>
            </p>
          </div>
          <div x-show="selezionato?.email" class="flex items-start gap-2.5">
            <span class="shrink-0 mt-0.5 text-primary"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></span>
            <p><span class="sr-only">Email:</span> <a class="text-primary underline underline-offset-2 hover:text-primary-dark break-all" :href="'mailto:' + selezionato?.email" x-text="selezionato?.email"></a></p>
          </div>
        </div>

        <div class="mt-5 space-y-2 pb-4">
          <a :href="linkIndicazioni(selezionato)" target="_blank" rel="noopener"
             class="block text-center bg-primary text-white hover:bg-primary-dark rounded-md py-2 text-sm font-medium">Indicazioni stradali<span class="sr-only"> (si apre in una nuova scheda)</span></a>
          <template x-for="pezzo in pezziContatto(selezionato?.telefono).filter((p) => p.tipo === 'tel').slice(0, 1)" :key="pezzo.href">
            <a :href="pezzo.href" class="flex items-center justify-center gap-2 border border-primary text-primary hover:bg-blue-50 rounded-md py-2 text-sm font-medium">
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25c1.12.37 2.33.57 3.6.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1L6.6 10.8Z"/></svg>
              <span x-text="'Chiama ' + pezzo.testo"></span>
            </a>
          </template>
          <a x-show="linkSicuro(selezionato?.sito_web)" :href="linkSicuro(selezionato?.sito_web)" target="_blank" rel="noopener"
             class="block text-center border rounded-md py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Sito del servizio<span class="sr-only"> (si apre in una nuova scheda)</span></a>
        </div>
      </div>
    </template>
  </aside>
</main>

<footer class="border-t bg-white text-xs text-gray-600">
  <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-1.5 sm:px-4">
    <span class="hidden lg:inline">Punti in comune · Farra di Soligo, Pieve di Soligo, Refrontolo, Sernaglia della Battaglia</span>
    <nav aria-label="Link utili" class="flex flex-wrap gap-x-4 gap-y-1">
      <a href="/informazioni.php" class="underline hover:text-primary font-semibold">Il servizio</a>
      <a href="/lista.php" class="underline hover:text-primary">Elenco dei servizi</a>
      <a href="/privacy.php" class="underline hover:text-primary">Privacy policy</a>
      <a href="/cookie.php" class="underline hover:text-primary">Cookie policy</a>
      <a href="/accessibilita.php" class="underline hover:text-primary">Accessibilità</a>
    </nav>
  </div>
</footer>

<!-- Galleria foto a schermo intero -->
<div x-show="galleria.aperta" x-cloak x-transition.opacity
     class="fixed inset-0 z-[1300] bg-black/90 flex flex-col" role="dialog" aria-modal="true" :aria-label="'Foto di ' + (selezionato?.nome || '')"
     @keydown.escape.window="galleria.aperta && chiudiGalleria()"
     @keydown.arrow-right.window="galleria.aperta && cambiaFoto(1)"
     @keydown.arrow-left.window="galleria.aperta && cambiaFoto(-1)"
     @keydown.tab="tieniFocusInGalleria($event)"
     @touchstart.passive="inizioTocco = $event.changedTouches[0].clientX"
     @touchend="fineTocco($event)">
  <div class="flex items-center justify-between gap-4 text-white px-4 py-3">
    <p class="text-sm min-w-0 truncate" aria-live="polite">
      <span class="font-semibold" x-text="(galleria.indice + 1) + ' / ' + fotoCorrenti.length"></span>
      <span class="ml-2 text-white/80" x-text="selezionato?.nome"></span>
    </p>
    <button type="button" x-ref="chiudiGalleria" @click="chiudiGalleria()" aria-label="Chiudi le foto" class="shrink-0 w-10 h-10 flex items-center justify-center rounded-full hover:bg-white/15">
      <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>
    </button>
  </div>
  <div class="relative flex-1 min-h-0 flex items-center justify-center px-2 sm:px-16 pb-2">
    <img :src="fotoAttuale?.grande" :alt="fotoAttuale?.alt || ''" class="max-w-full max-h-full object-contain select-none">
    <template x-if="fotoCorrenti.length > 1">
      <div>
        <button type="button" @click="cambiaFoto(-1)" aria-label="Foto precedente" class="absolute left-2 sm:left-4 top-1/2 -translate-y-1/2 w-11 h-11 flex items-center justify-center rounded-full bg-white/15 hover:bg-white/30 text-white">
          <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m15 6-6 6 6 6"/></svg>
        </button>
        <button type="button" @click="cambiaFoto(1)" aria-label="Foto successiva" class="absolute right-2 sm:right-4 top-1/2 -translate-y-1/2 w-11 h-11 flex items-center justify-center rounded-full bg-white/15 hover:bg-white/30 text-white">
          <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m9 6 6 6-6 6"/></svg>
        </button>
      </div>
    </template>
  </div>
  <p x-show="fotoAttuale?.alt" x-text="fotoAttuale?.alt" class="text-center text-white/90 text-sm px-4 pb-5"></p>
</div>

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

        <fieldset>
          <legend class="text-xs font-semibold uppercase tracking-wide text-gray-600 mb-3">Opzioni della mappa</legend>
          <div class="space-y-3">
            <label x-show="semplificazioneDisponibile" class="flex items-start gap-3 text-sm cursor-pointer">
              <input type="checkbox" :checked="mappaSemplificata" @change="impostaMappaSemplificata($event.target.checked)" class="mt-0.5 w-4 h-4 rounded border-gray-400 text-primary">
              <span>Mappa semplificata
                <span class="block text-xs text-gray-600">Nasconde negozi, locali e altri punti di interesse dello sfondo: restano solo i servizi comunali.</span>
              </span>
            </label>
            <label x-show="confiniDisponibili" class="flex items-center gap-3 text-sm cursor-pointer">
              <input type="checkbox" :checked="mostraConfini" @change="toggleConfini($event.target.checked)" class="w-4 h-4 rounded border-gray-400 text-primary">
              Mostra confini comunali
            </label>
          </div>
        </fieldset>
      </div>

      <div class="px-5 py-4 border-t">
        <button type="button" @click="azzeraFiltri()" class="w-full border border-gray-400 rounded py-2 text-sm font-medium hover:bg-gray-100">Azzera filtri</button>
      </div>
    </aside>
  </div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script>window.CONFIG_MAPPA = <?= json_encode($configMappa, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="<?= h(asset('/assets/js/app.js')) ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js" defer></script>
</body>
</html>
