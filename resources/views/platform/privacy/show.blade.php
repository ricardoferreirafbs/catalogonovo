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
                <label>Mensagem ao solicitante<textarea name="requester_message" rows="5" maxlength="3000" placeholder="Somente informações apropriadas para o titular. Obrigatória ao pedir informações ou não atender.">{{ old('requester_message', $privacyRequest->requester_message) }}</textarea></label>
                <label>Decisão, providências e fundamento<textarea name="internal_notes" rows="10" maxlength="6000" placeholder="Registre validação de identidade, dados localizados, decisão e forma segura de resposta.">{{ old('internal_notes', $privacyRequest->internal_notes) }}</textarea></label>
                <p class="occurrence-retention-note">A mensagem ao solicitante será enviada quando a situação mudar. As notas internas nunca são exibidas no acompanhamento. Não entregue dados apenas com base no e-mail: valide a identidade e o papel da Catalog.</p>
                <button class="primary-button" type="submit">Salvar tratativa</button>
            </form>
            @if($privacyRequest->email_verified_at)
                <form method="post" action="{{ route('platform.privacy.notify', $privacyRequest) }}" class="privacy-notify-form">
                    @csrf
                    <button class="secondary-button" type="submit">Reenviar acompanhamento</button>
                    <small>Gera um novo link assinado, com prazo de expiração, sem expor as notas internas.</small>
                </form>
            @endif
        </aside>
    </div>
@endsection
