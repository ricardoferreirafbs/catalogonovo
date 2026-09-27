@extends('layouts.admin', ['title' => $communication->subject, 'eyebrow' => $communication->protocol])

@section('content')
    <a class="back-link" href="{{ route('admin.communications.index') }}">← Voltar às comunicações</a>
    <section class="panel-card communication-thread tenant-thread">
        <div class="communication-thread-head"><div><span class="occurrence-status {{ $communication->status }}">{{ $communication->statusLabel() }}</span><h2>{{ $communication->subject }}</h2><p>{{ $communication->categoryLabel() }} · prioridade {{ mb_strtolower(\App\Models\Communication::PRIORITY_LABELS[$communication->priority]) }} · disponível até {{ \App\Support\LocalDateTime::format($communication->expires_at) }}</p></div>@if($communication->requires_acknowledgement && ! $recipient->acknowledged_at)<form method="post" action="{{ route('admin.communications.acknowledge', $communication) }}">@csrf<button class="primary-button" type="submit">Li e estou ciente</button></form>@endif</div>
        <div class="privacy-sensitive-alert"><strong>Canal protegido</strong><span>Não compartilhe senhas, códigos MFA, segredos de autenticação ou dados de terceiros nesta conversa.</span></div>
        <div class="secure-thread">@foreach($communication->messages as $message)<article class="secure-message {{ $message->sender_side }}"><header><strong>{{ $message->sender_side === 'platform' ? 'Plataforma Catalog' : ($message->sender?->name ?? 'Usuário removido') }}</strong><time>{{ \App\Support\LocalDateTime::format($message->created_at) }}</time></header><p>{{ $message->body }}</p></article>@endforeach</div>
        @if($communication->status === 'published')<form method="post" action="{{ route('admin.communications.reply', $communication) }}" class="secure-reply">@csrf<label>Responder à plataforma<textarea name="body" rows="5" maxlength="8000" required></textarea></label><button class="primary-button" type="submit">Enviar resposta segura</button></form>@else<div class="occurrence-retention-note">Esta comunicação foi encerrada e está disponível somente para consulta até a data de expiração.</div>@endif
    </section>
@endsection
