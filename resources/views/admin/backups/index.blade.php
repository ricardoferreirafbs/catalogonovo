@extends('layouts.admin', ['title' => 'Backup da empresa', 'eyebrow' => 'Proteção e portabilidade'])

@section('content')
    <div class="backup-grid tenant-backup-grid">
        <section class="panel-card backup-card">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">{{ $tenant->name }}</p>
                    <h2>Cópia isolada do seu catálogo</h2>
                    <p>O arquivo contém somente os dados, usuários, produtos, comunicações e imagens desta empresa. Nenhuma informação de outro cliente é incluída.</p>
                </div>
                <form method="post" action="{{ route('admin.backups.store') }}" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').textContent='Gerando…'">
                    @csrf
                    <button class="primary-button" type="submit">Gerar backup da empresa</button>
                </form>
            </div>

            <div class="backup-table" role="table" aria-label="Backups da empresa">
                <div class="backup-row backup-head" role="row"><span>Arquivo</span><span>Criado em</span><span>Tamanho</span><span>Ações</span></div>
                @forelse($backups as $backup)
                    <div class="backup-row" role="row">
                        <span><strong>{{ $backup['filename'] }}</strong><small>Escopo: somente {{ $tenant->name }}</small></span>
                        <span>{{ \App\Support\LocalDateTime::format(\Illuminate\Support\Carbon::createFromTimestampUTC($backup['modified_at'])) }}</span>
                        <span>{{ number_format($backup['size'] / 1048576, 2, ',', '.') }} MB</span>
                        <span class="backup-actions">
                            <form method="post" action="{{ route('admin.backups.verify', $backup['filename']) }}">@csrf<button class="secondary-button" type="submit">Verificar</button></form>
                            <form method="post" action="{{ route('admin.backups.download', $backup['filename']) }}">@csrf<button class="secondary-button" type="submit">Baixar</button></form>
                        </span>
                    </div>
                @empty
                    <div class="empty-inline">Você ainda não gerou um backup desta empresa.</div>
                @endforelse
            </div>
        </section>

        <aside class="panel-card backup-guidance">
            <p class="eyebrow">Segurança do arquivo</p>
            <h2>Como funciona</h2>
            <ul>
                <li>Disponível apenas para o Proprietário da empresa.</li>
                <li>Criptografado e autenticado antes de ficar disponível.</li>
                <li>A separação por empresa é aplicada no servidor, não pelo navegador.</li>
                <li>A restauração é feita pela administração da plataforma após validação técnica.</li>
            </ul>
            <p class="backup-warning">Guarde o arquivo baixado em um local protegido. Mesmo criptografado, ele deve ser tratado como dado confidencial.</p>
        </aside>
    </div>
@endsection
