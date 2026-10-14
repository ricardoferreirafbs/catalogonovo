@php($editing = $tenant->exists)
@extends('layouts.platform', ['title' => $editing ? 'Gerenciar empresa' : 'Nova empresa', 'eyebrow' => $editing ? $tenant->name : 'Cadastro de cliente'])

@section('content')
    <div class="platform-form-grid">
        <form action="{{ $editing ? route('platform.tenants.update', $tenant) : route('platform.tenants.store') }}" method="post" class="panel-card form-panel">
            @csrf
            @if($editing) @method('put') @endif

            <div class="form-section-heading"><span>01</span><div><h2>Dados da empresa</h2><p>Identificação, acesso público e plano comercial.</p></div></div>
            <div class="form-grid two-cols">
                <label>Nome da empresa<input name="name" required maxlength="160" value="{{ old('name', $tenant->name) }}"></label>
                <label>Identificador<input name="slug" required maxlength="120" value="{{ old('slug', $tenant->slug) }}" placeholder="minha-empresa"><small>Usado no subdomínio e nas confirmações.</small></label>
                <label class="span-2">Domínio personalizado<input name="custom_domain" maxlength="255" value="{{ old('custom_domain', $tenant->custom_domain) }}" placeholder="catalogo.empresa.com.br"><small>Informe sem https:// e sem barras.</small></label>
                <label>Plano<select name="plan" required><option value="starter" @selected(old('plan', $tenant->plan ?: 'starter') === 'starter')>Starter</option><option value="professional" @selected(old('plan', $tenant->plan) === 'professional')>Professional</option><option value="business" @selected(old('plan', $tenant->plan) === 'business')>Business</option></select></label>
                <label>Situação<select name="status" required><option value="active" @selected(old('status', $tenant->status ?: 'active') === 'active')>Ativa</option><option value="suspended" @selected(old('status', $tenant->status) === 'suspended')>Suspensa</option></select></label>
                <label class="span-2">WhatsApp<input name="contact_phone" maxlength="30" value="{{ old('contact_phone', $tenant->contact_phone) }}" placeholder="5511999999999"></label>
            </div>

            <div class="form-section-heading separated"><span>02</span><div><h2>Administrador do cliente</h2><p>Conta proprietária responsável pelo catálogo.</p></div></div>
            <div class="form-grid two-cols">
                <label>Nome<input name="admin_name" required maxlength="160" value="{{ old('admin_name', $owner->name ?: 'Administrador') }}"></label>
                <label>E-mail<input name="admin_email" type="email" required maxlength="255" value="{{ old('admin_email', $owner->email) }}"></label>
                <label>Senha {{ $editing && $owner->exists ? '(opcional)' : '' }}<input name="admin_password" type="password" minlength="12" @required(! $editing || ! $owner->exists) autocomplete="new-password"><small>{{ $editing && $owner->exists ? 'Deixe em branco para manter a senha atual. Ao trocar, use 12 caracteres com maiúscula, minúscula, número e símbolo.' : 'Use 12 caracteres com maiúscula, minúscula, número e símbolo.' }}</small></label>
                <label>Confirmar senha<input name="admin_password_confirmation" type="password" minlength="12" @required(! $editing || ! $owner->exists) autocomplete="new-password"></label>
            </div>

            <div class="form-actions platform-form-actions"><a href="{{ route('platform.tenants.index') }}">Cancelar</a><button class="primary-button" type="submit">{{ $editing ? 'Salvar alterações' : 'Criar empresa' }}</button></div>
        </form>

        @if($editing)
            <aside class="platform-side-stack">
                <section class="panel-card account-summary">
                    <p class="eyebrow">Resumo</p><h2>{{ $tenant->name }}</h2>
                    <dl><div><dt>Produtos</dt><dd>{{ $tenant->products_count }}</dd></div><div><dt>Usuários</dt><dd>{{ $tenant->users_count }}</dd></div><div><dt>Identificador</dt><dd>{{ $tenant->slug }}</dd></div></dl>
                    @if($tenant->status === 'active' && $tenant->custom_domain)<a class="secondary-button full-button" href="{{ $tenant->catalogUrl() }}" target="_blank" rel="noopener">Abrir catálogo ↗</a>@endif
                    <form action="{{ route('platform.tenants.status', $tenant) }}" method="post">@csrf @method('patch')<button class="secondary-button full-button" type="submit">{{ $tenant->status === 'active' ? 'Suspender empresa' : 'Reativar empresa' }}</button></form>
                </section>
                <section class="panel-card recovery-admin-card">
                    <p class="eyebrow">Recuperação de acesso</p>
                    <h2>Redefinir MFA do Proprietário</h2>
                    @if($owner->exists && $owner->two_factor_confirmed_at)
                        <p>Use somente após validar a identidade de <strong>{{ $owner->name }}</strong>. A senha será mantida, mas sessões, códigos MFA, recuperações de senha pendentes e assinaturas push serão revogados.</p>
                        <form action="{{ route('platform.tenants.owner-mfa.recover', [$tenant, $owner]) }}" method="post" class="stack-form">
                            @csrf
                            <label>Método de validação<select name="verification_method" required><option value="">Selecione</option>@foreach($identityMethods as $value => $label)<option value="{{ $value }}" @selected(old('verification_method') === $value)>{{ $label }}</option>@endforeach</select></label>
                            <label>Referência do atendimento<input name="support_reference" required minlength="6" maxlength="80" pattern="[A-Za-z0-9][A-Za-z0-9._-]{5,79}" value="{{ old('support_reference') }}" placeholder="SUP-20261013-001" autocomplete="off"></label>
                            <label>Confirme o e-mail <strong>{{ $owner->email }}</strong><input name="owner_email" type="email" required autocomplete="off" value="{{ old('owner_email') }}"></label>
                            <label>Sua senha atual<input name="password" type="password" required autocomplete="current-password"></label>
                            <label>Novo código do seu autenticador<input name="code" inputmode="numeric" autocomplete="one-time-code" minlength="6" maxlength="6" required></label>
                            <label class="check-label"><input type="checkbox" name="acknowledgement" value="1" required> Confirmo que validei a identidade do Proprietário e que ele será avisado da recuperação.</label>
                            <button type="submit" class="danger-button">Redefinir MFA e encerrar acessos</button>
                        </form>
                    @elseif($owner->exists)
                        <p>Este Proprietário ainda não possui MFA ativo. No próximo acesso, a configuração obrigatória será apresentada automaticamente.</p>
                    @else
                        <p>Não existe uma conta proprietária vinculada a esta empresa.</p>
                    @endif
                </section>
                <section class="panel-card danger-zone">
                    <p class="eyebrow">Zona de perigo</p><h2>Excluir empresa</h2><p>Remove usuários, catálogo, produtos e imagens permanentemente.</p>
                    <form action="{{ route('platform.tenants.destroy', $tenant) }}" method="post">@csrf @method('delete')<label>Digite <strong>{{ $tenant->slug }}</strong><input name="confirmation" required autocomplete="off"></label><button type="submit" class="danger-button">Excluir definitivamente</button></form>
                </section>
            </aside>
        @endif
    </div>
@endsection
