# 🔹Documento Técnico — Modelagem🔹

## 1. Modelo conceitual do domínio

### Entidade: Alerta
Representa um evento monitorado com:
- identificação operacional (`numero`),
- classificação (`tipo_evento`, `nivel_gravidade`, `fonte`),
- vigência temporal,
- informações de risco/recomendação,
- cobertura territorial.

### Entidade: Território do alerta
- Municípios impactados (`alerta_municipios`).
- Regiões de integração impactadas (`alerta_regioes`).

### Entidade: Usuário
Operador autenticado com perfil e status.

### Entidade: Histórico
Registro de ação auditável com referência temporal e contexto.

### Entidade: Catálogo territorial
Tabela mestra de municípios e regiões (`municipios_regioes_pa`).

## 2. Invariantes de domínio
- Todo alerta possui `numero`, `status`, `tipo_evento`, `nivel_gravidade`, `data_alerta` e vigência.
- Perfil de usuário deve pertencer ao conjunto permitido.
- Status de usuário deve ser `ATIVO` ou `INATIVO`.
- Registros territoriais de alerta referenciam um alerta existente.

## 3. Regras operacionais associadas
- Um alerta pode afetar múltiplos municípios e regiões.
- Histórico é append-only para auditoria.
- Área de alerta pode ser originada por desenho ou KML.

## 4. Evolução esperada
O modelo permite expansão por novos tipos de fonte, métricas analíticas e integrações sem ruptura da entidade central `alertas`.
