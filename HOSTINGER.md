# Implantação na Hostinger

> **Tipo de aplicação:** este repositório é uma aplicação PHP/Laravel. No hPanel, crie um site **Custom PHP/HTML** e conecte o GitHub em **Avançado → Git**. Não use a opção “Node.js Web App”: o Node/Vite serve apenas para compilar os arquivos visuais e não é o servidor da aplicação.

Os recursos de produção em `public/build` já estão versionados para que o deploy Web/Cloud não precise executar o Vite. O `.htaccess` da raiz encaminha as requisições para `public/`, conforme o modelo recomendado pela Hostinger para hospedagens cujo document root é fixo em `public_html`.

## Plano indicado

Para operar como SaaS, use uma **VPS Hostinger**. O catálogo pode rodar em hospedagem Web/Cloud, mas a VPS é a opção adequada para:

- subdomínio curinga para vários clientes;
- domínios personalizados e certificados SSL;
- workers de fila permanentes;
- Redis, automação de deploy e controle de recursos;
- crescimento sem transformar cada cliente em um site separado.

## Estrutura de produção

```text
DNS / HTTPS
    ↓
Nginx
    ↓
Laravel + PHP-FPM
    ├── MySQL
    ├── Redis (opcional no início)
    ├── worker de filas
    └── storage de imagens
```

## DNS

Crie registros `A` apontando para o IP da VPS:

```text
catalogos.seudominio.com
*.catalogos.seudominio.com
```

Para um domínio próprio de cliente, cadastre o domínio na tabela `tenants` pelo comando `tenant:create` e direcione o DNS dele para a VPS. Automatização completa de domínios e SSL deve ser a próxima etapa antes de liberar o autosserviço.

## Configuração

1. Instale Nginx, PHP-FPM 8.2+, Composer, MySQL, Node.js e Supervisor.
2. Clone o projeto em `/var/www/catalogo-saas/current`.
3. Configure o document root para a pasta `public`.
4. Use `deploy/hostinger-nginx.conf` como referência, ajustando domínio e versão do PHP-FPM.
5. Crie o `.env` de produção a partir do `.env.example`.
6. Nunca versiona ou compartilhe o `.env` real.

Exemplo das variáveis essenciais:

```dotenv
APP_NAME="Catálogo SaaS"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://catalogos.seudominio.com
APP_DISPLAY_TIMEZONE=America/Sao_Paulo
CATALOG_BASE_DOMAIN=catalogos.seudominio.com
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nome_fornecido_pela_hostinger
DB_USERNAME=usuario_fornecido_pela_hostinger
DB_PASSWORD=senha_forte

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
AUDIT_RETENTION_DAYS=180
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=uploads
```

Configure também o SMTP da conta de envio para habilitar a recuperação de senha:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=587
MAIL_USERNAME=contato@seudominio.com
MAIL_PASSWORD=senha_exclusiva_do_email
MAIL_FROM_ADDRESS=contato@seudominio.com
MAIL_FROM_NAME="Catálogo SaaS"
```

Confirme no hPanel os dados SMTP e a porta da conta contratada. A aplicação sempre responde de forma genérica ao pedido de recuperação, sem revelar se um endereço está cadastrado.

## Primeiro deploy

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan key:generate
php artisan migrate --force
php artisan optimize
```

As imagens são gravadas diretamente em `public/uploads`, pois hospedagens compartilhadas podem bloquear a função PHP `exec()` usada pelo `storage:link`. Não execute `php artisan storage:link`.

Depois, dê acesso de escrita ao usuário do PHP somente em `storage`, `bootstrap/cache` e `public/uploads`.

As pastas temporárias obrigatórias do Laravel já estão incluídas no repositório. Se uma instalação antiga exibir `View path not found`, atualize o projeto pelo Git antes de executar novamente `php artisan optimize:clear`.

Crie a conta interna que administrará as empresas da plataforma:

```bash
php artisan platform:admin administrador@seudominio.com --name="Administrador da Plataforma"
```

O comando solicita uma senha de pelo menos 12 caracteres, com letra maiúscula, minúscula, número e símbolo. Após entrar normalmente em `/entrar`, o superadministrador será direcionado para `/plataforma`.

