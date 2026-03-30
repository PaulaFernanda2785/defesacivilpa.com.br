# 🔹Documento Técnico — Arquitetura de Banco de Dados🔹

## 1. Visão geral
O banco relacional centraliza operação de alertas, territorialização, usuários e auditoria.

## 2. Entidades nucleares
- `alertas`: entidade principal de evento/alerta.
- `alerta_municipios`: municípios associados ao alerta.
- `alerta_regioes`: regiões associadas ao alerta.
- `usuarios`: identidade e perfil de acesso.
- `historico_usuarios`: trilha de ações de auditoria.
- `municipios_regioes_pa`: base territorial de referência.

## 3. Estratégia de relacionamento
- `alertas` como tabela-pai de territorialização.
- Relação 1:N de `alertas` para tabelas satélite.
- Base territorial desacoplada para suporte de filtros e agregações.

## 4. Integridade e estrutura
- Chaves primárias em todas as entidades.
- FKs com `ON DELETE CASCADE` para territorialização.
- Índices de status, vigência, gravidade e tipo para consultas operacionais.

## 5. Tipos e domínio
- ENUM para estados de alerta e perfis de usuário.
- Campos textuais para descrição de risco e recomendações.
- Campos de data/hora para rastreabilidade temporal.
- Persistência de geometria em JSON textual (`area_geojson`/`poligono`).

## 6. Fonte de referência
- Schema validado em `public_html/banco_db/defesacivilpa.sql`.
