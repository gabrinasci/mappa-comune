// Test funzionali della mappa pubblica e delle impostazioni, su desktop e mobile.
// Uso: BASE_URL=http://127.0.0.1:8090 CHROME_PATH="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
//      ADMIN_EMAIL=... ADMIN_PASSWORD=... node tests/e2e.mjs
// ADMIN_* sono facoltativi: senza, si saltano i test delle impostazioni (che comunque ripristinano il valore iniziale).
import puppeteer from 'puppeteer-core';

const BASE = (process.env.BASE_URL || 'http://127.0.0.1:8090').replace(/\/$/, '');
const CHROME = process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const attendi = (ms) => new Promise((r) => setTimeout(r, ms));

let falliti = 0;
function verifica(condizione, descrizione, dettaglio = '') {
    console.log(`${condizione ? 'OK  ' : 'KO  '} ${descrizione}${!condizione && dettaglio ? ' — ' + dettaglio : ''}`);
    if (!condizione) falliti++;
}

const browser = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--use-angle=swiftshader', '--enable-unsafe-swiftshader'] });

async function apri(percorso, { larghezza = 1280, mobile = false } = {}) {
    const page = await browser.newPage();
    await page.setViewport({ width: larghezza, height: 800, isMobile: mobile, hasTouch: mobile });
    page.erroriJs = [];
    page.richieste = [];
    page.on('pageerror', (e) => page.erroriJs.push(e.message));
    page.on('request', (r) => page.richieste.push(r.url()));
    await page.goto(BASE + percorso, { waitUntil: 'networkidle2', timeout: 60000 });
    return page;
}

// Primo marker singolo (non cluster) visibile nella parte di mappa non coperta da pannelli.
async function markerVisibile(page) {
    return page.evaluate(() => {
        const mappa = document.getElementById('mappa').getBoundingClientRect();
        const pannello = document.getElementById('pannello-filtri');
        const limiteDestro = pannello && pannello.offsetParent ? pannello.getBoundingClientRect().left : mappa.right;
        for (const el of document.querySelectorAll('.marker-servizio')) {
            const r = el.getBoundingClientRect();
            if (r.width && r.left > mappa.left + 20 && r.right < Math.min(mappa.right, limiteDestro) - 20 && r.top > mappa.top + 20 && r.bottom < mappa.bottom - 20) {
                return { x: r.left + r.width / 2, y: r.top + r.height / 2, nome: el.getAttribute('title') };
            }
        }
        return null;
    });
}

async function zoomSuPunti(page, zoom = 15) {
    // Ingrandisce con i controlli della mappa finché compaiono marker singoli.
    for (let i = 0; i < zoom - 13; i++) {
        await page.click('.leaflet-control-zoom-in');
        await attendi(450);
        if (await markerVisibile(page)) return;
    }
}

// Il dettaglio è "visibile" se il titolo è davvero in primo piano (non coperto da altri pannelli).
async function dettaglioVisibile(page) {
    return page.evaluate(() => {
        const h = document.querySelector('main aside h2');
        if (!h) return null;
        const r = h.getBoundingClientRect();
        const sopra = document.elementFromPoint(r.left + 5, r.top + r.height / 2);
        return { nome: h.textContent, visibile: !!sopra && h.contains(sopra) };
    });
}

