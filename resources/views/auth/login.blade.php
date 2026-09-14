<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar · Catálogo SaaS</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-body">
    <main class="login-shell">
        <section class="login-panel">
            <a href="/" class="admin-brand"><span class="brand-mark">C</span><span>Catálogo<span class="brand-accent">.</span></span></a>
            <div class="login-copy"><p class="eyebrow">Área segura</p><h1>Seu catálogo,<br>sempre em movimento.</h1><p>Atualize produtos, valores e a identidade da sua marca em um só lugar.</p></div>
            <div class="login-pattern" aria-hidden="true"></div>
        </section>
        <section class="login-form-wrap">
            <form action="{{ route('login.store') }}" method="post" class="auth-card">
                @csrf
                <div><p class="eyebrow">Bem-vindo</p><h2>Acesse seu painel</h2><p>Use as credenciais da sua empresa.</p></div>
                @if($errors->any())<div class="error-message" role="alert">{{ $errors->first() }}</div>@endif
                <label>E-mail<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus placeholder="voce@empresa.com"></label>
                <label>Senha<input type="password" name="password" autocomplete="current-password" required placeholder="••••••••"></label>
                <label class="check-label"><input type="checkbox" name="remember" value="1"> Manter conectado</label>
                <button type="submit" class="primary-button full-button">Entrar no painel</button>
                <small class="demo-hint">Demonstração: admin@catalogo.test · catalogo123</small>
            </form>
        </section>
    </main>
</body>
</html>
