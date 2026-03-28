# 🔹Documento Técnico — Requisitos Não Funcionais🔹

## 1. Segurança
- Sessão autenticada obrigatória para áreas restritas.
- Autorização por perfil em rotas sensíveis.
- Proteção CSRF em operações mutáveis.
- Uso de prepared statements em consultas críticas.
- Headers de segurança para resposta HTML/JSON.

## 2. Desempenho
- Consultas agregadas para dashboards e análises.
- Endpoints JSON com cache control quando aplicável.
- Geração de relatório com controle de memória/tempo em serviços PDF.

## 3. Confiabilidade
- Tratamento de erro com códigos HTTP adequados.
- Registro de ações em histórico auditável.
- Idempotência de envio para reduzir duplicidade de submissão.

## 4. Usabilidade
- Layout padronizado com navegação lateral/topo.
- Filtros contextuais por módulo.
- Mensagens de validação e estado operacional.
- Interfaces responsivas para desktop e mobile.

## 5. Manutenibilidade
- Separação de responsabilidades em Core/Helpers/Services.
- Organização por domínio funcional em `pages` e `api`.
- Documentação incremental em `docs/`.

## 6. Portabilidade
- Execução local em WAMP e publicação em ambiente Apache/PHP.
- Dependência de configuração por `.env`.

## 7. Limitações conhecidas
- Sem suíte automatizada de testes no repositório atual.
- Dependência de integrações externas para parte das operações (INMET, planilha COMPDEC, SMTP, tiles).
