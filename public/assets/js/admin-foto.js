// Riduce le foto nel browser prima dell'invio: una foto da telefono (5-10 MB) supererebbe i limiti
// di caricamento tipici degli hosting condivisi (spesso 2-8 MB). Il ridisegno su canvas elimina
// anche i dati EXIF, compresa la posizione GPS. Se qualcosa non è supportato si inviano gli originali,
// che il server verifica e ricodifica comunque.
(function () {
    const form = document.getElementById('form-foto');
    if (!form) return;
    const campo = document.getElementById('campo-foto');
    const stato = document.getElementById('stato-foto');
    const LATO_MASSIMO = 2000;
    const MAX_PER_INVIO = 6;
    let preparato = false;

    async function riduci(file) {
        if (!/^image\/(jpeg|png|webp)$/.test(file.type)) return file;
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        const scala = Math.min(1, LATO_MASSIMO / Math.max(bitmap.width, bitmap.height));
        if (scala === 1 && file.size < 1500000) { bitmap.close(); return file; }
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scala);
        canvas.height = Math.round(bitmap.height * scala);
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff'; // i PNG trasparenti diventano JPEG su sfondo bianco
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();
        const blob = await new Promise((risolvi) => canvas.toBlob(risolvi, 'image/jpeg', 0.85));
        if (!blob) return file;
        return new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' });
    }

    form.addEventListener('submit', async (evento) => {
        if (preparato) return; // secondo invio: i file sono già ridotti
        const file = [...campo.files];
        if (file.length > MAX_PER_INVIO) {
            evento.preventDefault();
            stato.textContent = `Seleziona al massimo ${MAX_PER_INVIO} foto per volta.`;
            return;
        }
        if (!file.length || !window.createImageBitmap || !window.DataTransfer) return;
        evento.preventDefault();
        stato.textContent = 'Preparazione delle foto in corso…';
        form.querySelector('button[type=submit]').disabled = true;
        try {
            const trasferimento = new DataTransfer();
            for (const f of file) trasferimento.items.add(await riduci(f));
            campo.files = trasferimento.files;
        } catch (e) {
            // Browser senza supporto: si inviano gli originali.
        }
        stato.textContent = 'Caricamento in corso…';
        preparato = true;
        form.submit();
    });
})();
