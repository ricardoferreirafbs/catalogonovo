@extends('layouts.platform', ['title' => 'Segurança da conta', 'eyebrow' => 'Autenticação em dois fatores'])

@section('content')
    <div class="security-grid">
        <section class="panel-card security-card">
            @if($user->two_factor_confirmed_at)
                <p class="eyebrow">Proteção ativa</p>
                <h2>Segundo fator configurado</h2>
                <p class="security-lead">A conta exige um código temporário além da senha. Essa proteção é obrigatória para superadministradores.</p>
                <div class="security-status"><span>✓</span><div><strong>Autenticador confirmado</strong><small>Ativado em {{ $user->two_factor_confirmed_at->format('d/m/Y H:i') }}</small></div></div>

                <div class="security-divider"></div>
                <h3>Gerar novos códigos de recuperação</h3>
                <p>Confirme a senha e o código atual. Os códigos anteriores serão invalidados imediatamente.</p>
                <form action="{{ route('platform.mfa.recovery') }}" method="post" class="stack-form">
                    @csrf
                    <label>Senha atual<input type="password" name="password" autocomplete="current-password" required></label>
                    <label>Código do autenticador<input name="code" inputmode="numeric" autocomplete="one-time-code" minlength="6" maxlength="6" required></label>
                    <button class="secondary-button" type="submit">Gerar novos códigos</button>
                </form>
            @else
                <p class="eyebrow">Configuração obrigatória</p>
                <h2>Proteja a administração SaaS</h2>
                <p class="security-lead">No Google Authenticator, Microsoft Authenticator ou aplicativo compatível, escolha adicionar uma chave de configuração.</p>

                <div class="mfa-qr-panel">
                    <img src="{{ $qrCodeDataUri }}" alt="QR Code para configurar o aplicativo autenticador" width="280" height="280">
                    <div><strong>Escaneie o QR Code</strong><small>No aplicativo autenticador, toque em adicionar conta e escolha a opção de escanear QR Code.</small></div>
                </div>

                <ol class="security-steps">
                    <li><span>1</span><div><strong>Prefere configurar manualmente?</strong><small>Escolha uma chave baseada em tempo (TOTP).</small></div></li>
                    <li><span>2</span><div><strong>Digite esta chave</strong><code>{{ $user->two_factor_secret }}</code></div></li>
                    <li><span>3</span><div><strong>Confirme o código gerado</strong><small>Os códigos mudam a cada 30 segundos.</small></div></li>
                </ol>

                <a class="secondary-button" href="{{ $provisioningUri }}">Abrir no autenticador</a>

                <form action="{{ route('platform.mfa.confirm') }}" method="post" class="stack-form security-confirm-form">
                    @csrf
                    <label>Código de seis dígitos<input name="code" inputmode="numeric" autocomplete="one-time-code" minlength="6" maxlength="6" required autofocus></label>
                    <button class="primary-button" type="submit">Ativar segundo fator</button>
                </form>
            @endif
        </section>

        <aside class="panel-card recovery-card">
            <p class="eyebrow">Códigos de emergência</p>
            @if(session('recovery_codes'))
                <h2>Salve estes códigos agora</h2>
                <p>Cada código funciona uma única vez. Eles não serão exibidos novamente.</p>
                <div class="recovery-codes">
                    @foreach(session('recovery_codes') as $code)<code>{{ $code }}</code>@endforeach
                </div>
                <p class="security-warning">Guarde-os em um gerenciador de senhas ou local protegido, separado do celular.</p>
            @else
                <h2>Acesso de contingência</h2>
                <p>Após ativar o segundo fator, você receberá códigos para situações em que perder o acesso ao aplicativo autenticador.</p>
                <p class="security-warning">A plataforma armazena somente versões irreversíveis desses códigos.</p>
            @endif
        </aside>
    </div>
@endsection
