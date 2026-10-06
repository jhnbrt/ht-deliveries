@extends('layouts.app')

@section('title', 'Upload DR')
@section('heading', 'Upload DR photos')

@section('content')
    <div class="grid gap-5 xl:grid-cols-3">
        <form action="{{ route('deliveries.store') }}" method="POST" enctype="multipart/form-data" class="card xl:col-span-2">
            @csrf
            <div data-dropzone class="rounded-3xl border-2 border-dashed border-line p-6 transition">
                <label class="flex cursor-pointer flex-col items-center gap-3 py-8 text-center">
                    <span class="grid size-16 place-items-center rounded-2xl bg-soft text-brand"><x-icon name="upload" class="size-8" /></span>
                    <span class="text-lg font-bold">Drop DR photos here or click to browse</span>
                    <span class="text-sm text-muted">JPG, PNG or WEBP. Up to 30 photos at a time, one DR per photo.</span>
                    <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="sr-only">
                </label>
                <p data-count class="text-center text-sm font-semibold text-brand"></p>
                <div data-previews class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"></div>
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" data-submit disabled class="btn btn-primary"><x-icon name="upload" class="size-5" /> Upload and read DRs</button>
            </div>
        </form>

        <section class="card">
            <h2 class="text-xl font-bold">How it works</h2>
            <ol class="mt-5 space-y-5">
                @foreach ([
                    ['Upload', 'Add photos of your delivery receipts.'],
                    ['Read', 'Each DR is read in the background: reference ID, branch, then every SKU, item and quantity.'],
                    ['Review', 'Check the photo next to what was read, fix anything, then mark it reviewed.'],
                    ['Export', 'Send the reviewed rows to Google Sheets, or copy and paste them.'],
                ] as $i => [$title, $text])
                    <li class="flex gap-4">
                        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-brand text-sm font-bold text-white">{{ $i + 1 }}</span>
                        <div><p class="font-semibold">{{ $title }}</p><p class="text-sm text-muted">{{ $text }}</p></div>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>
@endsection
