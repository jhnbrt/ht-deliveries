
const photos = document.querySelector('#photos');
if (photos) {
    const zone = document.querySelector('#drop-zone');
    const previews = document.querySelector('#photo-previews');
    const feedback = document.querySelector('#upload-feedback');
    let objectUrls = [];
    function showPhotos() {
        objectUrls.forEach(URL.revokeObjectURL);
        objectUrls = [];
        previews.replaceChildren();
        const files = Array.from(photos.files);
        const invalid = files.length > 10 || files.some(file => file.size > 10 * 1024 * 1024 || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type));
        photos.setCustomValidity(invalid ? 'Select up to ten JPG, PNG or WEBP photos, each under 10 MB.' : '');
        feedback.textContent = invalid ? photos.validationMessage : `${files.length} photo(s) selected`;
        files.slice(0, 10).forEach(file => {
            if (!file.type.startsWith('image/')) { return; }
            const figure = document.createElement('figure');
            const img = document.createElement('img');
            const caption = document.createElement('figcaption');
            const url = URL.createObjectURL(file);
            objectUrls.push(url);
            img.src = url;
            img.alt = file.name;
            caption.textContent = file.name;
            figure.append(img, caption);
            previews.append(figure);
        });
    }
    photos.addEventListener('change', showPhotos);
    ['dragenter', 'dragover'].forEach(event => zone.addEventListener(event, e => { e.preventDefault(); zone.classList.add('dragging'); }));
    ['dragleave', 'drop'].forEach(event => zone.addEventListener(event, e => { e.preventDefault(); zone.classList.remove('dragging'); }));
    zone.addEventListener('drop', e => { photos.files = e.dataTransfer.files; showPhotos(); });
    document.querySelector('#upload-form').addEventListener('submit', () => {
        const button = document.querySelector('#upload-form button[type=submit]');
        button.disabled = true;
        button.textContent = 'Uploading…';
    });
}

const itemRows = document.querySelector('#item-rows');
if (itemRows) {
    let index = itemRows.children.length;
    document.querySelector('#add-row').addEventListener('click', () => {
        if (itemRows.children.length >= 200) { return; }
        index = Math.max(index, ...Array.from(itemRows.querySelectorAll('input')).map(input => Number(input.name.match(/items\[(\d+)\]/)?.[1] ?? -1) + 1));
        const row = document.createElement('tr');
        row.innerHTML = `<td><input name="items[${index}][line_number]" aria-label="Number" value="${itemRows.children.length + 1}" maxlength="255"></td><td><input name="items[${index}][item]" aria-label="FS Item" required maxlength="255"></td><td><input name="items[${index}][sku]" aria-label="Item SKU" maxlength="255"></td><td><input type="number" name="items[${index}][item_quantity]" aria-label="Item quantity" step="any" min="0" max="99999999"></td><td><input name="items[${index}][total_order]" aria-label="Total order" maxlength="255"></td><td><input type="number" name="items[${index}][quantity]" aria-label="Received order" step="any" min="0" max="99999999" required></td><td><button type="button" class="remove-row" aria-label="Remove item">×</button></td>`;
        itemRows.append(row);
        row.querySelector('input').focus();
        index++;
    });
    itemRows.addEventListener('click', e => {
        if (e.target.closest('.remove-row') && itemRows.children.length > 1) { e.target.closest('tr').remove(); }
    });
}

const copyButton = document.querySelector('#copy-table');
if (copyButton) {
    copyButton.addEventListener('click', async () => {
        const text = Array.from(document.querySelectorAll('#export-table tr')).map(row => Array.from(row.cells).map(cell => {
            let value = cell.textContent.replace(/[\t\r\n]/g, ' ').trim();
            if (/^\s*[=+@-]/u.test(value)) { value = `'${value}`; }
            return value;
        }).join('\t')).join('\n');
        const feedback = document.querySelector('#copy-feedback');
        try {
            await navigator.clipboard.writeText(text);
            feedback.textContent = 'Copied! Open Google Sheets, select cell A1, then press Ctrl+V (Cmd+V on Mac).';
        } catch {
            const fallback = document.querySelector('#copy-fallback');
            fallback.hidden = false;
            fallback.value = text;
            fallback.focus();
            fallback.select();
            feedback.textContent = 'Press Ctrl+C (Cmd+C on Mac) to copy the selected data, then paste into Google Sheets.';
        }
    });
}



import './free-receipt-reader';

const photoViewport = document.querySelector('.photo-viewport');
if (photoViewport) {
    let zoom = 1;
    const setZoom = value => {
        zoom = Math.min(3, Math.max(1, value));
        photoViewport.style.setProperty('--photo-zoom', zoom);
        photoViewport.classList.toggle('zoomed', zoom > 1);
        document.querySelector('#photo-zoom-label').textContent = zoom === 1 ? 'Fit' : `${Math.round(zoom * 100)}%`;
        document.querySelector('#photo-zoom-out').disabled = zoom === 1;
        document.querySelector('#photo-zoom-in').disabled = zoom === 3;
    };
    document.querySelector('#photo-zoom-in').addEventListener('click', () => setZoom(zoom + .25));
    document.querySelector('#photo-zoom-out').addEventListener('click', () => setZoom(zoom - .25));
    document.querySelector('#photo-fit').addEventListener('click', () => { setZoom(1); photoViewport.scrollTo(0,0); });
    setZoom(1);
}
