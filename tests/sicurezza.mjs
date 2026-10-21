// Test di sicurezza "dall'esterno": accessi senza login, CSRF, input malformati, file sensibili,
// XSS e link pericolosi, permessi tra comuni, blocco dei tentativi di accesso.
// Uso: BASE_URL=... CHROME_PATH=... ADMIN_EMAIL=... ADMIN_PASSWORD=... \
//      COMUNE_EMAIL=... COMUNE_PASSWORD=... node tests/sicurezza.mjs
// ADMIN_* (superadmin) e COMUNE_* (un admin_comune) sono facoltativi: senza, si eseguono solo i test anonimi.
// I dati di prova creati (un punto, un avviso) vengono eliminati alla fine.
// Il test del blocco tentativi blocca per 15 minuti l'account COMUNE_*: usarlo solo con un account di prova.
import puppeteer from 'puppeteer-core';

const BASE = (process.env.BASE_URL || 'http://127.0.0.1:8090').replace(/\/$/, '');
const CHROME = process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

let falliti = 0;
function verifica(condizione, descrizione, dettaglio = '') {
    console.log(`${condizione ? 'OK  ' : 'KO  '} ${descrizione}${!condizione && dettaglio ? ' — ' + dettaglio : ''}`);
    if (!condizione) falliti++;
}

// Client HTTP minimale con cookie di sessione e senza seguire i redirect.
function client() {
    const cookie = new Map();
    return {
        async richiesta(percorso, opzioni = {}) {
            const r = await fetch(BASE + percorso, {
                redirect: 'manual',
                ...opzioni,
                headers: { ...(opzioni.headers || {}), cookie: [...cookie].map(([k, v]) => `${k}=${v}`).join('; ') },
            });
            for (const c of r.headers.getSetCookie()) {
                const [coppia] = c.split(';');
                const [k, v] = coppia.split('=');
                cookie.set(k.trim(), v);
            }
            r.testo = await r.text();
            return r;
        },
        async token(percorso) {
            const r = await this.richiesta(percorso);
            return (r.testo.match(/name="csrf_token" value="([^"]+)"/) || [])[1];
        },
        async post(percorso, campi, { conToken = true, da = percorso } = {}) {
            const corpo = new URLSearchParams(campi);
            if (conToken) corpo.set('csrf_token', await this.token(da));
            return this.richiesta(percorso, { method: 'POST', body: corpo });
        },
        async login(email, password) {
            const r = await this.post('/admin/login.php', { email, password });
            return r.status === 302 && (r.headers.get('location') || '').includes('dashboard');
        },
    };
}

const PAGINE_ADMIN = ['dashboard', 'punti', 'avvisi', 'categorie', 'import', 'utenti', 'profilo', 'impostazioni_mappa', 'impostazioni_smtp'];

// ——— Senza login ———
const anonimo = client();
for (const pagina of PAGINE_ADMIN) {
    const r = await anonimo.richiesta(`/admin/${pagina}.php`);
    verifica(r.status === 302 && r.headers.get('location').includes('login'), `Senza login: /admin/${pagina}.php rimanda al login`, `${r.status}`);
}
{
    const r = await anonimo.post('/admin/login.php', { email: 'x@y.it', password: 'x' }, { conToken: false });
    verifica(r.status === 403, 'Login senza token CSRF rifiutato (403)', `${r.status}`);
    const r2 = await anonimo.richiesta('/admin/login.php', { method: 'POST', body: new URLSearchParams({ 'csrf_token[]': 'x', email: 'a', password: 'b' }) });
    verifica(r2.status === 403, 'Token CSRF malformato (array) rifiutato senza errore 500', `${r2.status}`);
    const r3 = await anonimo.richiesta('/admin/reimposta_password.php?token[]=x');
    verifica(r3.status < 500, 'Link di reset malformato: nessun errore 500', `${r3.status}`);
    const r4 = await anonimo.post('/admin/login.php', { email: "' OR '1'='1' -- ", password: "' OR '1'='1" });
    verifica(r4.status === 200 && r4.testo.includes('non corrette'), 'Login con SQL injection non accede');
}
for (const percorso of ['/app/config/config.php', '/../app/config/config.php', '/sql/schema.sql', '/.git/HEAD', '/package.json', '/tests/sicurezza.mjs', '/.env', '/app/lib/auth.php', '/assets/']) {
    const r = await anonimo.richiesta(percorso);
    verifica(r.status === 403 || r.status === 404, `File o cartella non raggiungibile: ${percorso}`, `${r.status}`);
}
{
    const r = await anonimo.richiesta('/api/punti.php');
    const dati = JSON.parse(r.testo);
    const campi = Object.keys(dati.punti[0] || {});
    const vietati = campi.filter((c) => /password|creato_da|token|utente/i.test(c));
    verifica(vietati.length === 0, 'API pubblica: nessun dato interno esposto', vietati.join(', '));
    const login = await client().richiesta('/admin/login.php');
    const cookieSessione = login.headers.getSetCookie().find((c) => c.startsWith('PHPSESSID')) || '';
    verifica(/httponly/i.test(cookieSessione) && /samesite=lax/i.test(cookieSessione), 'Cookie di sessione HttpOnly e SameSite', cookieSessione);
    if (BASE.startsWith('https')) verifica(/secure/i.test(cookieSessione), 'Cookie di sessione Secure su HTTPS');
    const server = r.headers.get('server') || login.headers.get('x-powered-by') || '';
    if (login.headers.get('x-content-type-options')) {
        for (const h of ['x-content-type-options', 'x-frame-options', 'referrer-policy', 'permissions-policy']) {
            verifica(!!login.headers.get(h), `Intestazione di sicurezza presente: ${h}`);
        }
    } else {
        console.log(`—   Intestazioni di sicurezza: non verificabili qui (le imposta .htaccess, ignorato dal server di sviluppo ${server})`);
    }
}

