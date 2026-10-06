<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('saves an uploaded receipt and opens its review screen', function () {
    Storage::fake('local');

    $response = $this->post(route('deliveries.store'), [
        'photos' => [UploadedFile::fake()->image('receipt.jpg')],
    ]);

    $delivery = DB::table('deliveries')->first();
    $response->assertRedirect(route('deliveries.edit', $delivery->id));
    $this->assertDatabaseHas('deliveries', ['status' => 'review', 'original_name' => 'receipt.jpg']);
    Storage::disk('local')->assertExists($delivery->photo_path);
});

it('rejects a non-image upload without saving a delivery', function () {
    $response = $this->post(route('deliveries.store'), [
        'photos' => [UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf')],
    ]);

    $response->assertSessionHasErrors('photos.0');
    $this->assertDatabaseCount('deliveries', 0);
});

it('requires positive quantities before approval', function () {
    $id = DB::table('deliveries')->insertGetId([
        'photo_path' => 'deliveries/photo.jpg', 'original_name' => 'photo.jpg',
        'status' => 'review', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $response = $this->put(route('deliveries.update', $id), [
        'reference_id' => 'DR-12', 'branch' => 'Tabunok', 'action' => 'approve',
        'items' => [['sku' => '', 'item' => 'Flour', 'quantity' => 0]],
    ]);

    $response->assertSessionHasErrors('items.0.quantity');
    $this->assertDatabaseHas('deliveries', ['id' => $id, 'status' => 'review']);
});

it('approves reviewed rows and exports them as csv', function () {
    $id = DB::table('deliveries')->insertGetId([
        'photo_path' => 'deliveries/photo.jpg', 'original_name' => 'photo.jpg',
        'status' => 'review', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->put(route('deliveries.update', $id), [
        'reference_id' => 'DR-12', 'branch' => 'Tabunok', 'action' => 'approve',
        'items' => [['sku' => 'F01', 'item' => 'Flour', 'quantity' => 10]],
    ])->assertRedirect(route('deliveries.index', ['status' => 'completed']));
    $this->assertDatabaseHas('deliveries', ['id' => $id, 'status' => 'completed']);

    $response = $this->get(route('deliveries.csv'));

    $response->assertStreamedContent("\xEF\xBB\xBF\"Reference ID\",Branch,SKU,Item,Quantity\nDR-12,Tabunok,F01,Flour,10\n");
});

it('excludes unapproved rows and neutralizes spreadsheet formulas', function () {
    DB::table('deliveries')->insert([
        ['photo_path' => 'a.jpg', 'original_name' => 'a.jpg', 'status' => 'review',
            'reference_id' => 'UNAPPROVED', 'branch' => 'Tabunok',
            'items' => json_encode([['sku' => '', 'item' => 'Sugar', 'quantity' => 1]])],
        ['photo_path' => 'b.jpg', 'original_name' => 'b.jpg', 'status' => 'completed',
            'reference_id' => '=1+1', 'branch' => 'Tabunok',
            'items' => json_encode([['sku' => '', 'item' => 'Flour', 'quantity' => 2]])],
    ]);

    $response = $this->get(route('deliveries.csv'));

    $response->assertStreamedContent("\xEF\xBB\xBF\"Reference ID\",Branch,SKU,Item,Quantity\n'=1+1,Tabunok,,Flour,2\n");
});

it('returns not found for a missing delivery', function () {
    $this->get(route('deliveries.edit', 999))->assertNotFound();
});
