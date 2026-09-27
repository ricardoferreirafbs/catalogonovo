# Plano operacional de resposta a incidentes

## Fluxo

1. **Detectar e registrar:** preservar protocolo, horário, sistema, empresa e evidências sem copiar senhas ou tokens.
2. **Conter:** bloquear acessos, revogar sessões/segredos, isolar o componente e preservar evidências.
3. **Classificar:** distinguir erro técnico, vulnerabilidade, suspeita de segurança e incidente confirmado.
4. **Avaliar dados pessoais:** categorias, titulares, volume, medidas existentes e possíveis consequências.
5. **Definir papéis:** identificar o controlador responsável pela decisão e os operadores/suboperadores envolvidos.
6. **Avaliar risco ou dano relevante:** registrar a justificativa, inclusive quando a decisão for não comunicar.
7. **Comunicar:** quando aplicável, preparar comunicação preliminar ou completa à ANPD e aos titulares no prazo vigente.
8. **Erradicar e recuperar:** corrigir causa raiz, restaurar o serviço e monitorar recorrência.
9. **Encerrar e aprender:** documentar cronologia, decisões, comunicações e ações preventivas.

## Dados mínimos no registro

- momento da detecção e da ciência;
- sistemas, empresas e operadores envolvidos;
- categorias e quantidade estimada de titulares/dados;
- medidas de segurança e contenção;
- avaliação de risco e fundamento da decisão;
- comunicações realizadas e respectivas datas;
- causa raiz, recuperação e prevenção de recorrência;
- responsáveis internos e aprovações.

## Escalonamento

- Erros HTTP 500/503: investigação técnica automática.
- Indício de acesso indevido, vazamento, perda ou alteração: triagem de segurança imediata.
- Confirmação de dados pessoais afetados: promover para incidente confirmado, preservar por pelo menos cinco anos e acionar responsável jurídico/privacidade.
- Possível risco ou dano relevante: priorizar avaliação e comunicação conforme a Resolução CD/ANPD nº 15/2024.

Este plano não substitui avaliação jurídica do caso concreto nem os procedimentos oficiais de comunicação da ANPD.