// ——— Superadmin: XSS e link pericolosi ———
let idPuntoProva = null;
let fotoProva = null; // { puntoId, fotoId, comuneId }: resta fino ai test dell'admin di comune, poi si elimina
let adminClient = null;
const PNG_1PX = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

async function caricaFoto(c, puntoId, nomeFile, contenuto, tipo) {
    const form = new FormData();
    form.set('csrf_token', await c.token(`/admin/punti.php?azione=modifica&id=${puntoId}`));
    form.set('punto_id', String(puntoId));
    form.set('azione', 'carica');
    form.set('foto[]', new Blob([contenuto], { type: tipo }), nomeFile);
    return c.richiesta(`/admin/foto_punto.php?punto_id=${puntoId}`, { method: 'POST', body: form });
}
if (process.env.ADMIN_EMAIL) {
    const admin = client();
    verifica(await admin.login(process.env.ADMIN_EMAIL, process.env.ADMIN_PASSWORD), 'Superadmin: accesso riuscito');

    const r = await admin.richiesta('/admin/logout.php');
    const ancoraDentro = (await admin.richiesta('/admin/dashboard.php')).status === 200;
    verifica(r.status === 302 && ancoraDentro, 'Logout via link (GET) ignorato: serve il pulsante con token CSRF');

    const punto = { id: '0', comune_id: '1', categoria_id: '1', nome: 'ZZ Test <img src=x onerror="window.__xss=1">', indirizzo: 'Via Prova 1', lat: '45.89', lng: '12.17', descrizione: '<script>window.__xss=1</script>', telefono: '0438 000000', email: '', orario_testo: '' };
    let rp = await admin.post('/admin/punti.php', { ...punto, sito_web: 'javascript:window.__xss=1' }, { da: '/admin/punti.php?azione=nuovo' });
    verifica(rp.testo.includes('Sito web non valido'), 'Punto: sito web "javascript:" rifiutato');
    rp = await admin.post('/admin/punti.php', { ...punto, sito_web: 'https://example.org' }, { da: '/admin/punti.php?azione=nuovo' });
    const api = JSON.parse((await admin.richiesta('/api/punti.php')).testo);
    idPuntoProva = api.punti.find((p) => p.nome.startsWith('ZZ Test'))?.id;
    verifica(!!idPuntoProva, 'Punto di prova con codice HTML nel nome creato');

    const ra = await admin.post('/admin/avvisi.php', { id: '0', comune_id: '', tipo: 'info', testo: 'ZZ <img src=x onerror="window.__xss=1">', link_url: 'javascript:window.__xss=1', data_inizio: '', data_fine: '', ordine: '0', attivo: '1' }, { da: '/admin/avvisi.php?azione=nuovo' });
    verifica(ra.testo.includes('Link non valido'), 'Avviso: link "javascript:" rifiutato');
    await admin.post('/admin/avvisi.php', { id: '0', comune_id: '', tipo: 'info', testo: 'ZZ <img src=x onerror="window.__xss=1">', link_url: '', data_inizio: '', data_fine: '', ordine: '0', attivo: '1' }, { da: '/admin/avvisi.php?azione=nuovo' });

    // Import CSV con sito "javascript:": l'anteprima deve segnalarlo.
    const csv = 'comune;categoria;nome;indirizzo;sito_web;lat;lng\nFarra di Soligo;Anagrafe;ZZ Import;Via Prova 2;javascript:alert(1);45.89;12.17\n';
    const form = new FormData();
    form.set('csrf_token', await admin.token('/admin/import.php'));
    form.set('azione', 'carica');
    form.set('csv', new Blob([csv], { type: 'text/csv' }), 'prova.csv');
    await admin.richiesta('/admin/import.php', { method: 'POST', body: form });
    const anteprima = await admin.richiesta('/admin/import.php');
    verifica(anteprima.testo.includes('Sito web non valido'), 'Import CSV: sito web "javascript:" segnalato come errore');

    // Foto: solo immagini vere, ricodificate (un file PHP travestito o nascosto in un'immagine non passa).
    adminClient = admin;
    // Punto del comune con id più basso: se COMUNE_* è di un altro comune, si verificano anche i permessi sulle foto altrui.
    const puntoBersaglio = JSON.parse((await admin.richiesta('/api/punti.php')).testo).punti
        .filter((p) => !p.foto.length).sort((a, b) => a.comune_id - b.comune_id)[0];
    if (puntoBersaglio) {
        await caricaFoto(admin, puntoBersaglio.id, 'shell.jpg', '<?php echo "pwned"; ?>', 'image/jpeg');
        let scheda = await admin.richiesta(`/admin/punti.php?azione=modifica&id=${puntoBersaglio.id}`);
        verifica(scheda.testo.includes('formato non supportato'), 'Foto: file PHP travestito da .jpg rifiutato');

        const poliglotta = Buffer.concat([Buffer.from(PNG_1PX, 'base64'), Buffer.from('<?php echo "pwned"; ?>')]);
        await caricaFoto(admin, puntoBersaglio.id, 'poliglotta.php', poliglotta, 'image/png');
        const foto = JSON.parse((await admin.richiesta('/api/punti.php?x=' + Date.now())).testo).punti.find((p) => p.id === puntoBersaglio.id).foto;
        const salvata = foto[0];
        verifica(salvata && /\.(webp|jpg)$/.test(salvata.grande), 'Foto: immagine con codice PHP nascosto salvata solo come immagine ricodificata', salvata?.grande);
        if (salvata) {
            const file = await admin.richiesta(salvata.grande);
            verifica(file.status === 200 && !file.testo.includes('<?php'), 'Foto: il codice nascosto non sopravvive alla ricodifica');
            scheda = await admin.richiesta(`/admin/punti.php?azione=modifica&id=${puntoBersaglio.id}`);
            fotoProva = { puntoId: puntoBersaglio.id, comuneId: puntoBersaglio.comune_id, fotoId: (scheda.testo.match(/name="foto_id" value="(\d+)"/) || [])[1] };
        }
    }

    // Nel browser: nessuno script iniettato deve eseguirsi in mappa, dettaglio, banner ed elenco.
    const browser = await puppeteer.launch({ executablePath: CHROME, headless: 'new' });
    const page = await browser.newPage();
    for (const percorso of ['/index.php', '/lista.php']) {
        await page.goto(BASE + percorso, { waitUntil: 'networkidle2' });
        if (percorso === '/index.php') {
            await page.type('#ricerca', 'ZZ Test');
            await page.keyboard.press('ArrowDown');
            await page.keyboard.press('Enter');
            await new Promise((r) => setTimeout(r, 800));
        }
        const xss = await page.evaluate(() => window.__xss === 1 || !!document.querySelector('img[src="x"]'));
        verifica(!xss, `${percorso}: codice HTML/JS inserito dall'admin mostrato come testo, non eseguito`);
    }
    await browser.close();

    // Pulizia dei dati di prova.
    const avvisi = await admin.richiesta('/admin/avvisi.php');
    for (const [, id] of avvisi.testo.matchAll(/ZZ &lt;img[\s\S]*?name="id" value="(\d+)"/g)) {
        await admin.post('/admin/avvisi.php', { azione: 'elimina', id });
    }
    if (idPuntoProva) await admin.post('/admin/punti.php', { azione: 'elimina', id: String(idPuntoProva) });
    await admin.post('/admin/import.php', { azione: 'annulla' });
    const dopo = JSON.parse((await admin.richiesta('/api/punti.php')).testo);
    verifica(!dopo.punti.some((p) => p.nome.startsWith('ZZ')), 'Dati di prova eliminati');
}

