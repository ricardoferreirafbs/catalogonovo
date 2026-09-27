@extends('layouts.platform', ['title' => 'Solicitação '.$privacyRequest->protocol, 'eyebrow' => 'Tratativa de privacidade'])

@section('content')
    <a class="back-link" href="{{ route('platform.privacy.index') }}">← Voltar às solicitações</a>
    <div class="occurrence-detail-grid">
        <section class="panel-card occurrence-detail">
            <div class="occurrence-detail-title"><div><p class="eyebrow">{{ $privacyRequest->typeLabel() }}</p><h2>{{ $privacyRequest->protocol }}</h2></div><span class="occurrence-status {{ $privacyRequest->status }}">{{ $privacyRequest->statusLabel() }}</span></div>
            <div class="privacy-sensitive-alert"><strong>Conteúdo protegido</strong><span>Use estes dados somente para atender o titular. Não copie informações para canais não autorizados.</span></div>
            <dl class="occurrence-data">
                <div><dt>Recebida em</dt><dd>{{ \App\Support\LocalDateTime::format($privacyRequest->created_at) }}</dd></div>
                <div><dt>E-mail confirmado</dt><dd>{{ $privacyRequest->email_verified_at ? \App\Support\LocalDateTime::format($privacyRequest->email_verified_at) : 'Ainda não confirmado' }}</dd></div>
                <div><dt>Solicitante</dt><dd>{{ $privacyRequest->requester_name }}<small>{{ $privacyRequest->requester_email }}</small></dd></div>
                <div><dt>Escopo</dt><dd>{{ $privacyRequest->scope === 'tenant' ? 'Empresa ou catálogo cliente' : 'Conta ou plataforma Catalog' }}<small>{{ $privacyRequest->company_reference }}</small></dd></div>
                <div class="privacy-detail-wide"><dt>Detalhes informados</dt><dd>{{ $privacyRequest->details ?: 'Nenhum detalhe adicional.' }}</dd></div>
                <div><dt>Retenção atual</dt><dd>{{ \App\Support\LocalDateTime::format($privacyRequest->retention_until) }}</dd></div>
                <div><dt>Responsável interno</dt><dd>{{ $privacyRequest->reviewer?->name ?? 'Ainda não atribuído' }}</dd></div>
            </dl>
        </section>
        <aside class="panel-card occurrence-review">
            <p class="eyebrow">Decisão documentada</p><h2>Atualizar solicitação</h2>
            <form method="post" action="{{ route('platform.privacy.update', $privacyRequest) }}" class="stack-form">
                @csrf @method('patch')
                <label>Situação<select name="status" required>@foreach(\App\Models\PrivacyRequest::STATUS_LABELS as $value => $label)<option value="{{ $value }}" @selected(old('status', $privacyRequest->status) === $value)>{{ $label }}</option>@endforeach</select></label>
                <label>Decisão, providências e fundamento<textarea name="internal_notes" rows="10" maxlength="6000" placeholder="Registre validação de identidade, dados localizados, decisão e forma segura de resposta.">{{ old('internal_notes', $privacyRequest->internal_notes) }}</textarea></label>
                <p class="occurrence-retention-note">Não entregue dados apenas com base no e-mail. Valide a identidade e confirme se a Catalog atua como controladora ou deve encaminhar o pedido à empresa cliente.</p>
                <button class="primary-button" type="submit">Salvar tratativa</button>
            </form>
        </aside>
    </div>
@endsection
