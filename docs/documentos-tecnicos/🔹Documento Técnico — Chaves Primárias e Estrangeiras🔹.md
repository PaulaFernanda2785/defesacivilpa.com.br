# 🔹Documento Técnico — Chaves Primárias e Estrangeiras🔹

## 1. Chaves primárias (PK)
- `alertas.id`
- `alerta_municipios.id`
- `alerta_regioes.id`
- `historico_usuarios.id`
- `municipios_regioes_pa.cod_ibge`
- `usuarios.id`

## 2. Chaves estrangeiras (FK)
- `alerta_municipios.alerta_id` → `alertas.id` (`ON DELETE CASCADE`)
- `alerta_regioes.alerta_id` → `alertas.id` (`ON DELETE CASCADE`)

## 3. Relações sem FK declarada
- `historico_usuarios.usuario_id` (relacionamento lógico com `usuarios.id`).
- `alertas.usuario_id` e `alertas.usuario_cancelamento` (relacionamento lógico com `usuarios.id`).
- `alerta_municipios.municipio_codigo` (relacionamento lógico com `municipios_regioes_pa.cod_ibge`).

## 4. Observação
O modelo físico garante integridade forte nas tabelas satélite de territorialização de alerta. Relações de usuário e catálogo territorial ainda dependem de integridade em nível de aplicação.