// ——— Admin di un comune: niente accesso a dati di altri comuni o pagine riservate ———
if (process.env.COMUNE_EMAIL) {
    const comune = client();
    verifica(await comune.login(process.env.COMUNE_EMAIL, process.env.COMUNE_PASSWORD), 'Admin comune: accesso riuscito');
    for (const pagina of ['utenti', 'categorie', 'impostazioni_mappa', 'impostazioni_smtp']) {
        const r = await comune.richiesta(`/admin/${pagina}.php`);
        verifica(r.status === 403, `Admin comune: /admin/${pagina}.php vietata`, `${r.status}`);
    }
    const mio = (await comune.richiesta('/admin/dashboard.php')).testo;
    const api = JSON.parse((await comune.richiesta('/api/punti.php')).testo);
    const elenco = (await comune.richiesta('/admin/punti.php')).testo;
    const altro = api.punti.find((p) => !elenco.includes(`id=${p.id}"`));
    if (altro) {
        const modifica = await comune.richiesta(`/admin/punti.php?azione=modifica&id=${altro.id}`);
        verifica(!modifica.testo.includes(`value="${altro.id}"`), 'Admin comune: non apre in modifica i punti di altri comuni');
        await comune.post('/admin/punti.php', { azione: 'elimina', id: String(altro.id) });
        const ancora = JSON.parse((await comune.richiesta('/api/punti.php')).testo).punti.some((p) => p.id === altro.id);
        verifica(ancora, 'Admin comune: non elimina i punti di altri comuni');
        const sposta = await comune.post('/admin/punti.php', { id: String(altro.id), comune_id: String(altro.comune_id), categoria_id: String(altro.categoria_id), nome: 'ZZ dirottato', indirizzo: 'x', lat: '45.8', lng: '12.1' }, { da: '/admin/punti.php?azione=nuovo' });
        const rinominato = JSON.parse((await comune.richiesta('/api/punti.php')).testo).punti.some((p) => p.nome === 'ZZ dirottato');
        verifica(!rinominato, 'Admin comune: non modifica i punti di altri comuni inviando un id a mano', sposta.status);
    }
    verifica(mio.length > 0, 'Admin comune: dashboard raggiungibile');

    if (fotoProva) {
        const mioPunto = api.punti.find((p) => elenco.includes(`id=${p.id}"`));
        if (mioPunto && fotoProva.comuneId !== mioPunto.comune_id) {
            const r = await caricaFoto(comune, fotoProva.puntoId, 'x.png', Buffer.from(PNG_1PX, 'base64'), 'image/png');
            verifica(r.status === 403, 'Admin comune: non carica foto sui punti di altri comuni', `${r.status}`);
            // Foto di un altro comune indicata insieme a un proprio punto: non deve essere toccata.
            await comune.post('/admin/foto_punto.php', { punto_id: String(mioPunto.id), foto_id: String(fotoProva.fotoId), azione: 'elimina' }, { da: `/admin/punti.php?azione=modifica&id=${mioPunto.id}` });
            const ancora = JSON.parse((await comune.richiesta('/api/punti.php?x=' + Date.now())).testo).punti.find((p) => p.id === fotoProva.puntoId).foto.length;
            verifica(ancora === 1, 'Admin comune: non elimina foto di altri comuni');
        }
    }

    // Blocco dopo 5 tentativi falliti: anche la password giusta viene rifiutata per 15 minuti.
    const attaccante = client();
    for (let i = 0; i < 5; i++) await attaccante.login(process.env.COMUNE_EMAIL, 'password-sbagliata-' + i);
    verifica(!(await attaccante.login(process.env.COMUNE_EMAIL, process.env.COMUNE_PASSWORD)), 'Dopo 5 tentativi falliti l\'account viene bloccato temporaneamente');
}

if (fotoProva && adminClient) {
    await adminClient.post('/admin/foto_punto.php', { punto_id: String(fotoProva.puntoId), foto_id: String(fotoProva.fotoId), azione: 'elimina' }, { da: `/admin/punti.php?azione=modifica&id=${fotoProva.puntoId}` });
    const resta = JSON.parse((await adminClient.richiesta('/api/punti.php?x=' + Date.now())).testo).punti.find((p) => p.id === fotoProva.puntoId).foto.length;
    verifica(resta === 0, 'Foto di prova eliminate');
}

console.log(falliti ? `\n${falliti} verifiche fallite` : '\nTutte le verifiche superate');
process.exit(falliti ? 1 : 0);
