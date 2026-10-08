@extends('layouts.app')
@section('title', 'Delivery Overview')
@section('content')
<div class="toolbar"><div><span class="pill">◷ All deliveries</span><span class="subtle">Your delivery overview</span></div><a class="button" href="{{ route('deliveries.create') }}">↑ Upload Deliveries</a></div>
<div class="stats-grid">
    <div class="stat-card"><span>Total Deliveries</span><span class="stat-icon green">▤</span><strong>{{ number_format($total) }}</strong><small>Receipt photos in your workspace</small></div>
    <div class="stat-card"><span>For Review</span><span class="stat-icon orange">◷</span><strong>{{ number_format($review) }}</strong><small>Waiting for details and approval</small></div>
    <div class="stat-card"><span>Completed</span><span class="stat-icon lime">✓</span><strong>{{ number_format($completed) }}</strong><small>Approved and ready to export</small></div>
</div>
@include('deliveries.branch-summary')
<div class="dashboard-grid">
    <section class="panel statistics"><div class="panel-heading"><h2>Delivery Statistics</h2></div><div class="legend"><div><i class="dot green-bg"></i> Completed <b>{{ $total ? round($completed / $total * 100) : 0 }}%</b></div><div><i class="dot orange-bg"></i> For review <b>{{ $total ? round($review / $total * 100) : 0 }}%</b></div></div><div class="donut" style="--completed: {{ $total ? ($completed / $total * 100) : 0 }}%; {{ !$total ? 'background: #edf0e8;' : '' }}"><div><small>Total deliveries</small><strong>{{ $total }}</strong></div></div><p class="subtle center">Review each photo before exporting.</p><div class="branch-statistics"><h3>Share of deliveries by branch</h3><p class="subtle">Branch receipts ÷ all uploaded receipts</p>
@forelse($branches as $summary)
    @php($share = $total ? $summary->total / $total * 100 : 0)
    <a class="branch-stat-row" href="{{ route('deliveries.index', ['status' => 'all'] + ($summary->branch === '' ? ['unassigned' => 1] : ['branch' => $summary->branch])) }}">
        <div><span>{{ $summary->branch ?: 'Branch not assigned' }}</span><strong>{{ number_format($share, 1) }}%</strong></div>
        <div class="branch-progress"><span style="width: {{ $share }}%"></span></div>
        <small>{{ number_format($summary->total) }} {{ $summary->total == 1 ? 'delivery' : 'deliveries' }}</small>
    </a>
@empty
    <p class="subtle">No deliveries yet.</p>
@endforelse
</div></section>
    <section class="panel"><div class="panel-heading"><h2>Recent Deliveries</h2><a class="text-link" href="{{ route('deliveries.index') }}">View review queue →</a></div>@include('deliveries.table')</section>
</div>
<div class="workflow"><span><b>01</b> Upload photo</span><span><b>02</b> Enter details</span><span><b>03</b> Review & approve</span><span><b>04</b> Copy or export</span></div>
@endsection
