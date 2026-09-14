# Implantação na Hostinger

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
CATALOG_BASE_DOMAIN=catalogos.seudominio.com
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nome_fornecido_pela_hostinger
DB_USERNAME=usuario_fornecido_pela_hostinger
DB_PASSWORD=senha_forte

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=public
```

## Primeiro deploy

```bash
composer install --no-dev --optimize-autoloader
corepack enable
pnpm install --frozen-lockfile
pnpm run build
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

Depois, dê acesso de escrita ao usuário do PHP-FPM somente em `storage` e `bootstrap/cache`.

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
corepack enable
pnpm install --frozen-lockfile
pnpm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan up
```

Use releases versionadas e link simbólico para obter rollback confiável antes de atender clientes pagantes.

## Itens necessários antes da operação comercial

- cobrança recorrente e webhooks idempotentes;
- recuperação de senha e verificação de e-mail;
- política de limites por plano;
- logs de auditoria;
- backup automático testado;
- armazenamento S3 compatível e CDN para imagens;
- testes de isolamento entre empresas;
- LGPD: termos, privacidade, exclusão e exportação de dados.
