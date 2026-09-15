# Catálogo SaaS

Plataforma multiempresa para publicar catálogos personalizados mantendo um único padrão para produtos, preços e imagens. O catálogo público combina renderização Laravel para SEO com uma experiência Vue interativa para busca e filtros.

## Entregue neste MVP

- Isolamento por empresa (`tenant_id`) e resolução por subdomínio ou domínio próprio.
- Catálogo público responsivo, busca instantânea, categorias e página individual de produto.
- Painel autenticado com indicadores e CRUD de produtos.
- Cadastro padronizado de preço normal, promocional, disponibilidade, SKU e foto.
- Editor de identidade visual com cores, tipografia, capa e formato dos cards.
- Contato por WhatsApp e produtos com preço sob consulta.
- Comando seguro para provisionar novos clientes.
- Configurações de referência para Nginx e Supervisor em VPS Hostinger.

## Requisitos

- PHP 8.2 ou superior, com extensões comuns do Laravel e MySQL.
- Composer 2.
- Node.js 20 ou superior e npm.
- MySQL 8 recomendado.

## Início local

```bash
cp .env.example .env
composer install
php artisan key:generate
```

Para desenvolvimento rápido com SQLite, altere o `.env`:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=/caminho/absoluto/para/database/database.sqlite
```

Em seguida:

```bash
php artisan migrate --seed
php artisan storage:link
npm ci
npm run build
php artisan serve
```

Catálogo: `http://127.0.0.1:8000`

Painel: `http://127.0.0.1:8000/entrar`

Credenciais da demonstração:

- E-mail: `admin@catalogo.test`
- Senha: `catalogo123`

O seeder é exclusivo para desenvolvimento. Não execute `--seed` em produção.

## Novo cliente

```bash
php artisan tenant:create "Empresa Exemplo" administrador@empresa.com --slug=empresa --domain=catalogo.empresa.com.br --plan=professional
```

O comando solicita a senha sem exibi-la no terminal. A partir daí, o catálogo responde pelo domínio cadastrado ou por `empresa.catalogos.seudominio.com`.

## Qualidade e segurança

```bash
php artisan test
npm run build
php artisan optimize
```

Antes de produção, use `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, backup externo e uma senha exclusiva para o banco.

Consulte [HOSTINGER.md](HOSTINGER.md) para a publicação.