Se uma versão anterior chegou a criar a conta pública de demonstração, remova somente esse acesso sem apagar o catálogo associado:

```bash
php artisan security:remove-demo-account --force
```

Não execute `php artisan db:seed` em produção. A versão atual bloqueia os dados demonstrativos quando `APP_ENV=production`, mas o comando acima ainda é necessário para instalações antigas.

Configure o worker usando `deploy/hostinger-queue.conf` e recarregue o Supervisor. Adicione também um cron executado a cada minuto:

```cron
* * * * * cd /var/www/catalogo-saas/current && php artisan schedule:run >> /dev/null 2>&1
```

## Atualizações

Em cada nova versão:

```bash
php artisan down --retry=30
git pull --ff-only
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan security:remove-demo-account --force
php artisan optimize
php artisan queue:restart
php artisan up
```

Use releases versionadas e link simbólico para obter rollback confiável antes de atender clientes pagantes.

### Atualização do construtor de catálogo

Depois de enviar esta versão para a hospedagem compartilhada, entre na pasta que contém o arquivo `artisan` e execute:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
```

Os arquivos compilados dos templates já estão em `public/build`; não é necessário executar Node.js na Hostinger. Em seguida, entre no painel do cliente, acesse **Aparência**, selecione o template desejado e publique.

### Controles de acesso e auditoria

Após a migration de segurança, o próximo acesso de cada superadministrador exigirá a configuração de um aplicativo TOTP por QR Code ou chave manual. Salve os códigos de recuperação fora do servidor. O menu **Auditoria** registra operações de escrita, tentativas rejeitadas, usuário, empresa, IP e agente do navegador, sem copiar senhas ou o conteúdo dos formulários.

O cron `schedule:run` já descrito neste documento executa diariamente a retenção dos logs. O padrão é 180 dias. Para executar manualmente:

```bash
php artisan audit:prune
```

O menu **Ocorrências** registra os protocolos apresentados aos usuários. Ocorrências operacionais permanecem por 90 dias; quando classificadas pelo Superadmin como investigação de segurança, permanecem por 180 dias. Falhas HTTP 500 e 503 entram automaticamente com situação **Em investigação**. Configure no `.env`:

```dotenv
ERROR_RETENTION_DAYS=90
SECURITY_ERROR_RETENTION_DAYS=180
PERSONAL_DATA_INCIDENT_RETENTION_YEARS=5
```

O mesmo cron remove diariamente os registros cujo prazo individual terminou. Para executar a limpeza manualmente:

```bash
php artisan occurrences:prune
```

### Canal de privacidade

Preencha os dados reais do agente responsável antes de publicar `/privacidade`:

```dotenv
PRIVACY_CONTROLLER_NAME="Razão social"
PRIVACY_CONTROLLER_DOCUMENT="CNPJ"
PRIVACY_CONTACT_EMAIL=privacidade@seudominio.com
PRIVACY_OFFICER_NAME="Nome do encarregado, quando aplicável"
PRIVACY_VERIFICATION_HOURS=24
PRIVACY_TRACKING_LINK_HOURS=168
PRIVACY_UNVERIFIED_RETENTION_DAYS=30
PRIVACY_REQUEST_RETENTION_DAYS=730
```

O SMTP deve estar funcional: cada solicitação só avança após a confirmação do e-mail por link assinado. Mudanças de situação enviam um novo link temporário de acompanhamento; o protocolo isolado não concede acesso. O menu **Privacidade** do Superadmin separa a mensagem destinada ao solicitante das notas internas. Ambos os campos e os demais dados pessoais são criptografados com `APP_KEY`; preserve essa chave no plano seguro de recuperação.

O agendador executa `privacy:prune` diariamente. Para executar manualmente:

```bash
php artisan privacy:prune
```

Incidentes confirmados envolvendo dados pessoais não podem ser rebaixados pelo painel e ficam retidos pelo prazo mínimo configurado. A decisão de comunicação à ANPD e aos titulares exige avaliação do caso concreto pelo responsável por privacidade.

### Central de Comunicação Segura

Configure a retenção das mensagens no `.env`:

```dotenv
COMMUNICATION_RETENTION_DAYS=60
```

O Superadmin pode publicar ou agendar mensagens no menu **Comunicações**, selecionar empresas e papéis, definir prioridade e exigir confirmação de ciência. Assuntos, mensagens iniciais e respostas são criptografados com `APP_KEY`. O e-mail contém somente um aviso genérico e o protocolo; o conteúdo exige autenticação no painel.

O agendador executa `communications:publish` a cada minuto e `communications:prune` diariamente. Por isso, mantenha o cron `schedule:run` da Hostinger ativo. Para validar manualmente:

```bash
php artisan communications:publish
php artisan communications:prune
```

A exclusão remove a comunicação, respostas e destinatários do banco ativo. Cópias criptografadas podem permanecer somente até o vencimento normal dos backups da hospedagem.

#### Ativação do Web Push

O Web Push exige HTTPS, as extensões PHP `curl`, `mbstring` e `openssl` e um par VAPID permanente. Depois de instalar as dependências do Composer, gere o par uma única vez:

```bash
php artisan push:vapid-generate
```

Copie o resultado para o `.env` e defina um contato válido:

```dotenv
WEBPUSH_VAPID_SUBJECT=mailto:privacidade@seudominio.com
WEBPUSH_VAPID_PUBLIC_KEY="chave-publica-gerada"
WEBPUSH_VAPID_PRIVATE_KEY="chave-privada-gerada"
WEBPUSH_TTL=300
WEBPUSH_ALLOWED_ENDPOINT_HOSTS=fcm.googleapis.com,updates.push.services.mozilla.com,web.push.apple.com,.notify.windows.com
```

Não versione, envie por mensagem ou regenere a chave privada. A troca do par invalida as assinaturas existentes e exige nova autorização dos usuários. A lista de provedores autorizados reduz o risco de uso do servidor para requisições arbitrárias; só a altere depois de validar o endpoint emitido por um navegador legítimo. Depois da configuração, execute `php artisan optimize:clear` e `php artisan optimize`.

Valide o ambiente sem revelar as chaves:

```bash
php artisan push:diagnose
```

Todos os itens devem retornar `OK`, inclusive **Formato das chaves VAPID**. Se a geração de chave EC falhar, confirme com o suporte da hospedagem a extensão OpenSSL com curva `prime256v1` e a localização do arquivo `openssl.cnf`/variável `OPENSSL_CONF`. Depois de alterar o `.env`, execute `php artisan optimize:clear` antes de repetir o diagnóstico.

Cada usuário ativa ou desativa o próprio dispositivo no menu **Comunicações**. A assinatura é vinculada ao usuário autenticado e criptografada com `APP_KEY`. A notificação de tela bloqueada é sempre genérica; o assunto e o conteúdo somente são carregados após login e MFA.

### Backup criptografado e restauração

A aplicação possui backup próprio compatível com hospedagem compartilhada, sem `exec()` ou `mysqldump`. Ele inclui banco funcional e imagens dos clientes, usa uma chave exclusiva e recusa restauração sobre uma instalação que já possua dados.

```bash
php artisan backup:key-generate
php artisan optimize:clear
php artisan backup:diagnose
php artisan backup:create
php artisan backup:verify
php artisan backup:list
```

Comece com `BACKUP_ENABLED=false`. Depois de baixar uma cópia externa e homologar sua restauração em ambiente separado, altere para `true`. O cron existente criará o backup no horário configurado e removerá cópias vencidas sem ultrapassar o mínimo de segurança.

O arquivo local fica em `storage/app/private/backups`, fora da área pública. Ele ainda precisa ser copiado para um local independente da hospedagem. A guarda externa da `APP_KEY`, da `BACKUP_ENCRYPTION_KEY` e das chaves VAPID é obrigatória para recuperação completa. Consulte `docs/operacao/BACKUP_RESTAURACAO.md` antes de restaurar.

## Itens necessários antes da operação comercial

- cobrança recorrente e webhooks idempotentes;
- recuperação de senha e verificação de e-mail;
- política de limites por plano;
- logs de auditoria;
- backup externo e restauração real homologados;
- armazenamento S3 compatível e CDN para imagens;
- testes de isolamento entre empresas;
- LGPD: termos, privacidade, exclusão e exportação de dados.
