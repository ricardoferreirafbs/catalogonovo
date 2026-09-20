<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Consulte os códigos de erro da plataforma Catálogo e saiba como resolver cada situação com segurança.">
    <title>Ajuda com erros · Catálogo</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="error-help-body">
    <header class="error-help-header">
        <div class="error-help-container error-help-nav">
            <a href="{{ url('/') }}" class="admin-brand error-help-brand">
                <span class="brand-mark">C</span><span>Catálogo<span class="brand-accent">.</span></span>
            </a>
            <nav aria-label="Navegação da central de ajuda">
                @auth
                    <a href="{{ auth()->user()->isSuperAdmin() ? route('platform.dashboard') : route('admin.dashboard') }}">Voltar ao painel</a>
                @else
                    <a href="{{ route('login') }}">Entrar no painel</a>
                @endauth
            </nav>
        </div>
    </header>

    <main>
        <section class="error-help-hero">
            <div class="error-help-container">
                <p class="eyebrow">Central de ajuda</p>
                <h1>Encontre uma solução pelo código do erro.</h1>
                <p>Digite o código exibido na tela, como <strong>CAT-403-ACESSO</strong>, ou pesquise por um assunto.</p>

                <label class="error-help-search" for="error-help-search">
                    <span aria-hidden="true">⌕</span>
                    <span class="sr-only">Pesquisar código ou assunto</span>
                    <input id="error-help-search" type="search" placeholder="Código, título ou palavra-chave" autocomplete="off" data-error-help-search>
                </label>
                <p class="error-help-result-count" data-error-help-count aria-live="polite">{{ count($references) }} orientações disponíveis</p>
            </div>
        </section>

        <section class="error-help-container error-help-content" aria-label="Códigos de erro">
            <div class="error-help-grid" data-error-help-list>
                @foreach($references as $reference)
                    <article class="error-help-card" id="{{ $reference['code'] }}" data-error-help-item data-search="{{ Str::lower($reference['code'].' '.$reference['title'].' '.$reference['message'].' '.$reference['meaning'].' '.implode(' ', $reference['actions'])) }}">
                        <div class="error-help-card-heading">
                            <span class="error-help-http">HTTP {{ $reference['status'] }}</span>
                            <code>{{ $reference['code'] }}</code>
                        </div>
                        <h2>{{ $reference['title'] }}</h2>
                        <p>{{ $reference['meaning'] }}</p>
                        <h3>Como resolver</h3>
                        <ol>
                            @foreach($reference['actions'] as $action)
                                <li>{{ $action }}</li>
                            @endforeach
                        </ol>
                    </article>
                @endforeach
            </div>

            <div class="error-help-empty" data-error-help-empty hidden>
                <strong>Nenhuma orientação encontrada.</strong>
                <p>Confira o código digitado ou pesquise por uma palavra mais curta.</p>
            </div>

            <aside class="error-help-security">
                <span aria-hidden="true">✓</span>
                <div>
                    <h2>Atendimento seguro</h2>
                    <p>Ao pedir ajuda, informe somente o código do erro, o protocolo, a data, o horário e a ação que estava realizando. A equipe nunca deve pedir sua senha, código MFA, segredo do QR Code ou código de recuperação.</p>
                </div>
            </aside>
        </section>
    </main>

    <footer class="error-help-footer">
        <div class="error-help-container">
            <strong>Catálogo.</strong>
            <span>Central de ajuda e segurança</span>
        </div>
    </footer>
</body>
</html>
