@extends('layouts.admin', ['title' => 'Usuários e permissões', 'eyebrow' => 'Equipe da empresa'])

@section('content')
    <section class="role-summary-grid" aria-label="Papéis disponíveis">
        <article><strong>Proprietário</strong><small>Controle total, usuários e ações críticas.</small></article>
        <article><strong>Administrador</strong><small>Opera o catálogo e gerencia editores e visualizadores.</small></article>
        <article><strong>Editor</strong><small>Produtos, categorias, menus e conteúdo.</small></article>
        <article><strong>Visualizador</strong><small>Consulta o painel e os produtos sem alterar dados.</small></article>
    </section>

    <div class="users-admin-grid">
        @if(auth()->user()->hasPermission('users.invite'))
            <section class="panel-card form-panel">
                <div class="panel-heading"><div><p class="eyebrow">Novo acesso</p><h2>Convidar usuário</h2><p>O usuário receberá um link temporário para criar a própria senha.</p></div></div>
                <form action="{{ route('admin.users.store') }}" method="post" class="form-grid">
                    @csrf
                    <label>Nome<input name="name" value="{{ old('name') }}" required maxlength="160" autocomplete="name"></label>
                    <label>E-mail<input type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email"></label>
                    <label>Papel
                        <select name="role" required>
                            @foreach($assignableRoles as $role => $label)<option value="{{ $role }}" @selected(old('role') === $role)>{{ $label }}</option>@endforeach
                        </select>
                    </label>
                    <button class="primary-button" type="submit">Enviar convite</button>
                </form>
            </section>
        @endif

        <section class="panel-card users-panel">
            <div class="panel-heading"><div><p class="eyebrow">Acessos ativos e pendentes</p><h2>{{ $users->count() }} {{ $users->count() === 1 ? 'usuário' : 'usuários' }}</h2><p>Alterações de papel encerram as sessões anteriores do usuário.</p></div></div>
            <div class="tenant-user-list">
                @foreach($users as $user)
                    <article class="tenant-user-row">
                        <div class="user-avatar" aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</div>
                        <div class="tenant-user-identity">
                            <strong>{{ $user->name }} @if($user->is(auth()->user()))<small>Você</small>@endif</strong>
                            <span>{{ $user->email }}</span>
                            <div class="user-security-state">
                                <span class="status {{ $user->invitation_accepted_at ? 'active' : 'draft' }}">{{ $user->invitation_accepted_at ? 'Cadastro concluído' : 'Convite pendente' }}</span>
                                <span class="status {{ $user->two_factor_confirmed_at ? 'active' : 'draft' }}">MFA {{ $user->two_factor_confirmed_at ? 'ativo' : 'inativo' }}</span>
                            </div>
                        </div>

                        <div class="tenant-user-role">
                            @if(auth()->user()->canManageTenantUser($user))
                                <form action="{{ route('admin.users.update', $user) }}" method="post" class="inline-role-form">
                                    @csrf @method('PATCH')
                                    <label><span>Papel</span><select name="role">
                                        @foreach($assignableRoles as $role => $label)<option value="{{ $role }}" @selected($user->role === $role)>{{ $label }}</option>@endforeach
                                    </select></label>
                                    <button class="secondary-button" type="submit">Atualizar</button>
                                </form>
                            @else
                                <span class="role-badge">{{ $roleLabels[$user->role] ?? ucfirst($user->role) }}</span>
                            @endif
                        </div>

                        @if(auth()->user()->canManageTenantUser($user))
                            <div class="tenant-user-actions">
                                @if(! $user->invitation_accepted_at)
                                    <form action="{{ route('admin.users.resend', $user) }}" method="post">@csrf<button type="submit">Reenviar convite</button></form>
                                @endif
                                <form action="{{ route('admin.users.destroy', $user) }}" method="post" onsubmit="return confirm('Remover este usuário e encerrar todas as sessões?')">@csrf @method('DELETE')<button class="danger-link" type="submit">Remover</button></form>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    </div>
@endsection
