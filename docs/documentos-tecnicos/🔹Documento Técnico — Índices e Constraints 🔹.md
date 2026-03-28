# 🔹Documento Técnico — Índices e Constraints 🔹

## 1. Índices e unicidade por tabela

### `alertas`
- PK: `id`
- Unique: `uk_alertas_inmet_url (inmet_url)`
- Unique: `uk_alerta_inmet (inmet_id)`
- Unique: `uk_inmet_id (inmet_id)`
- Índice: `idx_alertas_status (status)`
- Índice: `idx_alertas_vigencia (inicio_alerta, fim_alerta)`
- Índice: `idx_alertas_gravidade (nivel_gravidade)`
- Índice: `idx_alertas_tipo (tipo_evento)`

### `alerta_municipios`
- PK: `id`
- Índice: `alerta_id`

### `alerta_regioes`
- PK: `id`
- Índice: `alerta_id`

### `historico_usuarios`
- PK: `id`
- Unique: `hash_acao`
- Índice: `idx_data_hora (data_hora)`
- Índice: `idx_usuario (usuario_id)`
- Índice: `idx_acao (acao_codigo)`

### `municipios_regioes_pa`
- PK: `cod_ibge`

### `usuarios`
- PK: `id`
- Unique: `email`

## 2. Constraints de integridade
- FK `alerta_municipios_ibfk_1` com `ON DELETE CASCADE`.
- FK `alerta_regioes_ibfk_1` com `ON DELETE CASCADE`.
- CHECK em `alertas.poligono`: `json_valid(poligono)`.

## 3. Constraints de domínio (ENUM)
- `alertas.status`
- `alertas.fonte`
- `alertas.nivel_gravidade`
- `alertas.area_origem`
- `usuarios.perfil`
- `usuarios.status`

## 4. Observação técnica
Existe redundância de unicidade para `inmet_id` em `alertas` (duas unique keys). A manutenção pode ser simplificada com revisão de DDL em janela controlada.
