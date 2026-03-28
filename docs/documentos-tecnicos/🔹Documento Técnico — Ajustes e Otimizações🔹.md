# 🔹Documento Técnico — Ajustes e Otimizações🔹

## 1. Ajustes já aplicados no código atual

### Segurança e sessão
- Timeout de inatividade de sessão com encerramento automático.
- CSRF com token e controle de idempotência para submissões.
- Cabeçalhos de segurança para HTML e JSON.
- Sanitização de saídas em telas com `htmlspecialchars`.

### Banco e consultas
- Uso extensivo de prepared statements.
- Índices funcionais para consultas de alerta e histórico.
- Filtros de consulta por período, gravidade, evento e território.

### Operação e rastreabilidade
- Registro de trilha de ações no `historico_usuarios`.
- Paginação em listagens administrativas.
- Geração de relatórios PDF em serviços dedicados.

### Front-end
- Layout unificado por componentes compartilhados.
- Versionamento de assets por `filemtime` em páginas-chave.
- Painéis responsivos com experiência orientada à operação.

## 2. Otimizações recomendadas (próximas etapas)
- Mover dump SQL e scripts destrutivos para fora da área web pública.
- Remover fallback TLS inseguro na integração INMET.
- Fortalecer validação/parsing de KML para evitar bypass.
- Revisar algoritmo de interseção geográfica para casos de borda.
- Endurecer geração de número de alerta para cenários concorrentes.
- Revisar duplicidades de índices únicos em `alertas`.
- Evoluir para camada repository dedicada para reduzir SQL acoplado em controladores.

## 3. Plano sugerido de execução
1. Hardening de superfície pública e integrações externas.
2. Revisão de consistência de dados e concorrência.
3. Evolução arquitetural incremental (repositories/testes automatizados).

## 4. Referência
- Consolidação baseada no estado do código em **2026-03-28**.
