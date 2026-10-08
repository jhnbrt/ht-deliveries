@extends('layouts.app')
@section('title', 'Export Data')
@section('content')
<p class="intro">Export approved delivery rows, with one item per row. Copy the table and paste into cell A1 in Google Sheets.</p>
<form method="GET" class="delivery-filters"><label>Export branch<select name="branch"><option value="">All branches</option>
@foreach($branches as $summary)
@if($summary->branch !== '')
<option value="{{ $summary->branch }}" @selected($branch === $summary->branch)>{{ $summary->branch }}</option>
@endif
@endforeach
</select></label><button class="button secondary">Show selected branch</button></form>
<div class="toolbar"><span class="pill">{{ count($rows) }} approved item rows</span><div class="button-group"><button class="button secondary" id="copy-table" {{ !$rows ? 'disabled' : '' }}>Copy for Google Sheets</button><a class="button" href="{{ route('deliveries.csv', ['branch' => $branch]) }}">↓ Download CSV</a><a class="button" href="{{ route('deliveries.workbook', ['branch' => $branch]) }}">↓ Excel workbook</a></div></div>
<p id="copy-feedback" aria-live="polite" class="subtle"></p><section class="panel"><div class="panel-heading"><h2>Ready to export</h2><span class="subtle">Approved deliveries only</span></div><div class="table-wrap"><table id="export-table"><thead><tr><th>Reference ID</th><th>Branch</th><th>No.</th><th>FS Item</th><th>Item SKU</th><th>Item Quantity</th><th>Total Order</th><th>Received Order</th></tr></thead><tbody>@forelse($rows as $row)<tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>@empty<tr><td colspan="8"><div class="empty-state"><h3>No approved rows yet</h3><p>Approve a delivery to make it available here.</p><a class="button" href="{{ route('deliveries.index') }}">Review Deliveries</a></div></td></tr>@endforelse</tbody></table></div></section>
<textarea id="copy-fallback" class="copy-fallback" aria-label="Spreadsheet data to copy" readonly hidden></textarea>
<div class="notice">Direct Google Sheets syncing is pending setup. Copy-paste and CSV are available now. Excel creates All Summary and one sheet per selected branch. Export includes approved deliveries; avoid pasting the same export twice.</div>
@endsection
