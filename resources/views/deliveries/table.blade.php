<div class="table-wrap"><table><thead><tr><th>Reference ID</th><th>Branch</th><th>Items</th><th>Uploaded</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($deliveries as $delivery)
<tr><td><span class="reference">{{ $delivery->reference_id ?: 'DR #'.$delivery->id }}</span></td><td>{{ $delivery->branch ?: 'To be reviewed' }}</td><td>{{ count(json_decode($delivery->items ?? '[]', true)) }}</td><td>{{ \Illuminate\Support\Carbon::parse($delivery->created_at)->format('m/d/Y') }}</td><td><span class="badge {{ $delivery->status }}">{{ $delivery->status === 'completed' ? '✓ Completed' : '◷ For review' }}</span></td><td><a class="text-link" href="{{ route('deliveries.edit', $delivery->id) }}">Open →</a></td></tr>
@empty
<tr><td colspan="6"><div class="empty-state"><span>▤</span><h3>No deliveries here yet</h3><p>Upload your first receipt photo to get started.</p><a class="button" href="{{ route('deliveries.create') }}">Upload Deliveries</a></div></td></tr>
@endforelse
</tbody></table></div>
