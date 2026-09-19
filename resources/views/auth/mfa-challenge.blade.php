<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Segundo fator · Catálogo SaaS</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-body">
    <main class="login-shell">
        <section class="login-panel">
            <span class="admin-brand"><span class="brand-mark">C</span><span>Catálogo<span class="brand-accent">.</span></span></span>
            <div class="login-copy"><p class="eyebrow">Verificação adicional</p><h1>Proteção em<br>duas etapas.</h1><p>Confirme o código do aplicativo autenticador para acessar seu painel com segurança.</p></div>
            <div class="login-pattern" aria-hidden="true"></div>
        </section>
        <section class="login-form-wrap">
            <form action="{{ route('mfa.verify') }}" method="post" class="auth-card">
                @csrf
                <div><p class="eyebrow">Segundo fator</p><h2>Confirme sua identidade</h2><p>Digite o código de seis dígitos ou um código de recuperação.</p></div>
                @if($errors->any())<div class="error-message" role="alert">{{ $errors->first() }}</div>@endif
                <label>Código<input class="mfa-code-input" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="32" required autofocus></label>
                <button type="submit" class="primary-button full-button">Verificar e continuar</button>
            </form>
        </section>
    </main>
</body>
</html>
