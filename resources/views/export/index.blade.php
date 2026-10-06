@extends('layouts.app')

@section('title', 'Export')
@section('heading', 'Export to Google Sheets')

@section('content')
    <div class="flex flex-wrap items-center gap-3">
        <div class="pill">
            <span class="font-extrabold text-brand">{{ $rows->count() }}</span> rows from {{ $deliveries->count() }} {{ Str::plural('delivery', $deliveries->count()) }}
        </div>
        <a href="{{ route('export.index', $includeExported ? [] : ['all' => 1]) }}" class="text-sm font-semibold text-muted hover:text-brand">
            {{ $includeExported ? 'Show only new (not yet exported)' : 'Include already exported' }}
        </a>

        <label class="ml-auto flex cursor-pointer items-center gap-2 text-sm font-medium">
            <input type="checkbox" data-header-toggle class="size-4 accent-[var(--color-brand)]"> Include header row when copying
        </label>
    </div>

    <div class="mt-4 flex flex-wrap gap-3">
        <button type="button" data-copy data-tsv="{{ $tsv }}" data-tsv-header="{{ $tsvWithHeader }}" @disabled($rows->isEmpty()) class="btn btn-primary">
            <x-icon name="copy" class="size-5" /> <span data-label>Copy for Google Sheets</span>
        </button>

        <a href="{{ route('export.csv', $includeExported ? ['all' => 1] : []) }}" class="btn btn-ghost border border-line"><x-icon name="download" class="size-5" /> Download CSV</a>

        @if ($sheetsConfigured)
            <form method="POST" action="{{ route('export.sheet') }}">@csrf
                <button @disabled($rows->isEmpty() || $includeExported) class="btn btn-ghost border border-line"><x-icon name="sheet" class="size-5" /> Send to Google Sheet</button>
            </form>
        @endif

        @if ($deliveries->isNotEmpty() && ! $includeExported)
            <form method="POST" action="{{ route('export.mark') }}">@csrf
                @foreach ($deliveries as $d)<input type="hidden" name="ids[]" value="{{ $d->id }}">@endforeach
                <button class="btn btn-ghost border border-line"><x-icon name="check" class="size-5" /> Mark as exported</button>
            </form>
        @endif
    </div>

    @unless ($sheetsConfigured)
        <p class="mt-3 text-sm text-muted">Want one-click sending? Set up the Google Sheet link in <code>docs/google-sheets-setup.md</code>. Otherwise copy, paste into your sheet, then press "Mark as exported".</p>
    @endunless

    <section class="card mt-6">
        <div class="-mx-2 overflow-x-auto">
            <table class="w-full min-w-[640px]">
                <thead><tr>@foreach ($headers as $h)<th class="th">{{ $h }}</th>@endforeach</tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="even:bg-stripe">
                            @foreach ($row as $cell)<td class="td">{{ $cell }}</td>@endforeach
                        </tr>
                    @empty
                        <tr><td colspan="5" class="td py-12 text-center text-muted">
                            Nothing to export yet. Reviewed deliveries show up here. <a href="{{ route('deliveries.index', ['status' => 'pending_review']) }}" class="font-semibold text-brand">Go to the review queue</a>.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
