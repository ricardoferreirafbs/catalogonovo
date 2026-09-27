@extends('layouts.platform', ['title' => 'Nova comunicação', 'eyebrow' => 'Canal seguro'])

@section('content')
    <a class="back-link" href="{{ route('platform.communications.index') }}">← Voltar às comunicações</a>
    <section class="panel-card communication-form-card">
        <div class="panel-heading"><div><p class="eyebrow">Conteúdo criptografado</p><h2>Preparar comunicado</h2><p>O e-mail leva apenas um aviso genérico. O conteúdo completo exige autenticação e MFA na plataforma.</p></div></div>
        <form method="post" action="{{ route('platform.communications.store') }}" class="form-grid communication-form">
            @csrf
            <label class="check-label communication-all"><input type="checkbox" name="send_to_all" value="1" @checked(old('send_to_all'))> Enviar para todas as empresas ativas</label>
            <label>Empresa<select name="tenant_id"><option value="">Selecione</option>@foreach($tenants as $tenant)<option value="{{ $tenant->id }}" @selected((string) old('tenant_id') === (string) $tenant->id)>{{ $tenant->name }}</option>@endforeach</select></label>
            <label>Categoria<select name="category" required>@foreach(\App\Models\Communication::CATEGORY_LABELS as $value => $label)<option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label>Prioridade<select name="priority" required>@foreach(\App\Models\Communication::PRIORITY_LABELS as $value => $label)<option value="{{ $value }}" @selected(old('priority', 'normal') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="form-span-2">Assunto<input name="subject" value="{{ old('subject') }}" maxlength="180" required></label>
            <label class="form-span-2">Mensagem<textarea name="body" rows="9" maxlength="8000" required placeholder="Não inclua senhas, códigos MFA ou segredos de autenticação.">{{ old('body') }}</textarea></label>
            <fieldset class="communication-roles form-span-2"><legend>Destinatários por papel</legend>@foreach(\App\Models\User::TENANT_ROLE_LABELS as $role => $label)<label class="check-label"><input type="checkbox" name="recipient_roles[]" value="{{ $role }}" @checked(in_array($role, old('recipient_roles', ['owner', 'admin']), true))> {{ $label }}</label>@endforeach</fieldset>
            <label>Agendar para<input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}"><small>Deixe vazio para publicar agora. Horário: {{ config('app.display_timezone') }}.</small></label>
            <label class="check-label"><input type="checkbox" name="requires_acknowledgement" value="1" @checked(old('requires_acknowledgement'))> Exigir confirmação de ciência</label>
            <div class="form-actions form-span-2"><button class="primary-button" type="submit">Publicar ou agendar</button></div>
        </form>
    </section>
@endsection
