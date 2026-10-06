@extends('layouts.app')
@section('title', 'Review Delivery')
@section('content')
<div class="toolbar"><a class="text-link" href="{{ route('deliveries.index') }}">← Review queue</a><span class="badge {{ $delivery->status }}">{{ $delivery->status === 'completed' ? 'Completed' : 'For review' }}</span></div>
<div class="notice">Compare the fields with your receipt photo. Automatic extraction is pending setup; enter the details below.</div>
<div class="review-grid"><section class="panel photo-panel"><div class="panel-heading"><h2>Original receipt</h2><a class="text-link" href="{{ route('deliveries.photo', $delivery->id) }}" target="_blank" rel="noopener">Enlarge ↗</a></div><img class="receipt-image" src="{{ route('deliveries.photo', $delivery->id) }}" alt="Delivery receipt {{ $delivery->original_name }}"><p class="subtle">{{ $delivery->original_name }}</p></section>
<section class="panel"><div class="panel-heading"><h2>Delivery details</h2><span class="pill">DR #{{ $delivery->id }}</span></div>
<form method="POST" action="{{ route('deliveries.update', $delivery->id) }}" id="review-form">@csrf @method('PUT')
<div class="field-grid"><label>Reference ID<input name="reference_id" value="{{ old('reference_id', $delivery->reference_id) }}" placeholder="e.g. DR-00125" required maxlength="255"></label><label>Branch<input name="branch" value="{{ old('branch', $delivery->branch) }}" placeholder="e.g. Tabunok" required maxlength="255"></label></div>
<p class="subtle">Enter one row for each item on this receipt. SKU is optional.</p>
<div class="table-wrap"><table class="editable-table"><thead><tr><th>SKU</th><th>Item</th><th>Quantity</th><th></th></tr></thead><tbody id="item-rows">
@foreach(old('items', $delivery->items ?: [['sku' => '', 'item' => '', 'quantity' => '']]) as $index => $item)
<tr><td><input name="items[{{ $index }}][sku]" value="{{ $item['sku'] ?? '' }}" aria-label="SKU" maxlength="255"></td><td><input name="items[{{ $index }}][item]" value="{{ $item['item'] ?? '' }}" aria-label="Item" required maxlength="255"></td><td><input type="number" step="any" min="0.000001" max="99999999" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? '' }}" aria-label="Quantity" required></td><td><button type="button" class="remove-row" aria-label="Remove item">×</button></td></tr>
@endforeach
</tbody></table></div><button class="button secondary add-row" type="button" id="add-row">+ Add item</button>
<div class="form-actions"><button class="button secondary" name="action" value="save">Save for Review</button><button class="button" name="action" value="approve">✓ Approve Delivery</button></div></form></section></div>
@endsection
