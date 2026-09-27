# Matriz operacional de retenção

| Registro | Prazo padrão | Início da contagem | Destino |
|---|---:|---|---|
| Ocorrência operacional | 90 dias | criação | exclusão automática |
| Suspeita em investigação de segurança | 180 dias | classificação | exclusão automática se não promovida a incidente confirmado |
| Incidente confirmado envolvendo dados pessoais | mínimo de 5 anos | confirmação/conhecimento documentado | exclusão somente após prazo e liberação do responsável |
| Solicitação não confirmada por e-mail | 30 dias | criação | exclusão automática |
| Solicitação confirmada/concluída | 730 dias, sujeito à validação jurídica | confirmação ou conclusão | exclusão automática após o prazo |
| Auditoria | 180 dias por padrão | criação | exclusão automática |

## Regras

1. Obrigação legal, ordem de autoridade, investigação ou defesa de direito pode suspender a exclusão; a justificativa deve ser registrada.
2. A retenção deve alcançar banco, arquivos, índices, cache e o ciclo possível de restauração dos backups.
3. Dados não devem ser copiados para observações quando um identificador ou protocolo for suficiente.
4. Toda mudança de prazo deve ser aprovada, documentada e refletida no `.env` e nos documentos públicos aplicáveis.
