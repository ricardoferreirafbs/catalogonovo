<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Acompanhar {{ $privacyRequest->protocol }} · Catálogo</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="privacy-body">
    <header class="privacy-header"><div class="privacy-container privacy-nav"><a href="{{ route('privacy.index') }}" class="admin-brand privacy-brand"><span class="brand-mark">C</span><span>Catálogo<span class="brand-accent">.</span></span></a><nav><a href="{{ route('privacy.index') }}">Privacidade</a><a href="{{ route('help.errors') }}">Central de ajuda</a></nav></div></header>
    <main class="privacy-track-main">
        <section class="panel-card privacy-track-card">
            @if(session('success'))<div class="flash-message" role="status">✓ {{ session('success') }}</div>@endif
            <p class="eyebrow">Acompanhamento seguro</p>
            <h1>{{ $privacyRequest->protocol }}</h1>
            <span class="occurrence-status {{ $privacyRequest->status }}">{{ $privacyRequest->statusLabel() }}</span>
            <dl class="occurrence-data privacy-track-data">
                <div><dt>Tipo</dt><dd>{{ $privacyRequest->typeLabel() }}</dd></div>
                <div><dt>Recebida em</dt><dd>{{ \App\Support\LocalDateTime::format($privacyRequest->created_at) }}</dd></div>
                <div><dt>Última atualização</dt><dd>{{ \App\Support\LocalDateTime::format($privacyRequest->updated_at) }}</dd></div>
                @if($privacyRequest->requester_message)<div class="privacy-detail-wide"><dt>{{ $privacyRequest->status === 'completed' ? 'Resposta final' : ($privacyRequest->status === 'rejected' ? 'Decisão e orientação' : 'Mensagem da equipe') }}</dt><dd>{{ $privacyRequest->requester_message }}</dd></div>@endif
            </dl>
            <div class="privacy-sensitive-alert"><strong>Link pessoal</strong><span>Não compartilhe este endereço. Ele permite consultar o andamento até expirar, sem revelar notas internas ou informações de outros usuários.</span></div>
        </section>
    </main>
</body>
</html>
