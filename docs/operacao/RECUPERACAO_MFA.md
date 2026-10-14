# Recuperação administrativa de MFA

## Finalidade

Este procedimento atende Proprietários que perderam simultaneamente o aplicativo autenticador e todos os códigos de recuperação. Ele não substitui a recuperação normal por código de emergência e não deve ser usado por conveniência.

## Autorização

Somente um Superadmin autenticado com senha e MFA pode executar a recuperação. A operação exige uma nova confirmação da senha e um TOTP ainda não utilizado. O código usado para entrar na sessão não pode ser reaproveitado; aguarde a próxima janela de 30 segundos.

## Validação de identidade

Antes de abrir o formulário, o atendente deve validar o solicitante por um dos meios aprovados:

1. resposta recebida pelo e-mail já cadastrado;
2. contato pelo telefone previamente cadastrado, sem aceitar número informado apenas na solicitação atual;
3. confirmação pelo contato contratual registrado.

Nunca solicite senha, código TOTP, código de recuperação ou segredo do QR Code. A referência informada no formulário deve identificar o ticket ou registro externo onde a validação foi documentada, sem copiar dados pessoais desnecessários para a auditoria.

## Execução

1. Acesse **Plataforma → Empresas** e edite a empresa correta.
2. Localize **Recuperação de acesso**.
3. Confira o nome e o e-mail do Proprietário exibido.
4. Selecione o método de validação utilizado.
5. Informe a referência externa do atendimento.
6. Digite exatamente o e-mail atual do Proprietário.
7. Confirme sua senha de Superadmin.
8. Informe um novo código do seu autenticador.
9. Marque a declaração de validação e execute a recuperação.
10. Registre o protocolo `MFA-...` apresentado pela plataforma no atendimento externo.

## Efeitos de segurança

A operação:

- mantém a senha atual do Proprietário;
- apaga o segredo MFA e os códigos de recuperação anteriores;
- troca o token de permanência da conta;
- encerra todas as sessões do Proprietário;
- invalida redefinições de senha pendentes;
- remove assinaturas Web Push vinculadas aos dispositivos antigos;
- envia um aviso ao e-mail cadastrado;
- registra protocolo, Superadmin, empresa, Proprietário, método de verificação e quantidades revogadas na auditoria.

Senha, TOTP, segredo MFA e códigos de recuperação nunca são gravados na auditoria.

## Retorno do Proprietário

No próximo acesso, o Proprietário entra com a mesma senha e é bloqueado na tela de configuração do MFA. O painel somente é liberado depois do cadastro de um novo autenticador. As assinaturas Web Push precisam ser habilitadas novamente pelo próprio usuário.

## Falha no aviso por e-mail

A indisponibilidade do SMTP não reverte uma recuperação já concluída. A plataforma apresenta um alerta e registra `notification_sent=false` na auditoria. O operador deve abrir uma ocorrência vinculada ao mesmo protocolo e avisar o Proprietário pelo canal contratual previamente validado, sem transmitir credenciais.

## Homologação

- [ ] Somente Superadmin com MFA acessa a função.
- [ ] Senha incorreta do Superadmin é recusada.
- [ ] TOTP incorreto ou reutilizado é recusado.
- [ ] E-mail diferente do Proprietário é recusado.
- [ ] Proprietário de outra empresa não pode ser selecionado.
- [ ] Senha do Proprietário continua válida.
- [ ] Sessões, MFA anterior e assinaturas push são revogados.
- [ ] Proprietário recebe o aviso com protocolo.
- [ ] Novo login obriga o cadastramento de MFA.
- [ ] Auditoria não contém senha, TOTP ou segredo.
