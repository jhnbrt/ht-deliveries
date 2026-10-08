<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function receiptReadingRecord(array $attributes = []): int
{
    return DB::table('deliveries')->insertGetId(array_merge([
        'photo_path' => 'deliveries/dr.jpg', 'original_name' => 'dr.jpg',
        'status' => 'review', 'items' => '[]', 'created_at' => now(), 'updated_at' => now(),
    ], $attributes));
}

function receiptReadingResult(): array
{
    return [
        'reference_id' => 'PO-BRVT28TI-EZ7', 'branch' => 'Minglanilla',
        'raw_text' => 'Regular Cup 3pcs | TPC-0028 | Ordered 350 | Received blank',
        'warnings' => ['Received Order is blank. Confirm actual quantities.'],
        'items' => [['sku' => 'TPC-0028', 'item' => 'Regular Cup 3pcs',
            'quantity' => null, 'ordered_quantity' => 350, 'unit' => 'pc',
            'note' => 'Received quantity needs confirmation.']],
    ];
}

it('saves free browser OCR without making an external API call', function () {
    $id = receiptReadingRecord();
    Http::preventStrayRequests();

    $response = $this->postJson(route('deliveries.read', $id), receiptReadingResult());

    $response->assertOk()->assertJsonPath('branch', 'Minglanilla')->assertJsonPath('items.0.quantity', null);
    $this->assertDatabaseHas('deliveries', ['id' => $id, 'status' => 'review', 'items' => '[]']);
    expect(json_decode(DB::table('deliveries')->where('id', $id)->value('reading_result'), true))
        ->toBe(receiptReadingResult());
    Http::assertNothingSent();
});

it('does not overwrite corrected delivery fields when new OCR is saved', function () {
    $id = receiptReadingRecord(['reference_id' => 'CORRECTED', 'items' => json_encode([
        ['sku' => 'TPC-0028', 'item' => 'Cup', 'quantity' => 300],
    ])]);

    $this->postJson(route('deliveries.read', $id), receiptReadingResult())->assertOk();

    $this->assertDatabaseHas('deliveries', ['id' => $id, 'reference_id' => 'CORRECTED']);
    expect(json_decode(DB::table('deliveries')->where('id', $id)->value('items'), true)[0]['quantity'])->toBe(300);
});

it('rejects invalid OCR without saving a reading', function () {
    $id = receiptReadingRecord();

    $response = $this->postJson(route('deliveries.read', $id), ['raw_text' => '']);

    $response->assertUnprocessable()->assertJsonValidationErrors(['raw_text', 'warnings', 'items']);
    $this->assertDatabaseHas('deliveries', ['id' => $id, 'reading_result' => null]);
});

it('does not replace reading on an approved receipt', function () {
    $id = receiptReadingRecord(['status' => 'completed']);

    $this->postJson(route('deliveries.read', $id), receiptReadingResult())->assertConflict();

    $this->assertDatabaseHas('deliveries', ['id' => $id, 'reading_result' => null]);
});

it('preserves edited raw text when review fields are saved', function () {
    $id = receiptReadingRecord(['reading_result' => json_encode(receiptReadingResult())]);

    $this->put(route('deliveries.update', $id), [
        'reference_id' => 'DR-12', 'branch' => 'Tabunok', 'action' => 'save',
        'raw_text' => 'Corrected receipt text',
        'items' => [['sku' => 'TPC-0028', 'item' => 'Cup', 'quantity' => 300]],
    ])->assertRedirect(route('deliveries.index', ['status' => 'review']));

    expect(json_decode(DB::table('deliveries')->where('id', $id)->value('reading_result'), true)['raw_text'])
        ->toBe('Corrected receipt text');
});

it('returns not found for a missing receipt', function () {
    $this->postJson(route('deliveries.read', 999), receiptReadingResult())->assertNotFound();
});
