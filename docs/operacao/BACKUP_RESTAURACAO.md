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
BACKUP_MAX_AGE_HOURS=30
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

`BACKUP_TIME` utiliza UTC. Assim, `02:30` corresponde a `23:30` do dia anterior em Brasília quando o deslocamento é UTC-3. O limite de 30 horas concede margem operacional sem deixar uma falha diária passar despercebida.

## Comprovação do cron na Hostinger

No hPanel, configure uma tarefa cron a cada minuto. Substitua o caminho pelo caminho absoluto real da pasta que contém `artisan`:

```cron
* * * * * cd /home/USUARIO/domains/DOMINIO/public_html && /usr/bin/php artisan schedule:run >> /home/USUARIO/schedule.log 2>&1
```

Depois do primeiro backup no horário programado, execute:

```bash
php artisan schedule:list
php artisan backup:diagnose
php artisan backup:verify
```

O diagnóstico somente aprova o cron quando encontra um pacote com origem **agendador**, autenticada dentro do próprio backup, e com idade inferior a `BACKUP_MAX_AGE_HOURS`. Um backup criado manualmente não comprova o funcionamento do cron. Guarde a saída do diagnóstico e a data no registro de homologação.

Depois da confirmação, o redirecionamento do cron para `schedule.log` pode ser trocado por `/dev/null`; mantenha logs ou alertas enquanto a operação ainda estiver em piloto.

## Cópia externa obrigatória

A cópia mantida na Hostinger não é considerada cópia externa. Até existir integração automática com um armazenamento independente, a rotina operacional obrigatória é:

1. Após o backup diário, o responsável acessa **Plataforma → Backup e restauração** usando MFA.
2. Confirma a integridade e baixa o backup geral mais recente.
3. Armazena o arquivo em repositório independente da Hostinger, com acesso restrito e MFA.
4. Mantém 14 dias e no mínimo 3 cópias externas, sem renomear ou editar os pacotes.
5. Registra data, nome do arquivo, tamanho, destino e responsável.
6. Uma vez por semana, abre uma cópia externa com `backup:verify` em ambiente isolado.
7. Uma vez por trimestre, executa uma restauração completa em homologação vazia.

A transferência deve ocorrer diariamente até duas horas depois do horário configurado. Se não houver responsável para a atividade diária, a Etapa 1 permanece aberta até a transferência externa ser automatizada.

## Cofre externo de recuperação

O cofre deve ser independente da hospedagem, possuir MFA, histórico de acesso e pelo menos dois responsáveis autorizados. Registre separadamente:

- `APP_KEY`;
- `BACKUP_ENCRYPTION_KEY`;
- `WEBPUSH_VAPID_PUBLIC_KEY` e `WEBPUSH_VAPID_PRIVATE_KEY`;
- instruções para localizar a cópia externa e o código-fonte da versão implantada.

Faça uma conferência por dupla custódia: uma pessoa registra e outra confirma que os valores podem ser recuperados. Não teste as chaves exibindo-as em terminal, ticket ou captura de tela; valide-as restaurando uma cópia em ambiente isolado.

## Objetivos de recuperação

- **RPO objetivo: até 24 horas.** Em perda total da Hostinger, admite-se perder no máximo as alterações posteriores à última cópia externa diária.
- **RTO objetivo: até 8 horas após a declaração do incidente.** Inclui preparar hospedagem, instalar a versão compatível, configurar segredos, restaurar e validar as jornadas críticas.

O tempo exibido por `backup:restore` mede a restauração técnica. O RTO real começa na declaração do incidente e termina quando login, MFA, catálogos, imagens, comunicações, privacidade e Web Push estão novamente validados. Registre ambos os tempos.

## Recuperação seletiva de uma empresa

Backups de empresa são pacotes de portabilidade e evidência; eles nunca devem ser importados diretamente sobre a produção. Para recuperar uma empresa:

1. suspenda alterações na empresa afetada;
2. selecione e autentique o backup da própria empresa;
3. abra uma ocorrência operacional com empresa, motivo, responsável e ponto de recuperação;
4. prepare banco e uploads temporários isolados;
5. extraia e valide os registros por engenharia, preservando os identificadores e vínculos;
6. compare com a produção e obtenha aprovação do Proprietário;
7. importe somente os dados aprovados em transação, mantendo uma cópia anterior;
8. valide catálogo, usuários, permissões, imagens e auditoria antes de reativar alterações.

Não existe restauração seletiva automática na versão atual. Qualquer importação direta sem ambiente temporário e aprovação é proibida.

## Dados excluídos presentes em backups

Uma exclusão no banco ativo não reescreve pacotes já criados. Durante o prazo de 14 dias, os dados podem permanecer criptografados apenas para continuidade, com acesso restrito e sem uso operacional. Eles desaparecem pela retenção normal; uma obrigação legal pode suspender a eliminação mediante registro formal.

Após restaurar uma cópia anterior, o responsável deve reaplicar todas as exclusões, encerramentos de empresa e solicitações de titulares ocorridas depois da data do backup antes de liberar o ambiente. O registro externo de homologação deve listar essas pendências sem copiar conteúdo pessoal desnecessário.

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

## Registro de homologação

| Evidência | Valor |
|---|---|
| Data e responsável | preencher na execução |
| Arquivo e tamanho | preencher na execução |
| Origem indicada pelo pacote | agendador |
| Cópia externa e destino | preencher sem registrar credenciais |
| Cofre e dupla custódia confirmados | sim/não |
| Início da declaração do teste | preencher na execução |
| Duração técnica informada pelo comando | preencher na execução |
| Retorno completo ao serviço | preencher na execução |
| RPO observado | preencher na execução |
| RTO observado | preencher na execução |
| Jornadas críticas aprovadas | login, MFA, empresas, produtos, imagens, comunicações, privacidade e Web Push |
| Resultado | aprovado/reprovado |
