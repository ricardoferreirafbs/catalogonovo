# Catálogo SaaS

Plataforma multiempresa para publicar catálogos personalizados mantendo um único padrão para produtos, preços e imagens. O catálogo público combina renderização Laravel para SEO com uma experiência Vue interativa para busca e filtros.

## Entregue neste MVP

- Isolamento por empresa (`tenant_id`) e resolução por subdomínio ou domínio próprio.
- Catálogo público responsivo, busca instantânea, categorias e página individual de produto.
- Painel autenticado com indicadores e CRUD de produtos.
- Cadastro padronizado de preço normal, promocional, disponibilidade, SKU e foto.
- Editor de identidade visual com cores, tipografia, capa e formato dos cards.
- Template “Vértice”, com estrutura editorial marcante e foco comercial.
- Template “Aurora”, com identidade delicada, colagem de imagens, seção institucional e chamada para orçamento.
- Editor completo de conteúdo: hero, indicadores, faixa, vitrine, experiência, jornada, contato, FAQ, SEO e rodapé.
- Categorias e subcategorias com até quatro níveis, publicação no menu principal e filtros por hierarquia.
- Menus livres para âncoras, páginas internas e endereços externos.
- Contato por WhatsApp e produtos com preço sob consulta.
- Comando seguro para provisionar novos clientes.
- Recuperação de senha por link temporário, com resposta que não revela contas cadastradas.
- Segundo fator TOTP obrigatório para superadministradores, cadastro por QR Code e códigos de recuperação de uso único.
- Trilha de auditoria para operações de escrita, tentativas rejeitadas, origem e responsável.
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
npm ci
npm run build
php artisan serve
```

As imagens são gravadas diretamente em `public/uploads`; nenhum link simbólico ou comando `storage:link` é necessário.

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

O comando solicita a senha sem exibi-la no terminal e exige ao menos 12 caracteres, com letra maiúscula, minúscula, número e símbolo. A partir daí, o catálogo responde pelo domínio cadastrado ou por `empresa.catalogos.seudominio.com`.

## Administração da plataforma

Crie uma única conta interna de superadministrador:

```bash
php artisan platform:admin administrador@seudominio.com --name="Administrador da Plataforma"
```

Depois de entrar em `/entrar`, essa conta é direcionada para `/plataforma`. Nesse painel é possível cadastrar, editar, suspender, reativar e excluir empresas, além de criar ou redefinir o acesso do administrador de cada cliente. O comando `tenant:create` permanece disponível apenas para manutenção.

No primeiro acesso, o superadministrador precisa cadastrar a chave TOTP em um aplicativo autenticador e salvar os códigos de recuperação. Os segredos são criptografados com `APP_KEY`; por isso, essa chave deve permanecer protegida e incluída no plano seguro de recuperação da infraestrutura.

Os usuários das empresas podem ativar voluntariamente o segundo fator em **Painel → Segurança**. A configuração oferece QR Code, chave manual e oito códigos de recuperação de uso único. Depois de ativado, o segundo fator é exigido em toda nova sessão e nas rotas administrativas; a desativação exige a senha atual e um código válido.

Cada empresa possui quatro papéis fixos: **Proprietário**, **Administrador**, **Editor** e **Visualizador**. Proprietários administram toda a equipe; administradores podem convidar e gerenciar editores e visualizadores; editores cuidam dos produtos, estrutura e conteúdo; visualizadores possuem acesso somente de leitura. Os convites são enviados por e-mail com link temporário para definição da senha e nenhuma senha é transmitida na mensagem.

## Construtor do catálogo

No painel de cada empresa:

- **Estrutura:** administra menus, categorias e subcategorias;
- **Conteúdo:** edita todas as áreas comerciais e envia logo e imagem de capa;
- **Aparência:** seleciona o template e personaliza cores, tipografia e cards.

Para ativar um visual, abra **Aparência**, escolha **Aurora**, **Vértice** ou **Nítido** e publique. Os textos sugeridos funcionam como conteúdo inicial e podem ser substituídos em **Conteúdo**. O template Aurora permite enviar três imagens para a colagem principal e uma imagem institucional adicional.

## Qualidade e segurança

```bash
php artisan test
npm run build
php artisan optimize
```

Antes de produção, use `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, backup externo e uma senha exclusiva para o banco. Mantenha `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true` e `SESSION_SAME_SITE=lax`.

Instalações antigas devem remover a conta conhecida de demonstração com `php artisan security:remove-demo-account --force`. O comando preserva a empresa, os produtos e o catálogo.

Para a recuperação de senha funcionar, configure um servidor SMTP real no `.env`. A trilha de auditoria mantém 180 dias por padrão, ajustáveis por `AUDIT_RETENTION_DAYS`, e é limpa diariamente pelo agendador do Laravel.

Consulte [HOSTINGER.md](HOSTINGER.md) para a publicação.
