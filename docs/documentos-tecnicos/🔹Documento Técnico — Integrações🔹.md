# 🔹Documento Técnico — Integrações🔹

## 1. Integrações externas efetivas

### INMET (alertas oficiais)
- Consumo de URL oficial de aviso.
- Parser CAP/XML para dados de evento, severidade, vigência e polígono.
- Uso operacional na importação de alertas.

### Google Sheets (base COMPDEC)
- Leitura CSV publicado de planilha externa.
- Uso para catálogo de contatos de defesa civil municipal.
- Consumido em `api/mapa/compdec.php` e `AlertaEnvioService`.

### SMTP
- Envio de e-mails via PHPMailer.
- Credenciais carregadas de `.env` por `app/config/email.php`.

### OpenStreetMap tiles
- Camada de mapa base no front-end via Leaflet.

### Recursos front-end CDN
- `unpkg` para Leaflet CSS/JS em páginas específicas.

## 2. Integrações internas
- Integração entre `pages/*` e `app/Services/*`.
- Integração entre `api/*` e serviços analíticos/territoriais.
- Integração de layout via includes compartilhados (`_sidebar`, `_topbar`, `_footer`).

## 3. Observações
- O endpoint `api/ia/consultar.php` opera com contexto e consultas locais do banco.
- Não foi identificado cliente HTTP interno em uso ativo para provedores de IA externos na base atual.
