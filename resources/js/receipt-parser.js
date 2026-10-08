export function parseReceipt(text, tsv = '') {
    const reference = text.match(/REFERENCE\s*ID\s*[:：]?\s*([^\n]+)/i);
    const branch = text.match(/BRANCH\s*[:：]?\s*([^\n]+)/i);
    const cleanHeader = value => (value || '').trim().replace(/\s*[|;]\s*$/, '').slice(0, 255);
    const words = tsv.split(/\r?\n/).slice(1).map(line => {
        const cells = line.split('\t');
        return { level: Number(cells[0]), x: Number(cells[6]), y: Number(cells[7]), w: Number(cells[8]), h: Number(cells[9]), text: cells.slice(11).join('\t').trim() };
    }).filter(word => word.level === 5 && word.text && Number.isFinite(word.x));
    const skuHeader = words.find(word => /SKU/i.test(word.text));
    const receivedHeader = words.find(word => /RECEIVED/i.test(word.text));
    const totalHeader = words.find(word => /^TOTAL$/i.test(word.text));
    const quantityHeader = words.find(word => /QUANTITY|QTY/i.test(word.text));
    const hasReceived = Boolean(receivedHeader) || /RECEIVED\s*ORDER|TOTAL\s*ORDER|ORDERED|ORDER\s*QUANTITY/i.test(text);
    const items = [];
    const skuPattern = /[A-Z0-9$]{2,5}-[A-Z0-9]{3,5}/i;
    const candidates = words.filter(word => skuPattern.test(word.text) && !/FSI-/i.test(word.text)
        && (!skuHeader || Math.abs(word.x - skuHeader.x) < 160) && (!skuHeader || word.y > skuHeader.y));
    for (const sku of candidates) {
        const mid = sku.y + sku.h / 2;
        const otherRows = candidates.filter(word => word !== sku).map(word => Math.abs(word.y + word.h / 2 - mid)).filter(distance => distance > 5);
        const tolerance = Math.max(12, Math.min(28, Math.min(...otherRows) / 2));
        const row = words.filter(word => Math.abs(word.y + word.h / 2 - mid) < tolerance).sort((a, b) => a.x - b.x);
        const itemWords = row.filter(word => word.x + word.w < sku.x - 4);
        let item = itemWords.map(word => word.text).join(' ').replace(/^[^A-Za-z0-9]+/, '').replace(/^\d+[.)\]—-]?\s+(?=[A-Za-z])/, '').trim();
        if (!item) { continue; }
        const numeric = word => /^\d+(?:[.,]\d+)?$/.test(word.text) ? Number(word.text.replace(',', '.')) : null;
        const quantityEnd = totalHeader ? (quantityHeader.x + quantityHeader.w + totalHeader.x) / 2 : receivedHeader ? receivedHeader.x - 35 : Infinity;
        const quantityWord = quantityHeader && row.find(word => word.x > sku.x + sku.w && word.x < quantityEnd && numeric(word) !== null);
        const ordered = quantityWord ? numeric(quantityWord) : null;
        const columnText = (start, end = Infinity) => row.filter(word => word.x >= start && word.x < end).map(word => word.text).join(' ').trim();
        const receivedWord = receivedHeader && row.find(word => word.x >= receivedHeader.x - 35 && numeric(word) !== null);
        const received = receivedWord ? numeric(receivedWord) : null;
        const totalOrder = totalHeader ? columnText(totalHeader.x - 35, receivedHeader ? receivedHeader.x - 35 : Infinity) : '';
        const number = itemWords[0]?.text.match(/^\d+[.)]?$/)?.[0].replace(/[.)]/g, '') || String(items.length + 1);
        items.push({ sku: sku.text.slice(0, 255), item: item.slice(0, 255),
            line_number: number, item_quantity: ordered, total_order: totalOrder || null,
            quantity: hasReceived ? received : ordered, ordered_quantity: hasReceived ? ordered : null,
            unit: null, note: hasReceived ? 'Check the received quantity against the photo; unreadable values are blank.' : 'Check this OCR row against the photo.' });
    }
    if (!items.length) {
        for (const line of text.split(/\r?\n/)) {
            const match = line.match(skuPattern);
            if (!match || /FSI-/i.test(match[0])) { continue; }
            const item = line.slice(0, match.index).replace(/^[^A-Za-z0-9]+/, '').replace(/^\d+[.)\]—-]?\s+(?=[A-Za-z])/, '').trim();
            if (item && !/reference|branch/i.test(item)) {
                items.push({ sku: match[0], item: item.slice(0,255), line_number: String(items.length + 1), item_quantity: null, total_order: null, quantity: null, ordered_quantity: null, unit: null, note: 'Confirm the quantity from the photo.' });
            }
        }
    }
    const warnings = ['Free OCR can misread letters, numbers or omit rows under stamps. Compare every row with the original photo.'];
    if (hasReceived) { warnings.push('This receipt separates ordered and received quantities. Received quantities are suggested only from the received column. Check them; unreadable values remain blank.'); }
    if (!items.length) { warnings.push('Item columns were not recognized. Use the generated text and enter item rows manually.'); }
    return { reference_id: cleanHeader(reference?.[1]) || null, branch: cleanHeader(branch?.[1]) || null,
        raw_text: text, warnings, items: items.slice(0, 200) };
}
