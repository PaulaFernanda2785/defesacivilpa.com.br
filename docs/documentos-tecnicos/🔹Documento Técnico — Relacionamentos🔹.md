# 🔹Documento Técnico — Relacionamentos🔹

## 1. Relacionamentos físicos (com FK)

### `alertas` 1:N `alerta_municipios`
- Chave: `alerta_municipios.alerta_id` → `alertas.id`
- Política: `ON DELETE CASCADE`
- Efeito: ao remover alerta, remove vínculos municipais.

### `alertas` 1:N `alerta_regioes`
- Chave: `alerta_regioes.alerta_id` → `alertas.id`
- Política: `ON DELETE CASCADE`
- Efeito: ao remover alerta, remove vínculos regionais.

## 2. Relacionamentos lógicos (sem FK declarada)

### `usuarios` 1:N `historico_usuarios`
- Relação por `historico_usuarios.usuario_id`.
- Não há constraint física no dump atual.

### `usuarios` 1:N `alertas`
- Relação por `alertas.usuario_id` (autor) e `alertas.usuario_cancelamento`.
- Não há FK declarada no dump atual.

### `municipios_regioes_pa` 1:N `alerta_municipios`
- Relação por `cod_ibge` ↔ `municipio_codigo`.
- Usada para composição regional e filtros, sem FK física.

## 3. Cardinalidades operacionais
- Um alerta pode impactar muitos municípios.
- Um alerta pode impactar muitas regiões.
- Um usuário pode executar muitas ações de histórico.
- Um município pode aparecer em muitos alertas.

## 4. Implicações
- FKs existentes garantem integridade territorial mínima.
- Relações lógicas exigem disciplina de aplicação e validação por código.
