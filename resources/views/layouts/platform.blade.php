<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title ?? 'Plataforma' }} · Catálogo SaaS</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-body platform-body">
    <div class="admin-shell">
        <aside class="sidebar platform-sidebar">
            <a href="{{ route('platform.dashboard') }}" class="admin-brand"><span class="brand-mark">C</span><span>Catálogo<span class="brand-accent">.</span></span></a>
            <div class="tenant-chip platform-chip">
                <span>S</span>
                <div><strong>Administração SaaS</strong><small>Controle da plataforma</small></div>
            </div>
            <nav class="sidebar-nav" aria-label="Menu da plataforma">
                <a class="{{ request()->routeIs('platform.dashboard') ? 'active' : '' }}" href="{{ route('platform.dashboard') }}"><span>⌂</span> Visão geral</a>
                <a class="{{ request()->routeIs('platform.tenants.*') ? 'active' : '' }}" href="{{ route('platform.tenants.index') }}"><span>▦</span> Empresas</a>
                <a href="{{ route('platform.tenants.create') }}"><span>＋</span> Nova empresa</a>
            </nav>
            <form action="{{ route('logout') }}" method="post" class="sidebar-logout">
                @csrf
                <button type="submit">Sair da conta</button>
            </form>
        </aside>
        <main class="admin-main">
            <header class="admin-topbar">
                <div><p class="eyebrow">{{ $eyebrow ?? 'Gestão da plataforma' }}</p><h1>{{ $title ?? 'Visão geral' }}</h1></div>
                <div class="user-avatar" title="{{ auth()->user()->name }}">{{ mb_substr(auth()->user()->name, 0, 1) }}</div>
            </header>

            @if(session('success'))<div class="flash-message" role="status">✓ {{ session('success') }}</div>@endif
            @if($errors->any())
                <div class="error-message" role="alert"><strong>Confira os campos:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>
