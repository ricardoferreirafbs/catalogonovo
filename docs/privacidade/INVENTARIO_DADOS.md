# Inventário inicial de dados pessoais — Catalog

Este documento é um registro técnico inicial e deve ser validado pelo responsável jurídico/privacidade e atualizado quando fornecedores, finalidades ou funcionalidades mudarem.

| Processo | Dados | Titulares | Finalidade | Papel provável da Catalog | Destinatários/infraestrutura | Retenção técnica |
|---|---|---|---|---|---|---|
| Administração SaaS | nome, e-mail, credenciais protegidas, papel, MFA criptografado | administradores e usuários dos clientes | autenticação, autorização, suporte e segurança | controladora para a própria relação; confirmar contrato | Hostinger, SMTP | enquanto a conta estiver ativa e período posterior definido em contrato |
| Catálogo do cliente | produtos, imagens, contatos comerciais e conteúdo definido pelo cliente | representantes, contatos ou pessoas eventualmente publicadas | hospedagem e publicação do catálogo | normalmente operadora, conforme instruções do cliente | Hostinger/CDN quando adotada | contrato e ciclo de backup |
| Auditoria | usuário, empresa, rota, IP, agente do navegador e resultado | usuários e visitantes autenticados | segurança, responsabilização e investigação | controladora para segurança da plataforma | Hostinger | `AUDIT_RETENTION_DAYS` |
| Ocorrências | protocolo, código, usuário, empresa, rota, classe técnica e hash de IP | usuários e visitantes | suporte, disponibilidade e segurança | controladora para segurança da plataforma | Hostinger | 90 dias; 180 dias em triagem de segurança; mínimo de 5 anos em incidente confirmado com dados pessoais |
| Solicitações de titulares | nome, e-mail, empresa relacionada, pedido e decisão | solicitantes | verificar identidade e atender direitos | controladora ou canal da operadora, conforme o pedido | Hostinger e SMTP | 30 dias sem confirmação; 730 dias após confirmação/conclusão por padrão |
| Comunicações seguras | assunto, mensagens, respostas, empresa, destinatários, leitura e ciência | usuários dos clientes e administradores da plataforma | suporte, avisos operacionais, segurança, cobrança e manutenção | conforme o tema da comunicação e o contrato | Hostinger e SMTP | 60 dias por padrão; registros formais relacionados seguem sua própria retenção |
| Sessões e recuperação | sessão, IP, agente, token temporário e e-mail | usuários | autenticação e recuperação de acesso | controladora | Hostinger e SMTP | prazo técnico da sessão/token |
| Backups | cópia dos dados acima | mesmos titulares | continuidade e recuperação | conforme o processo original | Hostinger/fornecedor de backup | definir janela, expiração e teste de restauração |

## Pendências de validação organizacional

- Razão social, CNPJ e contato público do controlador.
- Indicação ou dispensa fundamentada do encarregado e canal equivalente.
- Lista completa de fornecedores e localização do tratamento.
- Hipótese legal de cada finalidade, definida com assessoria jurídica.
- Prazos contratuais, fiscais, consumeristas e de defesa de direitos.
- Procedimento de exclusão nos backups e encerramento de clientes.
- Existência de cookies, analytics, publicidade ou cobrança ainda não inventariados.
