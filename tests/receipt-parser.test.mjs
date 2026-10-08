import { test } from 'node:test';
import assert from 'node:assert/strict';
import { parseReceipt } from '../resources/js/receipt-parser.js';

function tsv(words) {
    return 'level\tpage_num\tblock_num\tpar_num\tline_num\tword_num\tleft\ttop\twidth\theight\tconf\ttext\n'
        + words.map(([text,x,y,w=70])=>[5,1,1,1,1,1,x,y,w,20,90,text].join('\t')).join('\n');
}

test('orders do not become received quantities even if the received heading is missed', () => {
    const text = 'REFERENCE ID: DR-12\nBRANCH: Tabunok\nTOTAL ORDER';
    const layout=tsv([['SKU',650,50],['QUANTITY',850,50],['1.',10,100,20],['Flour',80,100],['F01-001',650,100],['350',850,100]]);
    const result=parseReceipt(text,layout);
    assert.equal(result.branch,'Tabunok');assert.equal(result.items.length,1);
    assert.equal(result.items[0].quantity,null);assert.equal(result.items[0].ordered_quantity,350);
});

test('a receipt with a single quantity column retains its number', () => {
    const layout=tsv([['SKU',650,50],['QTY',850,50],['1.',10,100,20],['Flour',80,100],['F01-001',650,100],['5',850,100]]);
    assert.equal(parseReceipt('BRANCH: Tabunok',layout).items[0].quantity,5);
});

test('fractional item names survive parsing edited text', () => {
    const result=parseReceipt('REFERENCE ID: DR-1\nBRANCH: Minglanilla\n1/4 Dough Powder DGH-0001');
    assert.equal(result.items[0].item,'1/4 Dough Powder');assert.equal(result.items[0].quantity,null);
});

test('missing columns return a warning and no invented item rows', () => {
    const result=parseReceipt('Unreadable stamp');
    assert.equal(result.reference_id,null);assert.equal(result.items.length,0);assert.equal(result.warnings.length,2);
});

test('received numbers come from their own column and total order preserves units', () => {
    const layout=tsv([['SKU',650,50],['QUANTITY',850,50],['TOTAL',1000,50],['RECEIVED',1250,50],['1.',10,100,20],['Flour',80,100],['F01-001',650,100],['350',850,100],['14',1000,100,20],['bags',1040,100],['12',1250,100]]);
    const item=parseReceipt('TOTAL ORDER RECEIVED ORDER',layout).items[0];
    assert.equal(item.item_quantity,350);assert.equal(item.total_order,'14 bags');assert.equal(item.quantity,12);assert.equal(item.line_number,'1');
});

test('right aligned quantities and slightly displaced numbers match the item row', () => {
 const layout=tsv([['SKU',650,50],['QUANTITY',850,50],['TOTAL',1054,50],['RECEIVED',1250,50],['1.',10,100,20],['Flour',80,100],['F01-001',650,100],['350',970,116,35],['14',1070,113,20],['bags',1110,113],['12',1300,115]]);
 const item=parseReceipt('TOTAL ORDER RECEIVED ORDER',layout).items[0];
 assert.equal(item.item_quantity,350);assert.equal(item.total_order,'14 bags');assert.equal(item.quantity,12);
});
