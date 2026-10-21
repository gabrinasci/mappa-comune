// Componente Alpine condiviso tra la mappa (index.php) e la vista lista (lista.php),
// cosi' i due punti di ingresso applicano sempre la stessa logica di filtro/ricerca.

const ICONE_CATEGORIA = {
    anagrafe: '<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0 2c-4 0-8 2-8 5v1h16v-1c0-3-4-5-8-5Z"/>',
    tributi: '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 15.5v1.5h-2v-1.6c-1.4-.3-2.5-1.1-2.9-2.5l1.8-.7c.3.9 1 1.4 2 1.4.9 0 1.6-.4 1.6-1.1 0-.7-.6-1-1.9-1.3-1.8-.5-3.2-1.1-3.2-3 0-1.4 1.1-2.4 2.6-2.7V5.5h2v1.5c1.2.3 2.1 1 2.5 2.2l-1.8.7c-.3-.7-.9-1.1-1.7-1.1-.8 0-1.4.4-1.4 1 0 .6.6.9 1.9 1.2 1.9.5 3.2 1.2 3.2 3.1 0 1.5-1.1 2.5-2.7 2.9Z"/>',
    sociale: '<path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>',
    istruzione: '<path d="m12 3 10 5-10 5L2 8Zm-7 7.5 7 3.5 7-3.5V17l-7 3.5L5 17Z"/>',
    sanita: '<path d="M10 3h4v7h7v4h-7v7h-4v-7H3v-4h7z"/>',
    biblioteca: '<path d="M4 4h6a2 2 0 0 1 2 2v14a2 2 0 0 0-2-2H4Zm16 0h-6a2 2 0 0 0-2 2v14a2 2 0 0 1 2-2h6Z"/>',
};

const ICONA_DEFAULT = '<path d="M12 2C7 2 3 6 3 11c0 6 9 11 9 11s9-5 9-11c0-5-4-9-9-9Zm0 12a3 3 0 1 1 0-6 3 3 0 0 1 0 6Z"/>';

