# FAQ de erros do Catálogo

Página pública: `/ajuda/erros`

Esta referência pode ser publicada na central de ajuda. O código identifica a categoria do erro e permanece igual em todas as ocorrências. O protocolo alfanumérico é exclusivo de cada ocorrência e permite que o suporte localize o registro correspondente nos logs da aplicação.

## CAT-401-AUTENTICACAO — Acesso não autenticado

**O que significa?** A sessão não possui uma autenticação válida.

**O que fazer?** Entre novamente com seu e-mail, senha e MFA. Se o erro continuar, apague apenas os cookies deste site e tente de novo.

## CAT-403-ACESSO — Acesso não permitido

**O que significa?** O papel do usuário não possui a permissão necessária para a área ou ação solicitada.

**O que fazer?** Confirme se entrou na empresa correta. Solicite ao Proprietário ou Administrador que revise seu papel. Administradores não podem promover usuários a Proprietário.

## CAT-404-RECURSO — Conteúdo não encontrado

**O que significa?** A página ou o registro não existe, foi removido ou pertence a outra empresa. A plataforma não informa qual dessas condições ocorreu para preservar o isolamento entre clientes.

**O que fazer?** Volte ao painel e abra o item pela listagem atualizada. Confira também se o endereço foi digitado corretamente.

## CAT-419-SESSAO — Sessão expirada

**O que significa?** O token de proteção do formulário expirou ou não corresponde à sessão atual.

**O que fazer?** Atualize a página, entre novamente se solicitado e repita a ação. Evite deixar formulários abertos por muitas horas.

## CAT-429-LIMITE — Muitas tentativas

**O que significa?** A proteção contra abuso bloqueou temporariamente novas tentativas, por exemplo no login ou MFA.

**O que fazer?** Aguarde alguns minutos sem repetir a solicitação e tente novamente. Se não reconhecer as tentativas, altere sua senha e avise o responsável pela empresa.

## CAT-500-INTERNO — Falha inesperada

**O que significa?** A plataforma encontrou uma condição que não conseguiu concluir com segurança. Nenhum detalhe técnico é mostrado ao visitante.

**O que fazer?** Tente novamente uma vez. Se persistir, envie ao suporte o código e o protocolo exibidos, a data, o horário aproximado e a ação que estava realizando.

## CAT-503-INDISPONIVEL — Serviço temporariamente indisponível

**O que significa?** A plataforma está em manutenção ou um serviço necessário está temporariamente indisponível.

**O que fazer?** Aguarde alguns minutos e tente novamente. Consulte o canal oficial de disponibilidade caso a interrupção continue.

## Orientação de segurança para o atendimento

- Solicite apenas o código do erro, o protocolo, a data, o horário e uma descrição da ação.
- Nunca solicite senha, código MFA, segredo do QR Code ou código de recuperação.
- Pesquise o protocolo no arquivo `storage/logs/laravel.log` usando somente acesso administrativo autorizado.
- Não publique caminhos internos, consultas SQL, stack traces, identificadores de outros clientes ou dados pessoais encontrados nos logs.
- Defina retenção e acesso aos logs conforme a política de segurança e privacidade da operação.
