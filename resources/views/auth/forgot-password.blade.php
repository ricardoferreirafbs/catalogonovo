<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Recuperar senha · Catálogo SaaS</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-body">
    <main class="login-shell">
        <section class="login-panel">
            <a href="/" class="admin-brand"><span class="brand-mark">C</span><span>Catálogo<span class="brand-accent">.</span></span></a>
            <div class="login-copy"><p class="eyebrow">Recuperação segura</p><h1>Volte ao seu<br>catálogo.</h1><p>Enviaremos um link temporário para o e-mail cadastrado.</p></div>
            <div class="login-pattern" aria-hidden="true"></div>
        </section>
        <section class="login-form-wrap">
            <form action="{{ route('password.email') }}" method="post" class="auth-card">
                @csrf
                <div><p class="eyebrow">Redefinir acesso</p><h2>Esqueceu a senha?</h2><p>Informe o e-mail da sua conta.</p></div>
                @if(session('success'))<div class="flash-message" role="status">✓ {{ session('success') }}</div>@endif
                @if($errors->any())<div class="error-message" role="alert">{{ $errors->first() }}</div>@endif
                <label>E-mail<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus></label>
                <button type="submit" class="primary-button full-button">Enviar instruções</button>
                <a class="auth-link" href="{{ route('login') }}">Voltar para o login</a>
            </form>
        </section>
    </main>
</body>
</html>
