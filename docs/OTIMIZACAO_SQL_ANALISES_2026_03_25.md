# Otimizacao SQL - Analises (2026-03-25)

Este documento registra os comandos SQL para aplicar no servidor original (producao), com foco em desempenho dos filtros temporais e territoriais.

## Escopo

- Consultas de analise temporal/global e APIs de mapa/IA.
- Eliminacao de filtros com funcao sobre coluna indexada (`DATE(a.data_alerta)`, `YEAR(a.data_alerta)` no `WHERE`).
- Inclusao de indices para acelerar filtros por data/status/evento e relacoes territoriais.

## Arquivo SQL idempotente

Arquivo no projeto:

`public_html/banco_db/2026_03_25_otimizacao_indices_analise_temporal.sql`

Roteiro operacional de execucao em producao:

`docs/JANELA_MANUTENCAO_SQL_ANALISES_2026_03_25.md`

## Comandos para aplicar em producao

1. Fazer backup do banco antes da mudanca.

```sql
-- Executar no cliente mysql conectado no banco da aplicacao
-- (exemplo de backup via shell):
-- mysqldump -h <HOST> -u <USER> -p <DATABASE> > backup_pre_otimizacao_2026_03_25.sql
```

2. Aplicar o script de indices.

```sql
SOURCE /caminho/do/projeto/public_html/banco_db/2026_03_25_otimizacao_indices_analise_temporal.sql;
```

3. Validar se os indices existem.

```sql
SHOW INDEX FROM alertas
WHERE Key_name IN ('idx_alertas_data_alerta', 'idx_alertas_status_data_alerta_tipo_evento');

SHOW INDEX FROM alerta_municipios
WHERE Key_name IN ('idx_alerta_municipios_alerta_municipio', 'idx_alerta_municipios_municipio_alerta');

SHOW INDEX FROM municipios_regioes_pa
WHERE Key_name IN ('idx_municipios_regiao_municipio_cod');
```

4. Validar plano de execucao (EXPLAIN).

```sql
EXPLAIN
SELECT MONTH(a.data_alerta) mes, COUNT(*) total
FROM alertas a
WHERE a.data_alerta >= '2026-01-01'
  AND a.data_alerta < '2027-01-01'
  AND a.status IN ('ATIVO','ENCERRADO')
GROUP BY mes
ORDER BY mes;
```

## Alteracoes de codigo relacionadas

- `public_html/app/Services/AnaliseTemporalService.php`
- `public_html/app/Services/AnaliseGlobalService.php`
- `public_html/api/mapa/alertas_ativos.php`
- `public_html/api/mapa/kpis.php`
- `public_html/api/mapa/linha_tempo_pressao.php`
- `public_html/api/mapa/municipios_pressao.php`
- `public_html/api/mapa/regioes_pressao.php`
- `public_html/api/ia/consultar.php`

## Observacoes

- O script SQL e idempotente: pode ser executado novamente sem duplicar indice.
- `data_fim` nos filtros foi ajustada para limite exclusivo (`< dia_seguinte`), preservando o resultado esperado para filtros por data.
