# Matriz operacional de retenção

| Registro | Prazo padrão | Início da contagem | Destino |
|---|---:|---|---|
| Ocorrência operacional | 90 dias | criação | exclusão automática |
| Suspeita em investigação de segurança | 180 dias | classificação | exclusão automática se não promovida a incidente confirmado |
| Incidente confirmado envolvendo dados pessoais | mínimo de 5 anos | confirmação/conhecimento documentado | exclusão somente após prazo e liberação do responsável |
| Solicitação não confirmada por e-mail | 30 dias | criação | exclusão automática |
| Solicitação confirmada/concluída | 730 dias, sujeito à validação jurídica | confirmação ou conclusão | exclusão automática após o prazo |
| Auditoria | 180 dias por padrão | criação | exclusão automática |
| Comunicação segura e respostas | 60 dias por padrão | publicação, encerramento ou última interação | exclusão automática do conteúdo e dos destinatários |
| Assinatura Web Push | enquanto o usuário mantiver o recurso ativo | ativação ou renovação pelo navegador | exclusão ao desativar, expirar no provedor ou excluir o usuário |
| Backup criptografado | 14 dias por padrão, preservando ao menos 3 cópias | criação da cópia | exclusão automática após prazo e mínimo; cópias externas seguem política contratada |

## Regras

1. Obrigação legal, ordem de autoridade, investigação ou defesa de direito pode suspender a exclusão; a justificativa deve ser registrada.
2. A retenção deve alcançar banco, arquivos, índices, cache e o ciclo possível de restauração dos backups.
3. Dados não devem ser copiados para observações quando um identificador ou protocolo for suficiente.
4. Toda mudança de prazo deve ser aprovada, documentada e refletida no `.env` e nos documentos públicos aplicáveis.
