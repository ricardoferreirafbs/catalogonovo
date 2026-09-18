<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Nova senha · Catálogo SaaS</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-body">
    <main class="login-shell">
        <section class="login-panel">
            <a href="/" class="admin-brand"><span class="brand-mark">C</span><span>Catálogo<span class="brand-accent">.</span></span></a>
            <div class="login-copy"><p class="eyebrow">Proteção da conta</p><h1>Crie uma senha<br>mais forte.</h1><p>Use uma senha exclusiva, longa e difícil de adivinhar.</p></div>
            <div class="login-pattern" aria-hidden="true"></div>
        </section>
        <section class="login-form-wrap">
            <form action="{{ route('password.update') }}" method="post" class="auth-card">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div><p class="eyebrow">Novo acesso</p><h2>Redefina sua senha</h2><p>Mínimo de 12 caracteres, com maiúscula, minúscula, número e símbolo.</p></div>
                @if($errors->any())<div class="error-message" role="alert">{{ $errors->first() }}</div>@endif
                <label>E-mail<input type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required></label>
                <label>Nova senha<input type="password" name="password" minlength="12" autocomplete="new-password" required></label>
                <label>Confirmar nova senha<input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></label>
                <button type="submit" class="primary-button full-button">Salvar nova senha</button>
                <a class="auth-link" href="{{ route('login') }}">Voltar para o login</a>
            </form>
        </section>
    </main>
</body>
</html>
