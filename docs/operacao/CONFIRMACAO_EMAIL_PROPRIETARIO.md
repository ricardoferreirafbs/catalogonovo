# Confirmação do e-mail do Proprietário

## Objetivo

Confirmar que o endereço cadastrado para o Proprietário está sob controle da pessoa responsável antes de liberar a configuração do MFA e o painel do cliente.

## Fluxo de segurança

1. O Superadmin ou o comando `tenant:create` cria a empresa e o Proprietário com `email_verified_at` vazio.
2. A plataforma envia ao Proprietário um link HTTPS temporário, assinado e vinculado ao identificador e ao hash do e-mail.
3. Login e senha válidos criam a sessão, mas o middleware `email.verified` bloqueia MFA e painel enquanto o endereço não estiver confirmado.
4. O link válido registra a confirmação e o evento `security.owner_email_verified` na auditoria.
5. O link não autentica o visitante. Depois da confirmação, o Proprietário ainda precisa entrar com a senha e cadastrar ou validar o MFA.

O prazo é definido por `OWNER_EMAIL_VERIFICATION_MINUTES`, com mínimo técnico de cinco minutos e padrão recomendado de 60 minutos. O reenvio aceita até três solicitações por hora por usuário e dez por hora por endereço IP.

## Alteração do endereço

Quando o Superadmin altera o e-mail do Proprietário, a plataforma:

- remove a confirmação anterior;
- encerra todas as sessões do Proprietário;
- invalida tokens de recuperação de senha;
- remove assinaturas Web Push;
- preserva o MFA cadastrado;
- envia um link para o novo endereço.

Preservar o MFA impede que uma simples alteração cadastral reduza a segurança da conta. Se o Proprietário também perdeu o autenticador, execute separadamente o procedimento de recuperação administrativa de MFA.

## Falha no envio

A empresa ou a alteração de e-mail permanece salva, mas o painel continua bloqueado. A aplicação apresenta um aviso sem expor credenciais, registra a exceção no log interno e permite novo envio na tela de confirmação. Corrija o SMTP antes de tentar novamente; não preencha manualmente `email_verified_at` como atalho operacional.

## Implantação

Execute em modo de manutenção:

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan up
```

A migration de compatibilidade marca como confirmados somente os Proprietários legados existentes no momento da implantação. Isso evita bloqueio inesperado das contas atuais. Novos Proprietários ficam pendentes de confirmação.

## Checklist de homologação

- [ ] Um Proprietário existente continua acessando normalmente depois da migration.
- [ ] Uma empresa nova cria o Proprietário com e-mail pendente.
- [ ] O e-mail de confirmação chega ao endereço cadastrado.
- [ ] Login válido redireciona para `/confirmar-email` antes do MFA.
- [ ] MFA e painel permanecem bloqueados antes da confirmação.
- [ ] Um link adulterado ou expirado retorna acesso negado.
- [ ] Um link válido confirma o endereço sem autenticar o visitante.
- [ ] O login seguinte exige configuração ou desafio MFA.
- [ ] O reenvio funciona e passa a ser limitado depois de três solicitações por hora.
- [ ] Alterar o e-mail encerra sessões e remove Web Push do Proprietário.
- [ ] A auditoria registra `security.owner_email_verified` sem senha, link, hash ou código MFA.
- [ ] Administrador, Editor e Visualizador mantêm o comportamento de acesso previsto.
