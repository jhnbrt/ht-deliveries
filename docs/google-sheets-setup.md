# Send reviewed DRs to Google Sheets (optional)

You can always use **Copy for Google Sheets** on the Export page and paste. For one-click sending:

1. Create a Google Sheet. Add this header row in row 1: `Reference ID | Branch | SKU | Item | Quantity`.
2. In the sheet: **Extensions > Apps Script**. Replace the code with:

```js
const SECRET = 'change-me'; // same value as GOOGLE_SHEETS_WEBHOOK_SECRET

function doPost(e) {
  try {
    const body = JSON.parse(e.postData.contents);
    if (body.secret !== SECRET) return out({ ok: false, error: 'bad secret' });
    const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheets()[0];
    const rows = body.rows;
    if (rows.length) {
      sheet.getRange(sheet.getLastRow() + 1, 1, rows.length, rows[0].length).setValues(rows);
    }
    return out({ ok: true });
  } catch (err) {
    return out({ ok: false, error: String(err) });
  }
}

function out(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(ContentService.MimeType.JSON);
}
```

3. **Deploy > New deployment > Web app**. Execute as: *Me*. Who has access: *Anyone*. Copy the Web app URL.
4. In `.env`:

```
GOOGLE_SHEETS_WEBHOOK_URL=<the web app URL>
GOOGLE_SHEETS_WEBHOOK_SECRET=change-me
```

5. `php artisan config:clear`. The **Send to Google Sheet** button now appears on the Export page and appends only rows that were not exported before.