// ——— Home desktop ———
{
    const page = await apri('/index.php');
    await page.waitForSelector('.marker-servizio, .cluster-servizi');
    await attendi(800);
    verifica(page.erroriJs.length === 0, 'Home: nessun errore JavaScript', page.erroriJs.join(' | '));
    const chiamateApi = page.richieste.filter((u) => u.includes('/api/punti.php')).length;
    verifica(chiamateApi === 1, 'Home: dati dei punti scaricati una sola volta', `${chiamateApi} chiamate`);
    verifica(!page.richieste.some((u) => u.includes('cdn.tailwindcss.com')), 'Home: CSS compilato, niente Tailwind da CDN');
    verifica(page.richieste.some((u) => /app\.js\?v=\d+/.test(u)), 'Home: app.js richiamato con versione (niente cache vecchie)');

    const conAria = await page.$$eval('.marker-servizio', (els) => els.length > 0 && els.every((e) => e.getAttribute('aria-label')));
    verifica(conAria, 'Marker: nome leggibile dagli screen reader (aria-label)');

    // Bug segnalato: pannello filtri aperto + clic su un punto → il dettaglio deve vedersi.
    await zoomSuPunti(page);
    await page.click('button[aria-controls="pannello-filtri"]');
    await attendi(500);
    const marker = await markerVisibile(page);
    verifica(!!marker, 'Filtri aperti: c\'è un punto cliccabile accanto al pannello');
    if (marker) {
        await page.mouse.click(marker.x, marker.y);
        await attendi(700);
        const d = await dettaglioVisibile(page);
        verifica(d && d.visibile && d.nome === marker.nome, 'Filtri aperti + clic sul punto: dettaglio visibile e corretto', JSON.stringify(d));
        const filtriChiusi = await page.$eval('#pannello-filtri', (el) => getComputedStyle(el).display === 'none');
        verifica(filtriChiusi, 'Clic sul punto: il pannello filtri si chiude');
    }

    // Contatti: un punto con telefono deve avere link tel: e pulsante "Chiama".
    const conTelefono = await page.evaluate(async () => (await (await fetch('/api/punti.php')).json()).punti.find((p) => /\d{6}/.test((p.telefono || '').replace(/\D/g, '')) && p.sito_web));
    if (conTelefono) {
        await page.type('#ricerca', conTelefono.nome);
        await page.keyboard.press('ArrowDown');
        await page.keyboard.press('Enter');
        await attendi(600);
        const link = await page.$$eval('main aside a[href^="tel:"]', (as) => as.map((a) => a.getAttribute('href')));
        verifica(link.length >= 1 && link.every((h) => /^tel:\+\d{8,15}$/.test(h)), 'Dettaglio: numeri di telefono cliccabili (tel:+39…)', link.join(', '));
        const chiama = await page.$$eval('main aside a[href^="tel:"]', (as) => as.some((a) => a.textContent.includes('Chiama')));
        verifica(chiama, 'Dettaglio: pulsante "Chiama"');
        const sito = await page.$eval('main aside', (a) => [...a.querySelectorAll('a')].find((x) => x.textContent.includes('Sito del servizio'))?.href);
        verifica(/^https?:\/\//.test(sito || '') && !(sito || '').startsWith(BASE), 'Dettaglio: link al sito esterno assoluto', sito);
    }

    // Mappa semplificata (con Mapbox: stile light; con OpenFreeMap: punti di interesse spenti), ricordata al ricaricamento.
    await page.evaluate(() => document.querySelector('button[aria-controls="pannello-filtri"]').click());
    await attendi(500);
    const disponibile = await page.evaluate(() => Alpine.$data(document.body).semplificazioneDisponibile);
    if (disponibile) {
        page.richieste.length = 0;
        await page.evaluate(() => Alpine.$data(document.body).impostaMappaSemplificata(true));
        await attendi(1500);
        const fornitore = await page.evaluate(() => CONFIG_MAPPA.fornitore);
        if (fornitore === 'mapbox') {
            verifica(page.richieste.some((u) => u.includes('/light-v11/')), 'Mappa semplificata (Mapbox): stile senza punti di interesse');
        }
        await page.reload({ waitUntil: 'networkidle2' });
        const ricordata = await page.evaluate(() => Alpine.$data(document.body).mappaSemplificata);
        verifica(ricordata === true, 'Mappa semplificata: scelta ricordata dopo il ricaricamento');
        await page.evaluate(() => Alpine.$data(document.body).impostaMappaSemplificata(false));
    }
    await page.close();
}

// ——— Home mobile ———
{
    const page = await apri('/index.php', { larghezza: 390, mobile: true });
    await page.waitForSelector('.marker-servizio, .cluster-servizi');
    await attendi(800);
    verifica(page.erroriJs.length === 0, 'Mobile: nessun errore JavaScript', page.erroriJs.join(' | '));
    const larghezzaPagina = await page.evaluate(() => document.documentElement.scrollWidth);
    verifica(larghezzaPagina <= 390, 'Mobile: nessuno scorrimento orizzontale', `${larghezzaPagina}px`);
    await zoomSuPunti(page);
    const marker = await markerVisibile(page);
    if (marker) {
        await page.touchscreen.tap(marker.x, marker.y);
        await attendi(800);
        const d = await dettaglioVisibile(page);
        verifica(d && d.visibile, 'Mobile: tocco su un punto apre il foglio dei dettagli in primo piano', JSON.stringify(d));
        // Il foglio non deve finire sotto marker o controlli della mappa.
        const coperto = await page.evaluate(() => {
            const aside = document.querySelector('main aside');
            const r = aside.getBoundingClientRect();
            const punti = [[r.left + 20, r.top + 40], [r.left + r.width / 2, r.top + r.height / 2], [r.right - 20, r.bottom - 20]];
            return punti.some(([x, y]) => { const e = document.elementFromPoint(x, y); return e && !aside.contains(e); });
        });
        verifica(!coperto, 'Mobile: il foglio dei dettagli non è coperto da marker o controlli');
    } else {
        verifica(false, 'Mobile: trovato un punto da toccare');
    }
    await page.close();
}

// ——— Altre pagine pubbliche ———
for (const percorso of ['/lista.php', '/informazioni.php', '/privacy.php', '/cookie.php', '/accessibilita.php']) {
    for (const [larghezza, mobile] of [[1280, false], [390, true]]) {
        const page = await apri(percorso, { larghezza, mobile });
        await attendi(600);
        const scroll = await page.evaluate(() => document.documentElement.scrollWidth);
        verifica(page.erroriJs.length === 0 && scroll <= larghezza, `${percorso} @${larghezza}px: nessun errore e nessuno scorrimento orizzontale`, page.erroriJs.join(' | ') || `${scroll}px`);
        if (percorso === '/lista.php' && larghezza === 1280) {
            const tel = await page.$$eval('a[href^="tel:"]', (as) => as.length);
            verifica(tel > 0, 'Elenco: numeri di telefono cliccabili', `${tel} link`);
        }
        if (percorso === '/informazioni.php' && larghezza === 1280) {
            const siti = await page.$$eval('a[href*="comune."]', (as) => as.map((a) => a.href));
            verifica(siti.length >= 4, 'Il servizio: link ai portali dei comuni', siti.join(', '));
        }
        await page.close();
    }
}

// ——— Impostazioni mappa (superadmin) ———
if (process.env.ADMIN_EMAIL && process.env.ADMIN_PASSWORD) {
    const page = await apri('/admin/login.php');
    await page.type('#email', process.env.ADMIN_EMAIL);
    await page.type('#password', process.env.ADMIN_PASSWORD);
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
    await page.goto(BASE + '/admin/impostazioni_mappa.php', { waitUntil: 'networkidle2' });
    const iniziale = await page.$eval('input[name=mappa_fornitore]:checked', (i) => i.value);
    const tokenIniziale = await page.$eval('#mapbox_token', (i) => i.value);

    // Un token segreto (sk.) non deve poter finire nella pagina pubblica.
    await page.$eval('#mapbox_token', (i) => { i.value = 'sk.segreto123'; });
    await Promise.all([page.waitForNavigation(), page.click('main form button[type=submit]')]);
    verifica((await page.content()).includes('token pubblico'), 'Impostazioni mappa: token segreto (sk.) rifiutato');

    await page.goto(BASE + '/admin/impostazioni_mappa.php', { waitUntil: 'networkidle2' });
    await page.click('input[value=openfreemap]');
    await Promise.all([page.waitForNavigation(), page.click('main form button[type=submit]')]);

    const home = await apri('/index.php');
    await attendi(5000);
    const stato = await home.evaluate(() => ({
        fornitore: CONFIG_MAPPA.fornitore,
        maplibre: !!document.querySelector('.leaflet-gl-layer, .maplibregl-canvas'),
        osmRaster: [...document.querySelectorAll('.leaflet-tile')].some((t) => t.src.includes('tile.openstreetmap.org')),
        mapbox: [...document.querySelectorAll('.leaflet-tile')].some((t) => t.src.includes('mapbox')),
    }));
    verifica(stato.fornitore === 'openfreemap' && (stato.maplibre || stato.osmRaster) && !stato.mapbox, 'OpenFreeMap selezionato: la home non usa più Mapbox', JSON.stringify(stato));
    verifica(home.erroriJs.length === 0, 'OpenFreeMap: nessun errore JavaScript', home.erroriJs.join(' | '));
    if (stato.maplibre) {
        await home.evaluate(() => Alpine.$data(document.body).impostaMappaSemplificata(true));
        await attendi(1500);
        await home.screenshot({ path: 'tests/ultimo-openfreemap-semplificata.png' });
        await home.evaluate(() => Alpine.$data(document.body).impostaMappaSemplificata(false));
    }
    await home.close();

    // Ripristino della configurazione iniziale.
    await page.goto(BASE + '/admin/impostazioni_mappa.php', { waitUntil: 'networkidle2' });
    await page.click(`input[value=${iniziale}]`);
    await page.$eval('#mapbox_token', (i, v) => { i.value = v; }, tokenIniziale);
    await Promise.all([page.waitForNavigation(), page.click('main form button[type=submit]')]);
    const ripristinato = await page.$eval('input[name=mappa_fornitore]:checked', (i) => i.value);
    verifica(ripristinato === iniziale, `Impostazioni mappa ripristinate (${iniziale})`);
    await page.close();
} else {
    console.log('—   Impostazioni mappa: saltate (ADMIN_EMAIL / ADMIN_PASSWORD non impostati)');
}

await browser.close();
console.log(falliti ? `\n${falliti} verifiche fallite` : '\nTutte le verifiche superate');
process.exit(falliti ? 1 : 0);
