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
                <div><dt>Retenção até</dt><dd>{{ \App\Support\LocalDateTime::format($occurrence->retention_until, 'd/m/Y H:i') }}<small>{{ $occurrence->personal_data_incident ? 'Incidente confirmado · mínimo de '.config('security.personal_data_incident_retention_years', 5).' anos' : ($occurrence->security_related ? 'Investigação de segurança · '.config('security.security_error_retention_days', 180).' dias' : 'Ocorrência operacional · '.config('security.error_retention_days', 90).' dias') }}</small></dd></div>
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
                <label class="check-label occurrence-incident-check">
                    <input type="checkbox" name="personal_data_incident" value="1" @checked(old('personal_data_incident', $occurrence->personal_data_incident)) @disabled($occurrence->personal_data_incident)>
                    Confirmar incidente envolvendo dados pessoais
                </label>
                @if($occurrence->personal_data_incident)<input type="hidden" name="personal_data_incident" value="1">@endif

                <div class="occurrence-incident-fields">
                    <label>Avaliação de risco
                        <select name="risk_assessment">
                            <option value="">Selecione</option>
                            <option value="pending" @selected(old('risk_assessment', $occurrence->risk_assessment) === 'pending')>Avaliação pendente</option>
                            <option value="no_relevant_risk" @selected(old('risk_assessment', $occurrence->risk_assessment) === 'no_relevant_risk')>Sem risco ou dano relevante</option>
                            <option value="relevant_risk" @selected(old('risk_assessment', $occurrence->risk_assessment) === 'relevant_risk')>Pode ocasionar risco ou dano relevante</option>
                        </select>
                    </label>
                    <label>Estimativa de titulares afetados
                        <input type="number" name="affected_subjects_estimate" min="0" value="{{ old('affected_subjects_estimate', $occurrence->affected_subjects_estimate) }}">
                    </label>
                    <label>Categorias de dados afetadas
                        <textarea name="affected_data_categories" rows="4" maxlength="4000" placeholder="Ex.: nome, e-mail, dados de autenticação.">{{ old('affected_data_categories', $occurrence->affected_data_categories) }}</textarea>
                    </label>
                    <label>Medidas de contenção
                        <textarea name="containment_measures" rows="4" maxlength="4000" placeholder="Registre bloqueios, correções e preservação de evidências.">{{ old('containment_measures', $occurrence->containment_measures) }}</textarea>
                    </label>
                    <div class="form-grid two-cols">
                        <label>Comunicação à ANPD
                            <input type="datetime-local" name="anpd_notified_at" value="{{ old('anpd_notified_at', $occurrence->anpd_notified_at?->format('Y-m-d\\TH:i')) }}">
                        </label>
                        <label>Comunicação aos titulares
                            <input type="datetime-local" name="data_subjects_notified_at" value="{{ old('data_subjects_notified_at', $occurrence->data_subjects_notified_at?->format('Y-m-d\\TH:i')) }}">
                        </label>
                    </div>
                    <p class="occurrence-legal-note">Confirme esta opção somente após análise. Incidentes com dados pessoais serão preservados por pelo menos {{ config('security.personal_data_incident_retention_years', 5) }} anos e não poderão ser rebaixados pelo painel.</p>
                </div>
                <label>Resumo interno
                    <textarea name="internal_notes" rows="8" maxlength="4000" placeholder="Registre evidências, impacto e providências sem incluir senhas, tokens ou códigos MFA.">{{ old('internal_notes', $occurrence->internal_notes) }}</textarea>
                </label>
                <p class="occurrence-retention-note">Ocorrências comuns permanecem por {{ config('security.error_retention_days', 90) }} dias; triagens de segurança, {{ config('security.security_error_retention_days', 180) }} dias; incidentes confirmados com dados pessoais, no mínimo {{ config('security.personal_data_incident_retention_years', 5) }} anos.</p>
                <button class="primary-button" type="submit">Salvar tratativa</button>
            </form>

            @if($occurrence->reviewer)
                <p class="occurrence-reviewer">Última análise por <strong>{{ $occurrence->reviewer->name }}</strong>{{ $occurrence->resolved_at ? ' · resolvida em '.\App\Support\LocalDateTime::format($occurrence->resolved_at) : '' }}.</p>
            @endif
        </aside>
    </div>
@endsection
