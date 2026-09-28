# Backup e restauração da Catalog

## Escopo e segurança

O backup da aplicação inclui as tabelas funcionais da plataforma e todos os arquivos em `public/uploads/tenants`. Sessões, cache, filas, tokens temporários de redefinição e falhas de fila não são copiados, pois são dados transitórios.

O formato `.catalog-backup` não depende de `exec()`, `mysqldump`, ZIP ou Sodium. Cada registro é autenticado e criptografado com AES-256-GCM, e o fechamento integral permite detectar alteração, chave incorreta ou truncamento. O arquivo não contém o `.env`.

Três segredos precisam ser guardados também fora da hospedagem, em cofre separado:

- `APP_KEY`, necessária para abrir campos criptografados do banco;
- `BACKUP_ENCRYPTION_KEY`, necessária para abrir o pacote;
- chaves VAPID, necessárias para manter as inscrições Web Push existentes.

Nunca envie esses segredos por e-mail, ticket, chat ou repositório Git.

## Configuração inicial

Na pasta que contém `artisan`, gere a chave uma única vez:

```bash
php artisan backup:key-generate
```

Copie a linha para o `.env`, guarde outra cópia no cofre externo e configure:

```dotenv
BACKUP_ENABLED=false
BACKUP_ENCRYPTION_KEY="base64:CHAVE_GERADA"
BACKUP_DISK=local
BACKUP_DIRECTORY=backups
BACKUP_RETENTION_DAYS=14
BACKUP_MINIMUM_COPIES=3
BACKUP_TIME=02:30
```

Limpe o cache e valide os requisitos:

```bash
php artisan optimize:clear
php artisan backup:diagnose
```

O primeiro diagnóstico informará que o agendamento está desativado. Isso é esperado até a homologação manual.

## Primeira cópia e verificação

```bash
php artisan backup:create
php artisan backup:list
php artisan backup:verify
```

O último comando precisa informar **Integridade e autenticação confirmadas**. No disco `local`, os novos backups gerais ficam em `storage/app/private/backups/general` e não são publicados por URL pública. Backups antigos mantidos diretamente em `backups` continuam reconhecidos.

O Superadmin também pode criar, verificar e baixar essas cópias em **Plataforma → Backup e restauração**. A execução da restauração permanece exclusivamente na linha de comando para impedir sobrescrita acidental da produção.

## Backup isolado de empresa

O Proprietário encontra **Painel → Backup da empresa**. A cópia inclui apenas o registro da própria empresa e os usuários, catálogo, comunicações, ocorrências, solicitações e uploads vinculados a ela. Administradores, editores e visualizadores não possuem essa permissão.

Os arquivos ficam separados em `backups/tenants/{id}`. A identificação da empresa é derivada da sessão autenticada no servidor; o cliente não informa nem controla o `tenant_id`. Antes do download, o pacote é novamente autenticado e o escopo interno precisa corresponder à empresa conectada. Backups de empresa não são aceitos pelo comando de restauração geral.

Baixe uma cópia pelo gerenciador de arquivos/SFTP da hospedagem e armazene-a fora da Hostinger. Uma cópia no mesmo servidor não protege contra perda da conta, falha do provedor ou exclusão ampla.

Depois da primeira restauração homologada, altere:

```dotenv
BACKUP_ENABLED=true
```

E execute:

```bash
php artisan optimize:clear
php artisan backup:diagnose
php artisan optimize
```

O cron `schedule:run` criará uma cópia diária e executará a retenção. O sistema mantém ao menos `BACKUP_MINIMUM_COPIES`, mesmo que sejam mais antigas que o prazo.

## Restauração homologada

A restauração foi deliberadamente limitada a uma instalação com banco funcional vazio e sem arquivos em `public/uploads/tenants`. Ela nunca sobrescreve uma produção existente.

1. Prepare um ambiente de homologação isolado.
2. Instale exatamente o código correspondente ou mais recente.
3. Configure uma cópia segura do `.env`, mantendo a `APP_KEY` original e a `BACKUP_ENCRYPTION_KEY`.
4. Execute `php artisan migrate --force` para criar as tabelas vazias.
5. Coloque o arquivo em `storage/app/private/backups/general`.
6. Verifique e restaure:

```bash
php artisan backup:verify backups/general/NOME_DO_ARQUIVO.catalog-backup
php artisan backup:restore backups/general/NOME_DO_ARQUIVO.catalog-backup --confirm=RESTORE-INTO-EMPTY-DATABASE
php artisan optimize:clear
```

7. Valide login/MFA, empresas, produtos, imagens, comunicações, solicitações de privacidade e Web Push.
8. Registre data, duração, tamanho, responsável, resultado e problemas encontrados no teste.

Em um destino configurado como produção, o comando também exige `--force`. Antes disso, coloque o site em manutenção. O uso de `--force` não remove a exigência de banco e uploads vazios:

```bash
php artisan down --retry=60
php artisan backup:restore backups/general/NOME_DO_ARQUIVO.catalog-backup --confirm=RESTORE-INTO-EMPTY-DATABASE --force
php artisan optimize:clear
php artisan up
```

## Critérios para homologação

- criação sem erro;
- `backup:verify` aprovado;
- cópia externa disponível;
- restauração concluída em ambiente vazio;
- quantidade de registros e arquivos correspondente ao fechamento autenticado;
- acesso a campos criptografados confirmado com a `APP_KEY` original;
- imagens exibidas;
- tempo total registrado para definir o RTO;
- perda máxima aceitável comparada ao backup diário para confirmar o RPO de até 24 horas.

Até a restauração real ser concluída e registrada, existe apenas uma cópia, não um processo de recuperação homologado.
