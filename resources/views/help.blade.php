@extends('layouts.app')
@section('title', 'Help & Setup')
@section('content')
<section class="panel help-panel"><h2>Your delivery workflow</h2><ol><li><strong>Upload Deliveries:</strong> Select up to ten JPG, PNG or WEBP photos. Use one complete receipt per photo.</li><li><strong>Review Deliveries:</strong> Open a photo and enter its Reference ID, Branch, SKU, Item and Quantity. Add as many item rows as needed.</li><li><strong>Approve:</strong> Check the quantities against the photo, then approve the delivery.</li><li><strong>Export:</strong> Copy the table into Google Sheets or download a CSV. Only approved records appear.</li></ol><p>To correct a completed delivery, open it from Completed. Saving for review removes it from exports until approved again.</p><h2>Laravel Herd setup</h2><p>Place this project inside your Herd folder. From the project terminal, run:</p><pre>composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build</pre><p>The copy command above is for Windows Command Prompt. In PowerShell use <code>Copy-Item .env.example .env</code>; on macOS use <code>cp .env.example .env</code>. Keep your existing .env if you already configured the project.</p><p>Use PHP 8.4 or later in Herd. The default database is SQLite; create an empty <code>database/database.sqlite</code> if it does not exist. Then open <code>http://htdeliveries.test</code>.</p><h2>Pending integrations</h2><p>Automatic photo reading and direct Google Sheets syncing are not implemented in this starter. Photos and reviewed details are saved locally in your Laravel project.</p><p>This version is for your local Herd workspace and has no login. Add authentication before hosting it online.</p></section>
@endsection
