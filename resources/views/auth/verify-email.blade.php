<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirme seu e-mail · Catálogo SaaS</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-body">
    <main class="login-shell">
        <section class="login-panel">
            <a href="/" class="admin-brand"><span class="brand-mark">C</span><span>Catálogo<span class="brand-accent">.</span></span></a>
            <div class="login-copy"><p class="eyebrow">Confirmação de identidade</p><h1>Verifique seu<br>e-mail.</h1><p>Antes de configurar o MFA e acessar o painel, precisamos confirmar que o endereço pertence ao Proprietário.</p></div>
            <div class="login-pattern" aria-hidden="true"></div>
        </section>
        <section class="login-form-wrap">
            <div class="auth-card">
                <div><p class="eyebrow">Acesso protegido</p><h2>Confira sua caixa de entrada</h2><p>Enviamos um link temporário para <strong>{{ $user->email }}</strong>.</p></div>
                @if(session('success'))<div class="flash-message" role="status">✓ {{ session('success') }}</div>@endif
                @if($errors->any())<div class="error-message" role="alert">{{ $errors->first() }}</div>@endif
                <p class="security-warning">O link confirma apenas o e-mail. Sua senha e o MFA continuarão obrigatórios.</p>
                <form action="{{ route('verification.send') }}" method="post" class="stack-form">
                    @csrf
                    <button type="submit" class="primary-button full-button">Reenviar link de confirmação</button>
                </form>
                <form action="{{ route('logout') }}" method="post" class="stack-form">
                    @csrf
                    <button type="submit" class="secondary-button full-button">Sair da conta</button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
