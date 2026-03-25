# Migracao do Nivel de Gravidade para EXTREMO

## Objetivo

Acrescentar o novo valor `EXTREMO` ao campo `alertas.nivel_gravidade`, preservando o valor existente `MUITO ALTO` e adicionando a nova cor roxa nas interfaces, mapas, legendas, filtros, PDF e analises.

## Ordem recomendada no servidor de origem

```sql
ALTER TABLE alertas
  MODIFY nivel_gravidade ENUM('BAIXO','MODERADO','ALTO','MUITO ALTO','EXTREMO') NOT NULL;
```

## Validacoes apos a migracao

```sql
SELECT nivel_gravidade, COUNT(*)
FROM alertas
GROUP BY nivel_gravidade
ORDER BY FIELD(nivel_gravidade, 'BAIXO', 'MODERADO', 'ALTO', 'MUITO ALTO', 'EXTREMO');
```

Resultado esperado:

- o banco aceita `BAIXO`, `MODERADO`, `ALTO`, `MUITO ALTO` e `EXTREMO`
- os registros existentes em `MUITO ALTO` permanecem intactos
- `EXTREMO` fica disponivel para novos alertas

## Observacoes operacionais

- O formulario local ja removeu `MANUAL` da selecao de fonte e passou a exigir uma fonte operacional valida.
- O sistema local ja trata `EXTREMO` como roxo (`#7A28C6`) nas visualizacoes.
- `MUITO ALTO` continua ativo no sistema com sua cor tradicional, e `EXTREMO` passa a coexistir como um quinto nivel.
