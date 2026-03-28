# 🔹Documento Técnico — Dicionário de Dados🔹

## 1. Tabela `alertas`

| Campo | Tipo | Descrição |
|---|---|---|
| id | int(11) | Identificador interno do alerta. |
| numero | varchar(20) | Número operacional (ex.: 001/2026). |
| status | enum | Situação (`ATIVO`, `ENCERRADO`, `CANCELADO`). |
| motivo_cancelamento | text | Justificativa de cancelamento. |
| data_cancelamento | datetime | Data/hora de cancelamento. |
| usuario_cancelamento | int(11) | Usuário que cancelou. |
| usuario_id | int(11) | Usuário que cadastrou/operou o alerta. |
| fonte | enum | Origem do alerta (`MANUAL`, `INMET`, etc.). |
| tipo_evento | varchar(100) | Tipo do evento monitorado. |
| nivel_gravidade | enum | Nível de gravidade do alerta. |
| data_alerta | date | Data oficial de referência do alerta. |
| inicio_alerta | datetime | Início da vigência. |
| fim_alerta | datetime | Fim da vigência. |
| data_encerramento | datetime | Data/hora de encerramento. |
| riscos | text | Descrição de riscos potenciais. |
| recomendacoes | text | Recomendações operacionais. |
| informacoes | varchar(255) | Caminho/metadata de informação adicional (imagem). |
| poligono | longtext | Geometria legada com validação JSON. |
| imagem_mapa | varchar(255) | Caminho da imagem de mapa gerada. |
| criado_em | datetime | Timestamp de criação. |
| inmet_url | varchar(255) | URL externa do alerta INMET. |
| inmet_id | varchar(50) | Identificador externo INMET. |
| area_geojson | longtext | Geometria principal em GeoJSON. |
| area_origem | enum | Origem da área (`DESENHO` ou `KML`). |
| kml_arquivo | varchar(255) | Caminho do arquivo KML associado. |
| alerta_enviado_compdec | tinyint(1) | Flag de envio para COMPDEC. |
| data_envio_compdec | datetime | Data/hora do envio para COMPDEC. |

## 2. Tabela `alerta_municipios`

| Campo | Tipo | Descrição |
|---|---|---|
| id | int(11) | Identificador do vínculo. |
| alerta_id | int(11) | Referência ao alerta. |
| municipio_codigo | varchar(10) | Código IBGE do município. |
| municipio_nome | varchar(150) | Nome do município afetado. |

## 3. Tabela `alerta_regioes`

| Campo | Tipo | Descrição |
|---|---|---|
| id | int(11) | Identificador do vínculo regional. |
| alerta_id | int(11) | Referência ao alerta. |
| regiao_integracao | varchar(100) | Região de integração impactada. |

## 4. Tabela `historico_usuarios`

| Campo | Tipo | Descrição |
|---|---|---|
| id | int(11) | Identificador do registro de auditoria. |
| usuario_id | int(11) | Usuário responsável pela ação. |
| usuario_nome | varchar(150) | Nome do usuário no momento da ação. |
| acao_codigo | varchar(50) | Código funcional da ação. |
| acao_descricao | text | Descrição textual da ação. |
| referencia | varchar(255) | Contexto resumido da operação. |
| data_hora | datetime | Data/hora da ação registrada. |
| ip_usuario | varchar(45) | IP de origem da operação. |
| user_agent | varchar(255) | Identificação do cliente HTTP. |
| hash_acao | char(64) | Hash para deduplicação/controle da trilha. |

## 5. Tabela `municipios_regioes_pa`

| Campo | Tipo | Descrição |
|---|---|---|
| cod_ibge | varchar(7) | Código IBGE do município. |
| municipio | varchar(150) | Nome do município. |
| regiao_integracao | varchar(100) | Região de integração associada. |

## 6. Tabela `usuarios`

| Campo | Tipo | Descrição |
|---|---|---|
| id | int(11) | Identificador do usuário. |
| nome | varchar(150) | Nome completo. |
| email | varchar(150) | E-mail de acesso. |
| senha_hash | varchar(255) | Hash da senha. |
| perfil | enum | Perfil funcional de acesso. |
| status | enum | Status da conta (`ATIVO`/`INATIVO`). |
| criado_em | datetime | Data/hora de criação da conta. |