const NOMI_GIORNI = ['', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato', 'Domenica'];

// Configurazione scritta da index.php (fornitore scelto dal superadmin in Impostazioni mappa).
const CONFIG_MAPPA = Object.assign({ fornitore: 'mapbox', mapboxToken: null, confiniUrl: '/assets/geo/confini.geojson' }, window.CONFIG_MAPPA || {});

const ATTRIBUZIONE_MAPBOX = '<a href="https://www.mapbox.com/about/maps/">&copy; Mapbox</a> <a href="https://www.openstreetmap.org/copyright">&copy; OpenStreetMap</a> <a href="https://www.mapbox.com/map-feedback/">Migliora la mappa</a>';
const ATTRIBUZIONE_OSM = '&copy; <a href="https://www.openstreetmap.org/copyright">contributori OpenStreetMap</a>';

// OpenFreeMap: tasselli vettoriali gratuiti e senza chiave, disegnati con MapLibre (caricato solo se serve).
const STILE_OPENFREEMAP = 'https://tiles.openfreemap.org/styles/liberty';
const SCRIPT_MAPLIBRE = [
    'https://cdn.jsdelivr.net/npm/maplibre-gl@5.24.0/dist/maplibre-gl.js',
    'https://cdn.jsdelivr.net/npm/@maplibre/maplibre-gl-leaflet@0.1.4/leaflet-maplibre-gl.js',
];
const CSS_MAPLIBRE = 'https://cdn.jsdelivr.net/npm/maplibre-gl@5.24.0/dist/maplibre-gl.css';

const CHIAVE_MAPPA_SEMPLIFICATA = 'puntiInComune.mappaSemplificata';

function normalizzaTesto(testo) {
    return (testo || '')
        .toString()
        .toLowerCase()
        .normalize('NFD')
        .replace(new RegExp('[' + String.fromCharCode(0x0300) + '-' + String.fromCharCode(0x036f) + ']', 'g'), '');
}

function iconaContrasto(hex) {
    const n = parseInt(hex.replace('#', ''), 16);
    const r = (n >> 16) & 255, g = (n >> 8) & 255, b = n & 255;
    const luminanza = (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
    return luminanza > 0.6 ? '#00194B' : '#FFFFFF';
}

function svgMarker(colore, icona) {
    const path = ICONE_CATEGORIA[icona] || ICONA_DEFAULT;
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="26" height="26" fill="${iconaContrasto(colore)}" aria-hidden="true" focusable="false" style="background:${colore};border-radius:50%;padding:5px;box-shadow:0 1px 4px rgba(0,0,0,.4)">${path}</svg>`;
}

/** Solo link http/https; un indirizzo senza protocollo (es. "www.comune.it") viene completato con https://. */
function linkSicuro(url) {
    const valore = (url || '').trim();
    if (/^https?:\/\//i.test(valore)) return valore;
    if (/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}(\/\S*)?$/i.test(valore)) return 'https://' + valore;
    return null;
}

/**
 * Divide il campo telefono in pezzi da mostrare: numeri (link "tel:"), email (link "mailto:") e testo libero.
 * Nei dati reali lo stesso campo contiene spesso più numeri, email o il nome di un referente.
 */
function pezziContatto(testo) {
    const pezzi = [];
    const regex = /(\+?\d[\d\s\/.\-]{4,}\d)|([^\s@,;]+@[^\s@,;]+\.[a-z]{2,})/gi;
    let ultimo = 0;
    let trovato;
    while ((trovato = regex.exec(testo || '')) !== null) {
        if (trovato.index > ultimo) pezzi.push({ tipo: 'testo', testo: testo.slice(ultimo, trovato.index) });
        if (trovato[2]) {
            pezzi.push({ tipo: 'email', testo: trovato[2], href: 'mailto:' + trovato[2] });
        } else {
            pezzi.push(...pezziNumeri(trovato[1]));
        }
        ultimo = regex.lastIndex;
    }
    if (ultimo < (testo || '').length) pezzi.push({ tipo: 'testo', testo: testo.slice(ultimo) });
    return pezzi.filter((p) => p.testo.trim() !== '' || p.tipo !== 'testo');
}

function contaCifre(testo) {
    return testo.replace(/\D/g, '').length;
}

/** Uno o più numeri di telefono scritti di seguito (es. "0438 965320 / 0438 965303") diventano link separati. */
function pezziNumeri(testo) {
    if (contaCifre(testo) <= 13) {
        const cifre = testo.replace(/[^\d+]/g, '');
        if (contaCifre(cifre) < 6) return [{ tipo: 'testo', testo }];
        return [{ tipo: 'tel', testo: testo.trim(), href: 'tel:' + (cifre.startsWith('+') ? cifre : '+39' + cifre) }];
    }
    // Troppe cifre per un solo numero: si divide sui separatori espliciti, oppure dove inizia
    // un nuovo prefisso (0 fisso, 3 cellulare) dopo un numero già completo.
    let parti = testo.split(/(\s+[\/\-]\s+|\s*[;,]\s*)/);
    if (parti.length === 1) {
        parti = [];
        let corrente = '';
        for (const token of testo.split(/(\s+)/)) {
            if (/^\s+$/.test(token)) { corrente += token; continue; }
            if (contaCifre(corrente) >= 8 && /^[03]/.test(token)) { parti.push(corrente.trimEnd(), ' '); corrente = ''; }
            corrente += token;
        }
        parti.push(corrente);
    }
    return parti.flatMap((parte, i) => (i % 2 === 1 ? [{ tipo: 'testo', testo: parte }] : contaCifre(parte) <= 13 ? pezziNumeri(parte) : [{ tipo: 'testo', testo: parte }]));
}

function caricaScript(url) {
    return new Promise((risolvi, rifiuta) => {
        const s = document.createElement('script');
        s.src = url;
        s.onload = risolvi;
        s.onerror = () => rifiuta(new Error('Impossibile caricare ' + url));
        document.head.appendChild(s);
    });
}

function webglDisponibile() {
    try {
        const canvas = document.createElement('canvas');
        return !!(canvas.getContext('webgl2') || canvas.getContext('webgl'));
    } catch (e) {
        return false;
    }
}

function leggiPreferenza(chiave) {
    try { return localStorage.getItem(chiave); } catch (e) { return null; }
}

function salvaPreferenza(chiave, valore) {
    try { localStorage.setItem(chiave, valore); } catch (e) { /* storage non disponibile: la scelta vale solo per questa visita */ }
}

function mappaServizi() {
    // Oggetti Leaflet tenuti fuori dallo stato reattivo di Alpine: i proxy di Alpine
    // interferiscono con i confronti interni di Leaflet e del clustering.
    const lf = { mappa: null, marker: null, confini: null, posizione: null, sfondo: null, maplibre: null };

    return {
        comuni: [],
        categorie: [],
        punti: [],
        filtroComuni: [],
        filtroCategorie: [],
        ricerca: '',
        selezionato: null,
        caricamentoFallito: false,
        confiniDisponibili: false,
        mostraConfini: true,
        mappaSemplificata: leggiPreferenza(CHIAVE_MAPPA_SEMPLIFICATA) === '1',
        semplificazioneDisponibile: true,
        statoGeo: '',
        nomiGiorni: NOMI_GIORNI,
        filtriAperti: false,
        suggerimentiAperti: false,
        indiceEvidenziato: -1,
        galleria: { aperta: false, indice: 0 },
        inizioTocco: null,
        elementoPrimaDellaGalleria: null,

        // Chiamato in automatico da Alpine. Su lista.php non c'è l'elemento della mappa.
        async init() {
            try {
                const risposta = await fetch('/api/punti.php');
                if (!risposta.ok) throw new Error('risposta non ok');
                const dati = await risposta.json();
                this.comuni = dati.comuni;
                this.categorie = dati.categorie;
                this.punti = dati.punti;
            } catch (e) {
                this.caricamentoFallito = true;
                return;
            }

            const elementoMappa = this.$refs.mappa;
            if (elementoMappa) {
                this.iniziaLeaflet(elementoMappa);
                this.aggiornaMarkers();
                this.caricaConfiniSeDisponibili();
            }
        },

        iniziaLeaflet(elemento) {
            // maxZoom sulla mappa: il clustering ne ha bisogno anche mentre lo sfondo OpenFreeMap è ancora in caricamento.
            lf.mappa = L.map(elemento, { scrollWheelZoom: true, maxZoom: 19 });
            lf.mappa.setView([45.89, 12.17], 13);
            this.impostaSfondo();
            lf.marker = L.markerClusterGroup({
                maxClusterRadius: 45,
                showCoverageOnHover: false,
                iconCreateFunction: (cluster) => L.divIcon({
                    html: `<span>${cluster.getChildCount()}</span>`,
                    className: 'cluster-servizi',
                    iconSize: [36, 36],
                }),
            }).addTo(lf.mappa);
        },

        // Mappa di sfondo secondo il fornitore scelto e l'opzione "mappa semplificata" (senza negozi e punti di interesse).
        async impostaSfondo() {
            if (CONFIG_MAPPA.fornitore === 'openfreemap') {
                if (await this.impostaSfondoOpenFreeMap()) return;
                // Senza WebGL o se MapLibre non si carica: mappa OpenStreetMap standard, che non si può semplificare.
                this.semplificazioneDisponibile = false;
                this.sostituisciSfondo(L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: ATTRIBUZIONE_OSM,
                    maxZoom: 19,
                }));
                return;
            }
            const stile = this.mappaSemplificata ? 'light-v11' : 'streets-v12';
            this.sostituisciSfondo(L.tileLayer(`https://api.mapbox.com/styles/v1/mapbox/${stile}/tiles/512/{z}/{x}/{y}@2x?access_token=${CONFIG_MAPPA.mapboxToken}`, {
                tileSize: 512,
                zoomOffset: -1,
                attribution: ATTRIBUZIONE_MAPBOX,
                maxZoom: 20,
            }));
        },

        async impostaSfondoOpenFreeMap() {
            if (lf.maplibre) {
                this.applicaSemplificazioneMapLibre();
                return true;
            }
            if (!webglDisponibile()) return false;
            try {
                if (!window.maplibregl) {
                    const css = document.createElement('link');
                    css.rel = 'stylesheet';
                    css.href = CSS_MAPLIBRE;
                    document.head.appendChild(css);
                    for (const url of SCRIPT_MAPLIBRE) await caricaScript(url);
                }
                const livello = L.maplibreGL({ style: STILE_OPENFREEMAP });
                this.sostituisciSfondo(livello);
                lf.maplibre = livello.getMaplibreMap();
                lf.maplibre.on('styledata', () => this.applicaSemplificazioneMapLibre());
                return true;
            } catch (e) {
                return false;
            }
        },

        applicaSemplificazioneMapLibre() {
            const mappa = lf.maplibre;
            if (!mappa || !mappa.getStyle()) return;
            const visibilita = this.mappaSemplificata ? 'none' : 'visible';
            for (const livello of mappa.getStyle().layers) {
                if (livello.id.startsWith('poi') && mappa.getLayoutProperty(livello.id, 'visibility') !== visibilita) {
                    mappa.setLayoutProperty(livello.id, 'visibility', visibilita);
                }
            }
        },

        sostituisciSfondo(livello) {
            if (lf.sfondo) lf.mappa.removeLayer(lf.sfondo);
            lf.sfondo = livello.addTo(lf.mappa);
            if (livello.bringToBack) livello.bringToBack();
        },

        impostaMappaSemplificata(attiva) {
            this.mappaSemplificata = attiva;
            salvaPreferenza(CHIAVE_MAPPA_SEMPLIFICATA, attiva ? '1' : '0');
            if (lf.mappa) this.impostaSfondo();
        },

        async caricaConfiniSeDisponibili() {
            try {
                const risposta = await fetch(CONFIG_MAPPA.confiniUrl, { method: 'GET' });
                if (!risposta.ok) return;
                const geojson = await risposta.json();
                lf.confini = L.geoJSON(geojson, {
                    interactive: false,
                    style: { color: '#0066CC', weight: 2, fillColor: '#0066CC', fillOpacity: 0.12 },
                    onEachFeature: (feature, layer) => {
                        layer.bindTooltip(feature.properties.name, { sticky: true });
                    },
                });
                this.confiniDisponibili = true;
                if (lf.mappa) this.toggleConfini(this.mostraConfini);
            } catch (e) {
                // File non ancora presente: nessun problema, il layer si aggiunge quando disponibile.
            }
        },

        geolocalizza() {
            if (!navigator.geolocation) {
                this.statoGeo = 'Geolocalizzazione non disponibile su questo dispositivo.';
                return;
            }
            this.statoGeo = 'Ricerca della posizione in corso...';
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    const latlng = [pos.coords.latitude, pos.coords.longitude];
                    this.statoGeo = '';
                    if (!lf.mappa) return;
                    lf.mappa.setView(latlng, 16);
                    if (lf.posizione) {
                        lf.posizione.setLatLng(latlng);
                    } else {
                        lf.posizione = L.circleMarker(latlng, {
                            radius: 8, color: '#ffffff', weight: 3, fillColor: '#0066CC', fillOpacity: 1,
                        }).addTo(lf.mappa);
                    }
                },
                () => {
                    this.statoGeo = 'Posizione non disponibile. Verifica di aver concesso l\'accesso alla posizione nel browser.';
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
            );
        },

        toggleConfini(mostra) {
            this.mostraConfini = mostra;
            if (!lf.confini || !lf.mappa) return;
            if (mostra) lf.confini.addTo(lf.mappa);
            else lf.mappa.removeLayer(lf.confini);
        },

        get puntiFiltrati() {
            const ricerca = normalizzaTesto(this.ricerca).split(/\s+/).filter(Boolean);
            return this.punti.filter((p) => {
                // I servizi sovracomunali restano visibili qualunque comune sia filtrato:
                // servono i residenti di tutti i comuni, non solo quello della loro sede.
                if (this.filtroComuni.length && !p.sovracomunale && !this.filtroComuni.includes(p.comune_id)) return false;
                if (this.filtroCategorie.length && !this.filtroCategorie.includes(p.categoria_id)) return false;
                if (!ricerca.length) return true;
                const testo = normalizzaTesto([p.nome, p.indirizzo, p.categoria_nome, p.comune_nome, p.descrizione].join(' '));
                return ricerca.every((termine) => testo.includes(termine));
            });
        },

        toggleFiltro(lista, id) {
            const indice = this[lista].indexOf(id);
            if (indice === -1) this[lista].push(id);
            else this[lista].splice(indice, 1);
            this.aggiornaMarkers();
        },

        get numeroFiltriAttivi() {
            return this.filtroComuni.length + this.filtroCategorie.length;
        },

        azzeraFiltri() {
            this.filtroComuni = [];
            this.filtroCategorie = [];
            this.aggiornaMarkers();
        },

        get suggerimenti() {
            return this.ricerca.trim() ? this.puntiFiltrati.slice(0, 8) : [];
        },

        onRicercaInput() {
            this.suggerimentiAperti = this.ricerca.trim().length > 0;
            this.indiceEvidenziato = -1;
            this.aggiornaMarkers();
        },

        chiudiSuggerimenti() {
            this.suggerimentiAperti = false;
            this.indiceEvidenziato = -1;
        },

        spostaEvidenziazione(delta) {
            if (!this.suggerimenti.length) return;
            this.suggerimentiAperti = true;
            const n = this.suggerimenti.length;
            this.indiceEvidenziato = (this.indiceEvidenziato + delta + n) % n;
        },

        confermaEvidenziato() {
            if (this.indiceEvidenziato >= 0 && this.suggerimenti[this.indiceEvidenziato]) {
                this.selezionaSuggerimento(this.suggerimenti[this.indiceEvidenziato]);
            }
        },

        selezionaSuggerimento(punto) {
            this.ricerca = punto.nome;
            this.chiudiSuggerimenti();
            this.aggiornaMarkers();
            this.seleziona(punto);
        },

        aggiornaMarkers() {
            if (!lf.marker) return;
            lf.marker.clearLayers();
            const markers = this.puntiFiltrati.map((p) => {
                const marker = L.marker([p.lat, p.lng], {
                    icon: L.divIcon({
                        html: svgMarker(p.colore_hex, p.categoria_icona),
                        className: 'marker-servizio',
                        iconSize: [26, 26],
                        iconAnchor: [13, 13],
                    }),
                    keyboard: true,
                    title: p.nome,
                    riseOnHover: true,
                });
                // Nome leggibile anche dagli screen reader (l'icona è un div, non un'immagine con alt).
                marker.on('add', () => marker.getElement()?.setAttribute('aria-label', `${p.nome}, ${p.categoria_nome}, ${p.comune_nome}`));
                marker.on('click', () => this.seleziona(p));
                return marker;
            });
            lf.marker.addLayers(markers);
        },

        seleziona(punto) {
            // Su desktop il pannello filtri copre la colonna dei dettagli: va chiuso, altrimenti
            // il dettaglio si apre sotto e sembra che il clic sul punto non sia stato preso.
            this.filtriAperti = false;
            this.galleria.aperta = false;
            this.selezionato = punto;
            if (lf.mappa) lf.mappa.panTo([punto.lat, punto.lng]);
        },

        chiudiDettaglio() {
            this.galleria.aperta = false;
            this.selezionato = null;
        },

        get fotoCorrenti() {
            return this.selezionato?.foto || [];
        },

        get fotoAttuale() {
            return this.fotoCorrenti[this.galleria.indice] || null;
        },

        apriGalleria(indice) {
            this.elementoPrimaDellaGalleria = document.activeElement;
            this.galleria = { aperta: true, indice };
            this.precaricaFoto(indice + 1);
            this.$nextTick(() => this.$refs.chiudiGalleria?.focus());
        },

        chiudiGalleria() {
            this.galleria.aperta = false;
            this.elementoPrimaDellaGalleria?.focus?.();
        },

        cambiaFoto(delta) {
            const n = this.fotoCorrenti.length;
            if (n < 2) return;
            this.galleria.indice = (this.galleria.indice + delta + n) % n;
            this.precaricaFoto(this.galleria.indice + delta);
        },

        precaricaFoto(indice) {
            const n = this.fotoCorrenti.length;
            const foto = n ? this.fotoCorrenti[(indice + n) % n] : null;
            if (foto) new Image().src = foto.grande;
        },

        // Scorrimento col dito sulla galleria (mobile).
        fineTocco(evento) {
            if (this.inizioTocco === null) return;
            const spostamento = evento.changedTouches[0].clientX - this.inizioTocco;
            this.inizioTocco = null;
            if (Math.abs(spostamento) > 50) this.cambiaFoto(spostamento < 0 ? 1 : -1);
        },

        // Il tasto Tab resta dentro la galleria finché è aperta (finestra modale).
        tieniFocusInGalleria(evento) {
            const focusabili = [...evento.currentTarget.querySelectorAll('button')].filter((b) => b.offsetParent !== null);
            if (!focusabili.length) return;
            const primo = focusabili[0];
            const ultimo = focusabili[focusabili.length - 1];
            if (evento.shiftKey && document.activeElement === primo) { evento.preventDefault(); ultimo.focus(); }
            else if (!evento.shiftKey && document.activeElement === ultimo) { evento.preventDefault(); primo.focus(); }
        },

        svgIconaCategoria(punto) {
            const path = ICONE_CATEGORIA[punto.categoria_icona] || ICONA_DEFAULT;
            return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="${iconaContrasto(punto.colore_hex)}" aria-hidden="true" focusable="false">${path}</svg>`;
        },

        coloreTesto(hex) {
            return iconaContrasto(hex);
        },

        pezziContatto(testo) {
            return pezziContatto(testo);
        },

        linkSicuro(url) {
            return linkSicuro(url);
        },

        linkIndicazioni(punto) {
            return `https://www.google.com/maps/dir/?api=1&destination=${punto.lat},${punto.lng}`;
        },
    };
}

// Banner avvisi della home: scorre da solo ogni pochi secondi, si ferma al passaggio del
// mouse o del focus e con il pulsante pausa (WCAG 2.2.2). Con "riduci movimento" parte in pausa.
const INTERVALLO_AVVISI_MS = 6000;

function bannerAvvisi(totale) {
    return {
        totale,
        attivo: 0,
        inPausa: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        sospeso: false,

        init() {
            if (this.totale < 2) return;
            setInterval(() => {
                if (!this.inPausa && !this.sospeso && !document.hidden) this.successivo();
            }, INTERVALLO_AVVISI_MS);
        },

        successivo() {
            this.attivo = (this.attivo + 1) % this.totale;
        },

        precedente() {
            this.attivo = (this.attivo - 1 + this.totale) % this.totale;
        },
    };
}
