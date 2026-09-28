@extends('layouts.admin', ['title' => 'Comunicações', 'eyebrow' => 'Mensagens da plataforma'])

@section('content')
    <section class="panel-card push-manager" data-push-manager data-public-key="{{ config('webpush.public_key') }}" data-store-url="{{ route('admin.push.store') }}" data-destroy-url="{{ route('admin.push.destroy') }}">
        <div><p class="eyebrow">Avisos no dispositivo</p><h2>Notificações Web Push</h2><p data-push-status aria-live="polite">{{ config('webpush.public_key') ? 'Ative para receber avisos genéricos mesmo com o painel fechado.' : 'O Web Push ainda não foi configurado pela plataforma.' }}</p></div>
        <button class="secondary-button" type="button" data-push-toggle disabled>Verificando navegador…</button>
    </section>
    <section class="panel-card occurrence-panel">
        <div class="panel-heading"><div><p class="eyebrow">Caixa de entrada segura</p><h2>Comunicados e interações</h2><p>O conteúdo permanece criptografado e é excluído após o prazo informado.</p></div></div>
        <div class="communication-list">
            @forelse($recipients as $recipient)
                @php($communication = $recipient->communication)
                <a class="communication-row tenant" href="{{ route('admin.communications.show', $communication) }}">
                    <span class="communication-priority {{ $communication->priority }}">{{ $recipient->read_at ? '✓' : '•' }}</span>
                    <span><strong>{{ $communication->subject }}</strong><small>{{ $communication->protocol }} · {{ $communication->categoryLabel() }}</small></span>
                    <span><strong>{{ $communication->messages_count }} mensagem(ns)</strong><small>{{ $recipient->acknowledged_at ? 'Ciência confirmada' : ($communication->requires_acknowledgement ? 'Requer sua ciência' : 'Acompanhamento disponível') }}</small></span>
                    <span><span class="occurrence-status {{ $communication->status }}">{{ $communication->statusLabel() }}</span><small>{{ \App\Support\LocalDateTime::format($communication->published_at) }}</small></span>
                </a>
            @empty
                <div class="empty-state compact"><h3>Nenhuma comunicação</h3><p>Os avisos destinados ao seu perfil aparecerão aqui.</p></div>
            @endforelse
        </div>
    </section>
    <div class="pagination">{{ $recipients->links() }}</div>
@endsection
