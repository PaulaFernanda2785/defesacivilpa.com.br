# Janela de Manutencao - SQL Analises (2026-03-25)

Roteiro operacional com ordem de comandos para producao e rollback em ate 5 minutos.

## 1) Preparacao rapida (antes da janela)

```bash
export SSH_HOST="<host_producao>"
export SSH_USER="<usuario_ssh>"
export APP_ROOT="/home/<usuario_ssh>/domains/defesacivilpa.com.br"
export PUBLIC_DIR="$APP_ROOT/public_html"
export RELEASE_TAG="sql_analises_2026_03_25"
```

## 2) Entrar no servidor e definir variaveis

```bash
ssh ${SSH_USER}@${SSH_HOST}
export APP_ROOT="/home/<usuario_ssh>/domains/defesacivilpa.com.br"
export PUBLIC_DIR="$APP_ROOT/public_html"
export RELEASE_TAG="sql_analises_2026_03_25"
export TS="$(date +%Y%m%d_%H%M%S)"
cd "$APP_ROOT"
mkdir -p backups/deploy
```

## 3) Abrir janela de manutencao

Acao operacional: ativar modo manutencao no painel da hospedagem (ou no balanceador/CDN usado em producao).

## 4) Backup de arquivos e banco (obrigatorio)

```bash
tar -czf "backups/deploy/${TS}_public_html_pre_${RELEASE_TAG}.tar.gz" public_html
```

```bash
export DB_HOST="localhost"
export DB_NAME="<database>"
export DB_USER="<db_user>"
read -s -p "DB password: " DB_PASS; echo
mysqldump --single-transaction --routines --triggers \
  -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
  > "backups/deploy/${TS}_db_pre_${RELEASE_TAG}.sql"

ls -lh "backups/deploy/${TS}_public_html_pre_${RELEASE_TAG}.tar.gz" \
       "backups/deploy/${TS}_db_pre_${RELEASE_TAG}.sql"
```

## 5) Publicar arquivos da release

Opcao A (git no servidor):

```bash
cd "$APP_ROOT"
git pull --ff-only
```

Opcao B (sem git no servidor, via upload de pacote/rsync):

```bash
# Executar da maquina de deploy:
# rsync -avz --progress <arquivos_da_release> ${SSH_USER}@${SSH_HOST}:${APP_ROOT}/
```

## 6) Aplicar indices SQL da otimizacao

```bash
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
  < "$PUBLIC_DIR/banco_db/2026_03_25_otimizacao_indices_analise_temporal.sql"
```

## 7) Validacao tecnica imediata

```bash
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
SHOW INDEX FROM alertas
WHERE Key_name IN ('idx_alertas_data_alerta', 'idx_alertas_status_data_alerta_tipo_evento');
SHOW INDEX FROM alerta_municipios
WHERE Key_name IN ('idx_alerta_municipios_alerta_municipio', 'idx_alerta_municipios_municipio_alerta');
SHOW INDEX FROM municipios_regioes_pa
WHERE Key_name IN ('idx_municipios_regiao_municipio_cod');
"
```

```bash
php -v >/dev/null 2>&1 || true
curl -I https://defesacivilpa.com.br/pages/analises/temporal.php
curl -I "https://defesacivilpa.com.br/api/mapa/kpis.php?data_inicio=2026-01-01&data_fim=2026-03-31"
```

## 8) Fechar janela

Acao operacional: desativar modo manutencao no painel da hospedagem.

## 9) Rollback em ate 5 minutos

Use exatamente esta ordem:

```bash
cd "$APP_ROOT"
export DB_HOST="localhost"
export DB_NAME="<database>"
export DB_USER="<db_user>"
read -s -p "DB password: " DB_PASS; echo

export FILES_BACKUP="$(ls -1t backups/deploy/*_public_html_pre_${RELEASE_TAG}.tar.gz | head -n1)"
export DB_BACKUP="$(ls -1t backups/deploy/*_db_pre_${RELEASE_TAG}.sql | head -n1)"
echo "$FILES_BACKUP"
echo "$DB_BACKUP"
```

```bash
# 1) Restaurar arquivos
tar -xzf "$FILES_BACKUP" -C "$APP_ROOT"
```

```bash
# 2) Restaurar banco
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
  < "$DB_BACKUP"
```

```bash
# 3) Validacao minima
curl -I https://defesacivilpa.com.br/pages/analises/temporal.php
curl -I https://defesacivilpa.com.br/api/mapa/kpis.php
```

Checklist de rollback (5 min):

- Confirmar retorno HTTP 200 nas rotas acima.
- Confirmar abertura da pagina Temporal com filtros.
- Confirmar KPI e mapa respondendo JSON.
- Encerrar incidente e registrar horario de rollback.
