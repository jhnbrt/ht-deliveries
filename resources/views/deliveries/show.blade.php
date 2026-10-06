@extends('layouts.app')

@use('App\Enums\DeliveryStatus')

@section('title', $delivery->label)
@section('heading', 'Review '.$delivery->label)

@push('head')
    @if ($delivery->status === DeliveryStatus::Processing)<meta http-equiv="refresh" content="4">@endif
@endpush

@section('content')
    @php
        $rows = old('items', $delivery->items->map(fn ($i) => [
            'sku' => $i->sku, 'item' => $i->item, 'quantity' => $i->display_quantity,
        ])->all());
        $rows = $rows ?: [['sku' => '', 'item' => '', 'quantity' => '']];
    @endphp

    <div class="mb-5 flex flex-wrap items-center gap-3">
        <a href="{{ route('deliveries.index') }}" class="pill hover:bg-soft">&larr; Back</a>
        <x-status-badge :status="$delivery->status" class="rounded-2xl bg-surface px-4 py-3" />
        <span class="text-sm text-muted">{{ $delivery->original_name }}</span>
    </div>

    <div class="grid gap-5 xl:grid-cols-5">
        {{-- Photo --}}
        <section class="card xl:col-span-2">
            <div class="max-h-[75vh] overflow-auto rounded-2xl bg-canvas">
                <img src="{{ $delivery->image_url }}" alt="DR photo" data-zoom class="max-w-full cursor-zoom-in">
            </div>
            <p class="mt-3 text-xs text-muted">Click the photo to zoom in and out.</p>
        </section>

        {{-- Form --}}
        <section class="card xl:col-span-3">
            @if ($delivery->status === DeliveryStatus::Processing)
                <div class="grid place-items-center gap-3 py-20 text-center">
                    <x-icon name="refresh" class="size-10 animate-spin text-brand" />
                    <p class="text-lg font-bold">Reading this DR...</p>
                    <p class="text-sm text-muted">This page refreshes by itself. Make sure the queue worker is running (<code>composer dev</code>).</p>
                </div>
            @else
                @if ($delivery->status === DeliveryStatus::Failed)
                    <div class="mb-5 flex flex-wrap items-center gap-3 rounded-2xl bg-red-50 px-5 py-3.5 text-sm text-red-600 dark:bg-red-950/40 dark:text-red-300">
                        <span class="min-w-0 flex-1">Could not read this DR: {{ $delivery->error }}. You can try again or type it in yourself.</span>
                        <button form="retry-form" class="btn btn-danger !py-2"><x-icon name="refresh" class="size-4" /> Try again</button>
                    </div>
                @endif

                @if ($duplicates)
                    <div class="mb-5 rounded-2xl bg-amber-50 px-5 py-3.5 text-sm text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                        Heads up: {{ $duplicates }} other {{ Str::plural('delivery', $duplicates) }} already ha{{ $duplicates === 1 ? 's' : 've' }} reference {{ $delivery->reference_id }}. This could be a duplicate upload.
                    </div>
                @endif

                <form method="POST" action="{{ route('deliveries.update', $delivery) }}">
                    @csrf @method('PUT')

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="reference_id">Reference ID</label>
                            <input id="reference_id" name="reference_id" class="input" value="{{ old('reference_id', $delivery->reference_id) }}">
                        </div>
                        <div>
                            <label class="label" for="branch">Branch</label>
                            <input id="branch" name="branch" class="input" value="{{ old('branch', $delivery->branch) }}">
                        </div>
                    </div>

                    <div data-repeater data-next="{{ count($rows) }}" class="mt-6">
                        <div class="-mx-1 overflow-x-auto">
                            <table class="w-full min-w-[520px]">
                                <thead><tr><th class="th w-[28%]">SKU</th><th class="th">Item</th><th class="th w-24">Quantity</th><th class="th w-10"></th></tr></thead>
                                <tbody data-rows>
                                    @foreach ($rows as $i => $row)
                                        <tr>
                                            <td class="p-1"><input name="items[{{ $i }}][sku]" value="{{ $row['sku'] ?? '' }}" class="input" aria-label="SKU"></td>
                                            <td class="p-1"><input name="items[{{ $i }}][item]" value="{{ $row['item'] ?? '' }}" class="input" aria-label="Item"></td>
                                            <td class="p-1"><input name="items[{{ $i }}][quantity]" value="{{ $row['quantity'] ?? '' }}" type="number" step="any" min="0" class="input" aria-label="Quantity"></td>
                                            <td class="p-1"><button type="button" data-remove class="grid size-9 place-items-center rounded-lg text-muted hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-950/40" aria-label="Remove row"><x-icon name="x" class="size-4" /></button></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <template>
                            <tr>
                                <td class="p-1"><input name="items[__INDEX__][sku]" class="input" aria-label="SKU"></td>
                                <td class="p-1"><input name="items[__INDEX__][item]" class="input" aria-label="Item"></td>
                                <td class="p-1"><input name="items[__INDEX__][quantity]" type="number" step="any" min="0" class="input" aria-label="Quantity"></td>
                                <td class="p-1"><button type="button" data-remove class="grid size-9 place-items-center rounded-lg text-muted hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-950/40" aria-label="Remove row"><x-icon name="x" class="size-4" /></button></td>
                            </tr>
                        </template>

                        <button type="button" data-add class="btn btn-ghost mt-3 !px-4 !py-2 border border-line"><x-icon name="plus" class="size-4" /> Add item</button>
                    </div>

                    <div class="mt-8 flex flex-wrap justify-end gap-3 border-t border-line pt-5">
                        <button name="action" value="draft" class="btn btn-ghost border border-line">Save draft</button>
                        <button name="action" value="review" class="btn btn-primary"><x-icon name="check" class="size-5" /> Save and mark reviewed</button>
                    </div>
                </form>

                <form id="retry-form" method="POST" action="{{ route('deliveries.retry', $delivery) }}">@csrf</form>
            @endif
        </section>
    </div>
@endsection
