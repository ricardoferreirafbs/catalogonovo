<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title ?? 'Painel' }} · Catálogo SaaS</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-body">
    <div class="admin-shell">
        <aside class="sidebar">
            <a href="{{ route('admin.dashboard') }}" class="admin-brand"><span class="brand-mark">C</span><span>Catálogo<span class="brand-accent">.</span></span></a>
            <div class="tenant-chip">
                <span>{{ mb_substr(auth()->user()->tenant->name, 0, 1) }}</span>
                <div><strong>{{ auth()->user()->tenant->name }}</strong><small>{{ auth()->user()->roleLabel() }} · Plano {{ ucfirst(auth()->user()->tenant->plan) }}</small></div>
            </div>
            <nav class="sidebar-nav" aria-label="Menu do painel">
                <a class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><span>⌂</span> Visão geral</a>
                @if(auth()->user()->hasPermission('products.view'))<a class="{{ request()->routeIs('admin.products.*') ? 'active' : '' }}" href="{{ route('admin.products.index') }}"><span>□</span> Produtos</a>@endif
                @if(auth()->user()->hasPermission('structure.manage'))<a class="{{ request()->routeIs('admin.categories.*') || request()->routeIs('admin.menus.*') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}"><span>☷</span> Estrutura</a>@endif
                @if(auth()->user()->hasPermission('content.manage'))<a class="{{ request()->routeIs('admin.content.*') ? 'active' : '' }}" href="{{ route('admin.content.edit') }}"><span>✎</span> Conteúdo</a>@endif
                @if(auth()->user()->hasPermission('appearance.manage'))<a class="{{ request()->routeIs('admin.theme.*') ? 'active' : '' }}" href="{{ route('admin.theme.edit') }}"><span>◐</span> Aparência</a>@endif
                @if(auth()->user()->hasPermission('users.view'))<a class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><span>♙</span> Usuários</a>@endif
                <a class="{{ request()->routeIs('admin.mfa.*') ? 'active' : '' }}" href="{{ route('admin.mfa.setup') }}"><span>◇</span> Segurança</a>
                <a href="{{ auth()->user()->tenant->catalogUrl() }}" target="_blank" rel="noopener"><span>↗</span> Abrir catálogo</a>
            </nav>
            <form action="{{ route('logout') }}" method="post" class="sidebar-logout">
                @csrf
                <button type="submit">Sair da conta</button>
            </form>
        </aside>
        <main class="admin-main">
            <header class="admin-topbar">
                <div><p class="eyebrow">{{ $eyebrow ?? 'Painel administrativo' }}</p><h1>{{ $title ?? 'Visão geral' }}</h1></div>
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
