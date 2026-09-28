@extends('layouts.platform', ['title' => 'Comunicações', 'eyebrow' => 'Relacionamento com clientes'])

@section('content')
    <section class="panel-card push-manager" data-push-manager data-public-key="{{ config('webpush.public_key') }}" data-store-url="{{ route('platform.push.store') }}" data-destroy-url="{{ route('platform.push.destroy') }}">
        <div><p class="eyebrow">Avisos no dispositivo</p><h2>Notificações Web Push</h2><p data-push-status aria-live="polite">{{ config('webpush.public_key') ? 'Ative para receber avisos genéricos quando um cliente responder.' : 'Configure as chaves VAPID para ativar este recurso.' }}</p></div>
        <button class="secondary-button" type="button" data-push-toggle disabled>Verificando navegador…</button>
    </section>
    <div class="communication-actions"><p>Mensagens protegidas, avisos programados e confirmações de leitura. O conteúdo expira após {{ config('communication.retention_days', 60) }} dias.</p><a class="primary-button" href="{{ route('platform.communications.create') }}">Nova comunicação</a></div>
    <section class="panel-card occurrence-panel">
        <div class="panel-heading occurrence-heading">
            <div><p class="eyebrow">Central segura</p><h2>Mensagens da plataforma</h2></div>
            <form method="get" class="occurrence-filters">
                <select name="status"><option value="">Todas as situações</option>@foreach(\App\Models\Communication::STATUS_LABELS as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select>
                <select name="category"><option value="">Todas as categorias</option>@foreach(\App\Models\Communication::CATEGORY_LABELS as $value => $label)<option value="{{ $value }}" @selected($category === $value)>{{ $label }}</option>@endforeach</select>
                <select name="tenant_id"><option value="">Todas as empresas</option>@foreach($tenants as $tenant)<option value="{{ $tenant->id }}" @selected($tenantId === $tenant->id)>{{ $tenant->name }}</option>@endforeach</select>
                <button class="secondary-button" type="submit">Filtrar</button>
            </form>
        </div>
        <div class="communication-list">
            @forelse($communications as $communication)
                <a class="communication-row" href="{{ route('platform.communications.show', $communication) }}">
                    <span class="communication-priority {{ $communication->priority }}">{{ $communication->priority === 'critical' ? '!' : '•' }}</span>
                    <span><strong>{{ $communication->subject }}</strong><small>{{ $communication->protocol }} · {{ $communication->categoryLabel() }}</small></span>
                    <span><strong>{{ $communication->tenant->name }}</strong><small>{{ $communication->recipients_count }} destinatário(s)</small></span>
                    <span><span class="occurrence-status {{ $communication->status }}">{{ $communication->statusLabel() }}</span><small>{{ \App\Support\LocalDateTime::format($communication->scheduled_at ?? $communication->published_at ?? $communication->created_at) }}</small></span>
                </a>
            @empty
                <div class="empty-state compact"><h3>Nenhuma comunicação encontrada</h3><p>Crie um aviso ou altere os filtros.</p></div>
            @endforelse
        </div>
    </section>
    <div class="pagination">{{ $communications->links() }}</div>
@endsection
