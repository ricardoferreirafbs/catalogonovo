<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Informações de privacidade e canal para exercício de direitos sobre dados pessoais na plataforma Catalog.">
    <title>Privacidade e dados pessoais · Catálogo</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="privacy-body">
    <header class="privacy-header">
        <div class="privacy-container privacy-nav">
            <a href="{{ url('/') }}" class="admin-brand privacy-brand"><span class="brand-mark">C</span><span>Catálogo<span class="brand-accent">.</span></span></a>
            <nav><a href="{{ route('help.errors') }}">Central de ajuda</a><a href="{{ route('login') }}">Entrar</a></nav>
        </div>
    </header>

    <main>
        <section class="privacy-hero">
            <div class="privacy-container">
                <p class="eyebrow">Privacidade e transparência</p>
                <h1>Seus dados, seus direitos.</h1>
                <p>Conheça como a plataforma trata dados pessoais e utilize o canal seguro para exercer seus direitos.</p>
            </div>
        </section>

        <div class="privacy-container privacy-grid">
            <section class="privacy-content">
                @if(session('success'))<div class="flash-message" role="status">✓ {{ session('success') }}</div>@endif
                <article><h2>Quem responde por este canal</h2><p><strong>{{ config('privacy.controller_name') }}</strong>@if(config('privacy.controller_document')) · {{ config('privacy.controller_document') }}@endif. Contato: <a href="mailto:{{ config('privacy.contact_email') }}">{{ config('privacy.contact_email') }}</a>@if(config('privacy.officer_name')). Encarregado: {{ config('privacy.officer_name') }}@endif.</p></article>
                <article><h2>Dados tratados pela plataforma</h2><p>Podemos tratar dados cadastrais de usuários, informações de empresas clientes, registros de autenticação e segurança, solicitações de suporte, auditoria e dados técnicos necessários para prestar e proteger o serviço. Catálogos podem conter dados definidos e publicados por cada empresa cliente.</p></article>
                <article><h2>Finalidades</h2><p>Os dados são utilizados para autenticação, administração de contas e catálogos, segurança, prevenção a fraudes, suporte, cumprimento de obrigações e exercício regular de direitos. A finalidade e a responsabilidade podem variar quando a Catalog atua em nome de uma empresa cliente.</p></article>
                <article><h2>Compartilhamento e armazenamento</h2><p>O tratamento pode envolver provedores de hospedagem, e-mail, backup e infraestrutura contratados para operar a plataforma. O acesso é limitado às pessoas e fornecedores necessários, conforme suas funções e obrigações contratuais.</p></article>
                <article><h2>Retenção e segurança</h2><p>Os dados são mantidos somente pelo período necessário à finalidade, às obrigações legais, contratuais e à defesa de direitos. Aplicamos controle de acesso, MFA, auditoria, criptografia de informações protegidas, registros de ocorrências e rotinas de exclusão.</p></article>
                <article><h2>Seus direitos</h2><p>Você pode solicitar confirmação e acesso, correção, informações sobre compartilhamento, exclusão ou anonimização quando aplicável, revogação do consentimento, oposição e outras providências previstas na legislação.</p></article>
            </section>

            <aside class="panel-card privacy-request-card" id="solicitacao">
                <p class="eyebrow">Canal do titular</p>
                <h2>Enviar solicitação</h2>
                <p>Após o envio, confirmaremos o endereço de e-mail antes de iniciar a análise. Mudanças relevantes serão comunicadas com um novo link temporário de acompanhamento.</p>
                @if($errors->any())<div class="error-message" role="alert">{{ $errors->first() }}</div>@endif
                <form method="post" action="{{ route('privacy.requests.store') }}" class="stack-form">
                    @csrf
                    <label>Nome completo<input name="name" value="{{ old('name') }}" maxlength="160" required autocomplete="name"></label>
                    <label>E-mail<input type="email" name="email" value="{{ old('email') }}" maxlength="255" required autocomplete="email"></label>
                    <label>Tipo de solicitação<select name="request_type" required><option value="">Selecione</option>@foreach(\App\Models\PrivacyRequest::TYPE_LABELS as $value => $label)<option value="{{ $value }}" @selected(old('request_type') === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label>Relacionada a<select name="scope" required><option value="platform" @selected(old('scope') === 'platform')>Minha conta ou a plataforma Catalog</option><option value="tenant" @selected(old('scope') === 'tenant')>Uma empresa ou catálogo cliente</option></select></label>
                    <label>Empresa ou domínio relacionado, se houver<input name="company_reference" value="{{ old('company_reference') }}" maxlength="255" placeholder="Ex.: empresa.com.br"></label>
                    <label>Detalhes necessários para localizar o tratamento<textarea name="details" rows="5" maxlength="4000" placeholder="Não envie senha, código MFA, documentos ou dados bancários.">{{ old('details') }}</textarea></label>
                    <label class="check-label privacy-ack"><input type="checkbox" name="privacy_acknowledgement" value="1" required> Autorizo o uso destes dados exclusivamente para validar e atender esta solicitação.</label>
                    <button class="primary-button" type="submit">Gerar protocolo</button>
                </form>
                <p class="privacy-safe-note">Nunca solicitaremos sua senha, segredo do QR Code ou código MFA por este canal.</p>
            </aside>
        </div>
    </main>

    <footer class="privacy-footer"><div class="privacy-container"><strong>Catálogo.</strong><span>Privacidade, segurança e transparência</span></div></footer>
</body>
</html>
