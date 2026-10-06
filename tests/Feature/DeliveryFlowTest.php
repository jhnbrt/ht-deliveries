<?php

use App\Enums\DeliveryStatus;
use App\Jobs\ExtractDeliveryJob;
use App\Models\Delivery;
use App\Services\DeliveryReceiptExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('public');
});

test('the dashboard loads', function () {
    $this->get('/')->assertOk()->assertSee('Recent deliveries');
});

test('uploading DR photos creates deliveries and queues reading', function () {
    Queue::fake();

    $this->post(route('deliveries.store'), [
        'photos' => [UploadedFile::fake()->image('dr1.jpg'), UploadedFile::fake()->image('dr2.jpg')],
    ])->assertRedirect(route('deliveries.index'));

    expect(Delivery::count())->toBe(2)
        ->and(Delivery::first()->status)->toBe(DeliveryStatus::Processing);

    Queue::assertPushed(ExtractDeliveryJob::class, 2);
});

test('the reading job stores reference, branch and items', function () {
    config(['services.anthropic.key' => 'test-key']);

    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [[
        'type' => 'tool_use',
        'name' => 'record_delivery',
        'input' => [
            'reference_id' => 'DR-1001',
            'branch' => 'Cebu Main',
            'items' => [['sku' => 'A-1', 'item' => 'Rice 25kg', 'quantity' => 12]],
        ],
    ]]])]);

    $path = UploadedFile::fake()->image('dr.jpg')->store('deliveries', 'public');
    $delivery = Delivery::create(['image_path' => $path, 'original_name' => 'dr.jpg', 'status' => DeliveryStatus::Processing]);

    (new ExtractDeliveryJob($delivery->id))->handle(app(DeliveryReceiptExtractor::class));

    $delivery->refresh();

    expect($delivery->status)->toBe(DeliveryStatus::PendingReview)
        ->and($delivery->reference_id)->toBe('DR-1001')
        ->and($delivery->branch)->toBe('Cebu Main')
        ->and($delivery->items)->toHaveCount(1);
});

test('a reviewed delivery shows up in the export', function () {
    $delivery = Delivery::create([
        'image_path' => 'deliveries/x.jpg',
        'original_name' => 'x.jpg',
        'status' => DeliveryStatus::PendingReview,
    ]);

    $this->put(route('deliveries.update', $delivery), [
        'action' => 'review',
        'reference_id' => 'DR-2002',
        'branch' => 'Mandaue',
        'items' => [['sku' => 'B-2', 'item' => 'Cooking oil', 'quantity' => 5]],
    ])->assertRedirect();

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Reviewed);

    $this->get(route('export.index'))->assertOk()->assertSee('DR-2002')->assertSee('Cooking oil');
    $this->get(route('export.csv'))->assertOk()->assertDownload();
});
