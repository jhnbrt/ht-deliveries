import { mkdir, copyFile, readdir } from 'node:fs/promises';
import path from 'node:path';
const target = 'public/ocr';
await mkdir(`${target}/core`, {recursive:true});
await mkdir(`${target}/lang`, {recursive:true});
await copyFile('node_modules/tesseract.js/dist/worker.min.js', `${target}/worker.min.js`);
for (const name of await readdir('node_modules/tesseract.js-core')) {
    if (/^tesseract-core.*\.(js|wasm)$/.test(name)) {
        await copyFile(path.join('node_modules/tesseract.js-core', name), `${target}/core/${name}`);
    }
}
await copyFile('node_modules/@tesseract.js-data/eng/4.0.0/eng.traineddata.gz', `${target}/lang/eng.traineddata.gz`);
await copyFile('node_modules/tesseract.js/LICENSE.md', `${target}/TESSERACT-LICENSE.md`);
await copyFile('node_modules/tesseract.js-core/LICENSE', `${target}/CORE-LICENSE`);
console.log('Free OCR assets copied.');
