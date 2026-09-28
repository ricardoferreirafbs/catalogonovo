@extends('layouts.platform', ['title' => 'Backup e restauração', 'eyebrow' => 'Continuidade da plataforma'])

@section('content')
    <div class="backup-grid">
        <section class="panel-card backup-card">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Cópia geral criptografada</p>
                    <h2>Proteção integral da plataforma</h2>
                    <p>Inclui todas as empresas, usuários, catálogos, registros operacionais e arquivos enviados. O conteúdo usa AES-256-GCM e é autenticado contra alteração.</p>
                </div>
                <form method="post" action="{{ route('platform.backups.store') }}" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').textContent='Gerando…'">
                    @csrf
                    <button class="primary-button" type="submit">Gerar backup geral</button>
                </form>
            </div>

            <div class="backup-table" role="table" aria-label="Backups gerais">
                <div class="backup-row backup-head" role="row"><span>Arquivo</span><span>Criado em</span><span>Tamanho</span><span>Ações</span></div>
                @forelse($backups as $backup)
                    <div class="backup-row" role="row">
                        <span><strong>{{ $backup['filename'] }}</strong><small>Escopo: plataforma completa</small></span>
                        <span>{{ \App\Support\LocalDateTime::format(\Illuminate\Support\Carbon::createFromTimestampUTC($backup['modified_at'])) }}</span>
                        <span>{{ number_format($backup['size'] / 1048576, 2, ',', '.') }} MB</span>
                        <span class="backup-actions">
                            <form method="post" action="{{ route('platform.backups.verify', $backup['filename']) }}">@csrf<button class="secondary-button" type="submit">Verificar</button></form>
                            <form method="post" action="{{ route('platform.backups.download', $backup['filename']) }}">@csrf<button class="secondary-button" type="submit">Baixar</button></form>
                        </span>
                    </div>
                @empty
                    <div class="empty-inline">Nenhum backup geral foi encontrado.</div>
                @endforelse
            </div>
        </section>

        <aside class="panel-card backup-guidance">
            <p class="eyebrow">Restauração protegida</p>
            <h2>Procedimento de recuperação</h2>
            <p>A restauração geral não é executada pelo navegador. Ela exige ambiente vazio, modo de manutenção, a mesma <code>APP_KEY</code> e a chave exclusiva de backup.</p>
            <ol>
                <li>Baixe e preserve o arquivo em local seguro.</li>
                <li>Prepare uma instalação vazia e execute as migrations.</li>
                <li>Envie o arquivo para o disco de backup do servidor.</li>
                <li>Verifique e restaure pela linha de comando.</li>
            </ol>
            @if($backups !== [])
                <code class="backup-command">php artisan backup:verify "{{ $backups[0]['path'] }}"<br>php artisan backup:restore "{{ $backups[0]['path'] }}" --confirm=RESTORE-INTO-EMPTY-DATABASE --force</code>
            @endif
            <p class="backup-warning"><strong>Atenção:</strong> nunca teste a restauração sobre o banco de produção. Use primeiro um ambiente de homologação isolado.</p>
        </aside>
    </div>
@endsection
