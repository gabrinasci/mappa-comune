// Componente Alpine condiviso tra la mappa (index.php) e la vista lista (lista.php),
// cosi' i due punti di ingresso applicano sempre la stessa logica di filtro/ricerca.

const ICONE_CATEGORIA = {
    anagrafe: '<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0 2c-4 0-8 2-8 5v1h16v-1c0-3-4-5-8-5Z"/>',
    tributi: '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 15.5v1.5h-2v-1.6c-1.4-.3-2.5-1.1-2.9-2.5l1.8-.7c.3.9 1 1.4 2 1.4.9 0 1.6-.4 1.6-1.1 0-.7-.6-1-1.9-1.3-1.8-.5-3.2-1.1-3.2-3 0-1.4 1.1-2.4 2.6-2.7V5.5h2v1.5c1.2.3 2.1 1 2.5 2.2l-1.8.7c-.3-.7-.9-1.1-1.7-1.1-.8 0-1.4.4-1.4 1 0 .6.6.9 1.9 1.2 1.9.5 3.2 1.2 3.2 3.1 0 1.5-1.1 2.5-2.7 2.9Z"/>',
    sociale: '<path d="M12 21s-7.5-4.6-10-9.1C.5 8.6 2 5 5.5 5c2 0 3.4 1.1 4.5 2.6C11.1 6.1 12.5 5 14.5 5 18 5 19.5 8.6 22 11.9 19.5 16.4 12 21 12 21Z"/>',
    istruzione: '<path d="m12 3 10 5-10 5L2 8Zm-7 7.5 7 3.5 7-3.5V17l-7 3.5L5 17Z"/>',
    biblioteca: '<path d="M4 4h6a2 2 0 0 1 2 2v14a2 2 0 0 0-2-2H4Zm16 0h-6a2 2 0 0 0-2 2v14a2 2 0 0 1 2-2h6Z"/>',
};

const ICONA_DEFAULT = '<path d="M12 2C7 2 3 6 3 11c0 6 9 11 9 11s9-5 9-11c0-5-4-9-9-9Zm0 12a3 3 0 1 1 0-6 3 3 0 0 1 0 6Z"/>';

const NOMI_GIORNI = ['', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato', 'Domenica'];

function normalizzaTesto(testo) {
    return (testo || '')
        .toString()
        .toLowerCase()
        .normalize('NFD')
        .replace(new RegExp('[' + String.fromCharCode(0x0300) + '-' + String.fromCharCode(0x036f) + ']', 'g'), '');
}

function svgMarker(colore, icona) {
    const path = ICONE_CATEGORIA[icona] || ICONA_DEFAULT;
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="26" height="26" fill="#fff" style="background:${colore};border-radius:50%;padding:5px;box-shadow:0 1px 4px rgba(0,0,0,.4)">${path}</svg>`;
}

function mappaServizi() {
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
        mappa: null,
        layerMarker: null,
        layerConfini: null,
        nomiGiorni: NOMI_GIORNI,
        filtriAperti: false,
        suggerimentiAperti: false,
        indiceEvidenziato: -1,

        async init(elementoMappa) {
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

            if (elementoMappa) {
                this.iniziaLeaflet(elementoMappa);
                this.aggiornaMarkers();
                this.caricaConfiniSeDisponibili();
            }
        },

        iniziaLeaflet(elemento) {
            this.mappa = L.map(elemento, { scrollWheelZoom: true });
            this.mappa.setView([45.89, 12.17], 13);
            L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
                attribution: '&copy; OpenStreetMap contributors &copy; CARTO',
                maxZoom: 20,
            }).addTo(this.mappa);
            this.layerMarker = L.layerGroup().addTo(this.mappa);
        },

        async caricaConfiniSeDisponibili() {
            try {
                const risposta = await fetch('/data/confini.geojson', { method: 'GET' });
                if (!risposta.ok) return;
                const geojson = await risposta.json();
                this.layerConfini = L.geoJSON(geojson, {
                    style: { color: '#1e3a8a', weight: 2, fill: false, dashArray: '4 3' },
                });
                this.confiniDisponibili = true;
            } catch (e) {
                // File non ancora presente: nessun problema, il layer si aggiunge quando disponibile.
            }
        },

        toggleConfini(mostra) {
            if (!this.layerConfini) return;
            if (mostra) this.layerConfini.addTo(this.mappa);
            else this.mappa.removeLayer(this.layerConfini);
        },

        get puntiFiltrati() {
            const ricerca = normalizzaTesto(this.ricerca).split(/\s+/).filter(Boolean);
            return this.punti.filter((p) => {
                if (this.filtroComuni.length && !this.filtroComuni.includes(p.comune_id)) return false;
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
            if (!this.layerMarker) return;
            this.layerMarker.clearLayers();
            for (const p of this.puntiFiltrati) {
                const icona = L.divIcon({
                    html: svgMarker(p.colore_hex, p.categoria_icona),
                    className: '',
                    iconSize: [26, 26],
                    iconAnchor: [13, 13],
                });
                const marker = L.marker([p.lat, p.lng], {
                    icon: icona,
                    keyboard: true,
                    alt: `${p.nome} (${p.categoria_nome})`,
                });
                marker.on('click', () => this.seleziona(p));
                marker.addTo(this.layerMarker);
            }
        },

        seleziona(punto) {
            this.selezionato = punto;
            if (this.mappa) this.mappa.panTo([punto.lat, punto.lng]);
        },

        chiudiDettaglio() {
            this.selezionato = null;
        },

        linkIndicazioni(punto) {
            return `https://www.google.com/maps/dir/?api=1&destination=${punto.lat},${punto.lng}`;
        },
    };
}
