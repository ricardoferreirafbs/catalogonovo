<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $errorCode }} · Catálogo</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="catalog-error-body">
    <main class="catalog-error-shell">
        <section class="catalog-error-card" aria-labelledby="error-title">
            <a href="{{ url('/') }}" class="admin-brand catalog-error-brand">
                <span class="brand-mark">C</span><span>Catálogo<span class="brand-accent">.</span></span>
            </a>

            <div class="catalog-error-status" aria-hidden="true">{{ $status }}</div>
            <p class="eyebrow">Não foi possível concluir a solicitação</p>
            <h1 id="error-title">{{ $errorTitle }}</h1>
            <p class="catalog-error-message">{{ $errorMessage }}</p>

            <dl class="catalog-error-references">
                <div>
                    <dt>Código para consultar na FAQ</dt>
                    <dd>{{ $errorCode }}</dd>
                </div>
                <div>
                    <dt>Protocolo desta ocorrência</dt>
                    <dd>{{ $protocol }}</dd>
                </div>
            </dl>

            <p class="catalog-error-help">Se precisar de atendimento, informe o código e o protocolo acima. Não envie sua senha nem o código do autenticador.</p>

            <div class="catalog-error-actions">
                <a class="primary-button" href="{{ route('help.errors', ['codigo' => $errorCode]) }}">Consultar este erro na FAQ</a>
                @auth
                    <a class="secondary-button" href="{{ auth()->user()->isSuperAdmin() ? route('platform.dashboard') : route('admin.dashboard') }}">Voltar ao painel</a>
                @else
                    <a class="secondary-button" href="{{ route('login') }}">Entrar novamente</a>
                @endauth
            </div>
        </section>
    </main>
</body>
</html>
