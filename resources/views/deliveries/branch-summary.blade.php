@php($branchTotal = $branches->sum('total'))
<section class="panel branch-summary">
    <div class="panel-heading"><div><h2>Deliveries by branch</h2><p class="subtle">All uploaded receipts · Click a branch to view its deliveries</p></div><a class="text-link" href="{{ route('deliveries.index', ['status' => 'all']) }}">View all →</a></div>
    <div class="branch-cards">
    @forelse($branches as $summary)
        <a class="branch-card" href="{{ route('deliveries.index', ['status' => 'all'] + ($summary->branch === '' ? ['unassigned' => 1] : ['branch' => $summary->branch])) }}">
            <div class="branch-card-top"><span class="branch-symbol">▤</span><strong>{{ number_format($summary->total) }}</strong></div>
            <p class="branch-share">{{ number_format($branchTotal ? $summary->total / $branchTotal * 100 : 0, 1) }}% of all deliveries</p>
            <h3>{{ $summary->branch ?: 'Branch not assigned' }}</h3>
            <div class="branch-counts"><span>{{ $summary->review }} for review</span><span>{{ $summary->completed }} completed</span></div>
            <div class="branch-progress" aria-label="{{ $summary->completed }} of {{ $summary->total }} completed" title="Completion: {{ number_format($summary->total ? $summary->completed / $summary->total * 100 : 0, 1) }}%"><span style="width: {{ $summary->total ? $summary->completed / $summary->total * 100 : 0 }}%"></span></div>
        </a>
    @empty
        <p class="subtle">Branch totals will appear after you upload receipts and save their branch details.</p>
    @endforelse
    </div>
</section>
