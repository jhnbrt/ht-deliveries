import { parseReceipt } from './receipt-parser';

const panel = document.querySelector('#receipt-reader');
if (panel) {
    const status = document.querySelector('#reader-status');
    const text = document.querySelector('#receipt-text');
    const rows = document.querySelector('#item-rows');
    const readButton = document.querySelector('#read-photo');
    const againButton = document.querySelector('#read-again');
    const cancelButton = document.querySelector('#cancel-reading');
    const progress = document.querySelector('#ocr-progress');
    const rotation = document.querySelector('#ocr-rotation');
    let busy = false;
    let cancelled = false;
    let worker = null;
    let cancelRecognition = null;
    let dirty = panel.dataset.editable !== '1';
    document.querySelector('#review-form').addEventListener('input', () => { dirty = true; });

    function applyReading(data) {
        text.value = data.raw_text;
        const warnings = document.querySelector('#reader-warnings');
        warnings.replaceChildren();
        for (const warning of data.warnings) {
            const li = document.createElement('li'); li.textContent = warning; warnings.append(li);
        }
        if (dirty && !document.querySelector('#replace-reading').checked) {
            status.textContent = 'Text generated and saved. Your fields were preserved. Check Replace the current editable fields, then choose Fill fields from edited text to apply it.';
            return;
        }
        document.querySelector('[name=reference_id]').value = data.reference_id || '';
        document.querySelector('[name=branch]').value = data.branch || '';
        rows.replaceChildren();
        for (const [i,item] of data.items.entries()) {
            const tr = document.createElement('tr');
            for (const name of ['line_number', 'item', 'sku', 'item_quantity', 'total_order', 'quantity']) {
                const td = document.createElement('td'); const input = document.createElement('input');
                input.name = `items[${i}][${name}]`; input.setAttribute('aria-label',name); input.value = item[name] ?? (name === 'line_number' ? i + 1 : '');
                if (name === 'quantity' || name === 'item_quantity') {
                    input.type = 'number'; input.step = 'any'; input.min = '0'; input.max = '99999999'; input.required = name === 'quantity';
                } else { input.maxLength = 255; input.required = name === 'item'; }
                td.append(input);
                if (name === 'quantity') {
                    input.title = item.note || 'Confirm received order against the photo.';
                }
                tr.append(td);
            }
            const td = document.createElement('td'); const button = document.createElement('button');
            button.type = 'button'; button.className = 'remove-row'; button.setAttribute('aria-label','Remove item'); button.textContent = '×';
            td.append(button); tr.append(td); rows.append(tr);
        }
        if (!data.items.length) { document.querySelector('#add-row').click(); }
        dirty = true;
        status.textContent = `Generated text and ${data.items.length} suggested item rows. Confirm every value and check for missing rows before saving.`;
    }

    async function saveReading(data) {
        const response = await fetch(panel.dataset.url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN':panel.dataset.csrf },
            body: JSON.stringify(data),
        });
        if (!response.ok) { throw new Error('Text was generated but could not be saved. Copy it now, then refresh and retry.'); }
    }

    async function preparePhoto() {
        const image = new Image(); image.src = panel.dataset.photo; await image.decode();
        const selection = document.querySelector('#ocr-rotation').value;
        const angle = selection === 'auto' ? (image.width > image.height ? -90 : 0) : Number(selection);
        const scale = Math.min(1.5, 3000 / Math.max(image.width,image.height));
        const width = Math.round(image.width*scale); const height = Math.round(image.height*scale);
        const canvas = document.querySelector('#ocr-preview');
        canvas.width = Math.abs(angle)%180 === 90 ? height : width;
        canvas.height = Math.abs(angle)%180 === 90 ? width : height;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = 'white'; ctx.fillRect(0,0,canvas.width,canvas.height);
        ctx.save(); ctx.translate(canvas.width/2,canvas.height/2); ctx.rotate(angle*Math.PI/180);
        ctx.drawImage(image,-width/2,-height/2,width,height); ctx.restore();
        canvas.hidden = false;
        document.querySelector('.photo-panel img').hidden = true;
        return canvas;
    }

    async function readPhoto() {
        if (busy || readButton.disabled) { return; }
        busy = true; cancelled = false; rotation.disabled = true;
        readButton.disabled = againButton.disabled = true; cancelButton.disabled = true;
        document.querySelector('.reader-disclosure').open = true;
        progress.value = 0; status.textContent = 'Loading the free reader…';
        try {
            await initialPreview;
            const canvas = await preparePhoto();
            const { createWorker } = await import('tesseract.js');
            if (cancelled) { return; }
            worker = await createWorker('eng',1,{
                workerPath:panel.dataset.worker, corePath:panel.dataset.core,
                langPath:panel.dataset.language, gzip:true,
                logger:message => {
                    if (cancelled) { return; }
                    if (message.status === 'recognizing text') {
                        progress.value = Math.round(message.progress*100);
                        status.textContent = `Reading photo: ${progress.value}%`;
                    }
                },
            });
            if (cancelled) { return; }
            await worker.setParameters({ tessedit_pageseg_mode:document.querySelector('#ocr-mode').value, preserve_interword_spaces:'1' });
            cancelButton.disabled = false;
            const cancellation = new Promise((resolve, reject) => { cancelRecognition = () => reject(new Error('Reading cancelled.')); });
            const result = await Promise.race([worker.recognize(canvas, {rotateAuto:true}, {text:true,tsv:true}), cancellation]);
            if (cancelled) { return; }
            let layout = result.data.tsv;
            const lines = layout.split(/\r?\n/);
            const words = lines.slice(1).map(line => line.split('\t')).filter(cells => cells[0] === '5');
            const header = pattern => words.find(cells => pattern.test(cells[11] || ''));
            const sku = header(/SKU/i), qty = header(/QUANTITY|QTY/i), total = header(/^TOTAL$/i), received = header(/RECEIVED/i);
            if (sku && qty && total && received) {
                status.textContent = 'Reading item quantity, total order and received order columns…';
                const startY = Math.max(...[qty,total,received].map(c => Number(c[7]) + Number(c[9]))) + 4;
                const boundaries = [
                    (Number(sku[6]) + Number(sku[8]) + Number(qty[6])) / 2,
                    (Number(qty[6]) + Number(qty[8]) + Number(total[6])) / 2,
                    (Number(total[6]) + Number(total[8]) + Number(received[6])) / 2,
                    canvas.width,
                ].map(Math.round);
                const kept = lines.slice(1).filter(line => {
                    const cells = line.split('\t');
                    return cells[0] !== '5' || Number(cells[7]) < startY || Number(cells[6]) < boundaries[0];
                });
                await worker.setParameters({tessedit_pageseg_mode:'6'});
                for (let column = 0; column < 3; column++) {
                    if (cancelled) { return; }
                    const left = boundaries[column], width = boundaries[column + 1] - left;
                    if (width <= 0 || startY >= canvas.height) { continue; }
                    const crop = document.createElement('canvas'); crop.width = width; crop.height = canvas.height - startY;
                    crop.getContext('2d').drawImage(canvas,left,startY,width,crop.height,0,0,width,crop.height);
                    const detail = await Promise.race([worker.recognize(crop, {}, {text:true,tsv:true}), cancellation]);
                    for (const line of detail.data.tsv.split(/\r?\n/).slice(1)) {
                        const cells = line.split('\t');
                        if (cells[0] !== '5') { continue; }
                        cells[6] = String(Number(cells[6]) + left); cells[7] = String(Number(cells[7]) + startY);
                        kept.push(cells.join('\t'));
                    }
                }
                layout = [lines[0], ...kept].join('\n');
            }
            const data = parseReceipt(result.data.text, layout);
            if (!data.raw_text.trim()) { throw new Error('No text detected. Change the orientation or use a clearer photo.'); }
            data.warnings.unshift(`OCR confidence: ${Math.round(result.data.confidence)}%. This is an engine estimate, not a guarantee of accuracy.`);
            applyReading(data); progress.value = 100;
            await saveReading(data);
        } catch (error) {
            status.textContent = cancelled ? 'Reading cancelled. Your photo is saved.' : error.message;
        } finally {
            if (worker) { await worker.terminate().catch(() => {}); worker = null; }
            cancelRecognition = null;
            busy = false; rotation.disabled = false; readButton.disabled = againButton.disabled = false; cancelButton.disabled = true;
        }
    }
    readButton.addEventListener('click',readPhoto); againButton.addEventListener('click',readPhoto);
    cancelButton.addEventListener('click', () => { cancelled = true; cancelRecognition?.(); status.textContent = 'Cancelling reading…'; if(worker) { worker.terminate().catch(() => {}); } });
    document.querySelector('#save-receipt-text').addEventListener('click', async () => {
        if (!text.value.trim()) { status.textContent = 'Read the photo or enter text first.'; return; }
        try { await saveReading(parseReceipt(text.value)); status.textContent = 'Edited receipt text saved. Your delivery fields are unchanged.'; }
        catch(error) { status.textContent = error.message; }
    });
    document.querySelector('#parse-receipt-text').addEventListener('click', () => {
        const data = parseReceipt(text.value); applyReading(data);
    });
    document.querySelector('#copy-receipt-text').addEventListener('click',async () => {
        if (!text.value) { status.textContent = 'Read the photo first.'; return; }
        try { await navigator.clipboard.writeText(text.value); status.textContent = 'Text copied.'; }
        catch { text.closest('details').open = true; text.focus(); text.select(); status.textContent = 'Press Ctrl+C to copy the selected text.'; }
    });
    document.querySelector('#download-receipt-text').addEventListener('click', () => {
        if (!text.value) { status.textContent = 'Read the photo first.'; return; }
        const url = URL.createObjectURL(new Blob([text.value],{type:'text/plain;charset=utf-8'}));
        const link = document.createElement('a'); link.href = url; link.download = 'delivery-receipt.txt'; link.click();
        setTimeout(() => URL.revokeObjectURL(url),1000);
    });
    const initialPreview = preparePhoto().catch(() => {
        status.textContent = 'Photo preview could not load. Refresh the page to try again.';
    });
    rotation.addEventListener('change', () => {
        if (!busy) { preparePhoto().catch(() => { status.textContent = 'Photo preview could not load.'; }); }
    });
    const saved = document.querySelector('#saved-reading');
    const cached = saved ? JSON.parse(saved.textContent) : null;
    if (panel.dataset.auto === '1') {
        if (cached) { applyReading(cached); } else { readPhoto(); }
    }
}
