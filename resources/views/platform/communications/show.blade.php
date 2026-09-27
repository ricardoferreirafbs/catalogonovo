@extends('layouts.platform', ['title' => $communication->subject, 'eyebrow' => $communication->protocol])

@section('content')
    <a class="back-link" href="{{ route('platform.communications.index') }}">← Voltar às comunicações</a>
    <div class="communication-detail-grid">
        <section class="panel-card communication-thread">
            <div class="communication-thread-head"><div><span class="occurrence-status {{ $communication->status }}">{{ $communication->statusLabel() }}</span><h2>{{ $communication->subject }}</h2><p>{{ $communication->tenant->name }} · {{ $communication->categoryLabel() }} · prioridade {{ mb_strtolower(\App\Models\Communication::PRIORITY_LABELS[$communication->priority]) }}</p></div>@if($communication->status === 'published')<form method="post" action="{{ route('platform.communications.close', $communication) }}">@csrf @method('PATCH')<button class="secondary-button" type="submit">Encerrar</button></form>@endif</div>
            <div class="secure-thread">@foreach($communication->messages as $message)<article class="secure-message {{ $message->sender_side }}"><header><strong>{{ $message->sender_side === 'platform' ? 'Plataforma Catalog' : ($message->sender?->name ?? 'Usuário removido') }}</strong><time>{{ \App\Support\LocalDateTime::format($message->created_at) }}</time></header><p>{{ $message->body }}</p></article>@endforeach</div>
            @if($communication->status === 'published')<form method="post" action="{{ route('platform.communications.reply', $communication) }}" class="secure-reply">@csrf<label>Responder com segurança<textarea name="body" rows="5" maxlength="8000" required></textarea></label><button class="primary-button" type="submit">Enviar resposta</button></form>@endif
        </section>
        <aside class="panel-card communication-recipients"><p class="eyebrow">Entrega e ciência</p><h2>Destinatários</h2><p>Expira em {{ $communication->expires_at ? \App\Support\LocalDateTime::format($communication->expires_at) : 'após a publicação' }}.</p>@foreach($communication->recipients as $recipient)<div><strong>{{ $recipient->user->name }}</strong><small>{{ $recipient->user->roleLabel() }} · {{ $recipient->read_at ? 'Lida em '.\App\Support\LocalDateTime::format($recipient->read_at) : 'Não lida' }}@if($communication->requires_acknowledgement) · {{ $recipient->acknowledged_at ? 'Ciência em '.\App\Support\LocalDateTime::format($recipient->acknowledged_at) : 'Ciência pendente' }}@endif</small></div>@endforeach</aside>
    </div>
@endsection
