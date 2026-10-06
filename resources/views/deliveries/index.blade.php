@extends('layouts.app')
@section('title', $status === 'completed' ? 'Completed Deliveries' : 'Review Deliveries')
@section('content')
<div class="toolbar"><p class="subtle">{{ $status === 'completed' ? 'Approved receipts ready for your Google Sheet.' : 'Check the photo, enter the details, then approve.' }}</p><a class="button" href="{{ $status === 'completed' ? route('deliveries.export') : route('deliveries.create') }}">{{ $status === 'completed' ? '↗ Export Data' : '↑ Upload Deliveries' }}</a></div>
<section class="panel"><div class="panel-heading"><h2>{{ $status === 'completed' ? 'Completed' : 'Review queue' }}</h2><form method="GET" action="{{ route('deliveries.index') }}" class="search-form"><input type="hidden" name="status" value="{{ $status }}"><input type="search" name="search" value="{{ $search }}" placeholder="Reference ID or branch" aria-label="Search deliveries"><button class="button secondary">Search</button></form></div>@include('deliveries.table')<div class="pagination">{{ $deliveries->links() }}</div></section>
@endsection
