<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · HTDeliveries</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ request()->routeIs('deliveries.edit') ? 'receipt-workspace' : '' }}">
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}"><span class="brand-symbol">HT</span> DELIVERIES</a>
        <span class="nav-label">WORKSPACE</span>
        <nav aria-label="Main navigation">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><span>▦</span> Dashboard</a>
            <a class="nav-link {{ request()->routeIs('deliveries.create') ? 'active' : '' }}" href="{{ route('deliveries.create') }}"><span>↑</span> Upload Deliveries</a>
            <a class="nav-link {{ (request()->routeIs('deliveries.index') && request('status') !== 'completed') || request()->routeIs('deliveries.edit') ? 'active' : '' }}" href="{{ route('deliveries.index') }}"><span>▤</span> Review Deliveries</a>
            <a class="nav-link {{ request('status') === 'completed' ? 'active' : '' }}" href="{{ route('deliveries.index', ['status' => 'completed']) }}"><span>✓</span> Completed</a>
            <a class="nav-link {{ request()->routeIs('deliveries.export') ? 'active' : '' }}" href="{{ route('deliveries.export') }}"><span>↗</span> Export Data</a>
        </nav>
        <span class="nav-label support-label">SUPPORT</span>
        <a class="nav-link {{ request()->routeIs('help') ? 'active' : '' }}" href="{{ route('help') }}"><span>ⓘ</span> Help & Setup</a>
        <div class="sidebar-note"><span class="note-icon">↑</span><strong>From receipt<br>to spreadsheet.</strong><p>Keep every delivery photo and its reviewed details together.</p><a href="{{ route('deliveries.create') }}">Upload a photo →</a></div>
        <small class="sidebar-footer">HT Ventures Corporation</small>
    </aside>
    <main>
        <header class="topbar"><div><p class="eyebrow">HEAVENLY TAKOYAKI</p><h1>@yield('title', 'Dashboard')</h1></div><div class="topbar-right"><span class="today">{{ now()->format('M d, Y') }}</span><span class="avatar">HT</span></div></header>
        @if(session('success'))<div class="notice success" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="notice error" role="alert"><strong>Please check the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
        <footer class="page-footer">HTDeliveries · Delivery receipt workspace</footer>
    </main>
</div>
</body>
</html>
