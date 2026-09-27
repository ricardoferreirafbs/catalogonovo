@extends('layouts.platform', ['title' => 'Ocorrência '.$occurrence->protocol, 'eyebrow' => 'Análise do protocolo'])

@section('content')
    <a class="back-link" href="{{ route('platform.occurrences.index') }}">← Voltar às ocorrências</a>

    <div class="occurrence-detail-grid">
        <section class="panel-card occurrence-detail">
            <div class="occurrence-detail-title">
                <div><p class="eyebrow">{{ $occurrence->error_code }}</p><h2>{{ $occurrence->protocol }}</h2></div>
                <span class="occurrence-status {{ $occurrence->status }}">{{ $occurrence->statusLabel() }}</span>
            </div>

            @if($occurrence->isPlatformFailure())
                <div class="occurrence-alert"><strong>Investigação automática</strong><span>Esta ocorrência foi aberta automaticamente porque uma página apresentou falha HTTP {{ $occurrence->http_status }}.</span></div>
            @endif

            <dl class="occurrence-data">
                <div><dt>Data e hora</dt><dd>{{ \App\Support\LocalDateTime::format($occurrence->created_at, 'd/m/Y H:i:s') }}</dd></div>
                <div><dt>Empresa</dt><dd>{{ $occurrence->tenant?->name ?? 'Plataforma ou acesso público' }}</dd></div>
                <div><dt>Usuário</dt><dd>{{ $occurrence->actor?->name ?? 'Não autenticado' }}<small>{{ $occurrence->actor?->email }}</small></dd></div>
                <div><dt>Resposta</dt><dd>HTTP {{ $occurrence->http_status }}</dd></div>
                <div><dt>Método e rota</dt><dd>{{ $occurrence->method }} {{ $occurrence->path }}<small>{{ $occurrence->route ?: 'Rota não identificada' }}</small></dd></div>
                <div><dt>Classe técnica</dt><dd><code>{{ $occurrence->exception_class ?: 'Não identificada' }}</code></dd></div>
                <div><dt>Correlação de origem</dt><dd><code>{{ $occurrence->ip_hash ? substr($occurrence->ip_hash, 0, 16).'…' : 'Não disponível' }}</code><small>Hash irreversível; o IP não é armazenado.</small></dd></div>
                <div><dt>Retenção até</dt><dd>{{ \App\Support\LocalDateTime::format($occurrence->retention_until, 'd/m/Y H:i') }}<small>{{ $occurrence->security_related ? 'Investigação de segurança · '.config('security.security_error_retention_days', 180).' dias' : 'Ocorrência operacional · '.config('security.error_retention_days', 90).' dias' }}</small></dd></div>
            </dl>
        </section>

        <aside class="panel-card occurrence-review">
            <p class="eyebrow">Tratativa interna</p>
            <h2>Atualizar investigação</h2>
            <form method="post" action="{{ route('platform.occurrences.update', $occurrence) }}" class="stack-form">
                @csrf
                @method('patch')
                <label>Situação
                    <select name="status" required>
                        @foreach(\App\Models\ErrorOccurrence::STATUS_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $occurrence->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="check-label occurrence-security-check">
                    <input type="checkbox" name="security_related" value="1" @checked(old('security_related', $occurrence->security_related))>
                    Classificar como investigação de segurança
                </label>
                <label>Resumo interno
                    <textarea name="internal_notes" rows="8" maxlength="4000" placeholder="Registre evidências, impacto e providências sem incluir senhas, tokens ou códigos MFA.">{{ old('internal_notes', $occurrence->internal_notes) }}</textarea>
                </label>
                <p class="occurrence-retention-note">Ocorrências comuns permanecem por {{ config('security.error_retention_days', 90) }} dias. Ao classificar como segurança, a retenção passa a {{ config('security.security_error_retention_days', 180) }} dias a partir desta análise.</p>
                <button class="primary-button" type="submit">Salvar tratativa</button>
            </form>

            @if($occurrence->reviewer)
                <p class="occurrence-reviewer">Última análise por <strong>{{ $occurrence->reviewer->name }}</strong>{{ $occurrence->resolved_at ? ' · resolvida em '.\App\Support\LocalDateTime::format($occurrence->resolved_at) : '' }}.</p>
            @endif
        </aside>
    </div>
@endsection
