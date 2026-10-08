@extends('layouts.app')
@section('title', $status === 'all' ? 'All Deliveries' : ($status === 'completed' ? 'Completed Deliveries' : 'Review Deliveries'))
@section('content')
<div class="toolbar"><p class="subtle">{{ $status === 'completed' ? 'Approved receipts ready for your Google Sheet.' : 'Check the photo, enter the details, then approve.' }}</p><a class="button" href="{{ $status === 'completed' ? route('deliveries.export') : route('deliveries.create') }}">{{ $status === 'completed' ? '↗ Export Data' : '↑ Upload Deliveries' }}</a></div>
@include('deliveries.branch-summary')
<section class="panel"><div class="panel-heading"><h2>{{ $deliveries->total() }} deliveries found</h2></div>
<form method="GET" action="{{ route('deliveries.index') }}" class="delivery-filters">
<label>Search<input type="search" name="search" value="{{ $search }}" placeholder="Reference ID or branch"></label>
<label>Branch
    <select name="branch">
        <option value="">All branches</option>
        @foreach($branches as $summary)
            @if($summary->branch !== '')
                <option value="{{ $summary->branch }}" @selected($branch === $summary->branch)>{{ $summary->branch }}</option>
            @endif
        @endforeach
    </select>
</label>
<label>Status<select name="status"><option value="all" @selected($status === 'all')>All statuses</option><option value="review" @selected($status === 'review')>For review</option><option value="completed" @selected($status === 'completed')>Completed</option></select></label>
<label class="unassigned-filter"><input type="checkbox" name="unassigned" value="1" @checked($unassigned)> Branch not assigned</label>
<button class="button">Apply filters</button><a class="text-link" href="{{ route('deliveries.index', ['status' => 'all']) }}">Reset</a>
</form>
@include('deliveries.table')<div class="pagination">{{ $deliveries->links() }}</div></section>
@endsection
