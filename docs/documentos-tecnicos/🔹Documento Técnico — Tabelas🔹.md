# 🔹Documento Técnico — Tabelas🔹

## `alertas`
Tabela principal de eventos. Armazena classificação do alerta, vigência, conteúdo operacional, área geográfica e metadados de integração.

## `alerta_municipios`
Tabela de associação alerta-município. Guarda municípios afetados por alerta para consulta territorial e indicadores.

## `alerta_regioes`
Tabela de associação alerta-região de integração. Suporta agregações regionais no painel e análises.

## `historico_usuarios`
Tabela de auditoria. Registra operação executada, usuário responsável, referência contextual, timestamp e metadados de origem.

## `municipios_regioes_pa`
Catálogo mestre de municípios do Pará e sua região de integração. Base de normalização territorial.

## `usuarios`
Tabela de autenticação/autorização. Armazena identidade, credencial hash, perfil funcional e status da conta.

## Observação
O modelo atual contém foco operacional e auditável, com `alertas` como núcleo e tabelas satélite para territorialização e trilha de ações.
