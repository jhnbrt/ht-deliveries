@extends('layouts.app')
@section('title', 'Upload Deliveries')
@section('content')
<p class="intro">Take a clear photo of the whole receipt, then upload it. Each photo becomes a separate record and is read when its review screen opens.</p>
<section class="panel upload-panel"><div class="panel-heading"><h2>Delivery receipt photos</h2><span class="pill">JPG · PNG · WEBP</span></div>
<form method="POST" action="{{ route('deliveries.store') }}" enctype="multipart/form-data" id="upload-form">@csrf
<label class="drop-zone" id="drop-zone"><span class="upload-icon">↑</span><strong>Drop your receipt photos here</strong><span>or click to browse files</span><small>Up to 10 photos · maximum 10 MB each</small><input id="photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required></label>
<div id="photo-previews" class="photo-previews" aria-live="polite"></div><p id="upload-feedback" class="subtle" aria-live="polite"></p>
<div class="form-actions"><span class="subtle">Your original photos stay attached to the records.</span><button class="button" type="submit">Upload & Review →</button></div></form></section>
<div class="notice">After upload, the review screen reads your photo into text and item rows. Configure the photo reader in Help & Setup first.</div>
@endsection
