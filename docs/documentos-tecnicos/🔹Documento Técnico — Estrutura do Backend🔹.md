# 🔹Documento Técnico — Estrutura do Backend🔹

## 1. Runtime
- Linguagem: `PHP`.
- Banco: `MariaDB/MySQL` via `PDO`.
- Ambiente alvo: WAMP local e hospedagem web (Apache + `.htaccess`).

## 2. Núcleo de backend
- `app/Core/Session.php`: sessão, timeout e renovação.
- `app/Core/Auth.php`: login/logout e sessão do usuário.
- `app/Core/Csrf.php`: token CSRF + idempotência.
- `app/Core/Protect.php`: gatekeeper de rotas protegidas.
- `app/Core/Database.php`: conexão e configuração de PDO.

## 3. Serviços de domínio
- `AlertaService`, `AlertaEnvioService`.
- `InmetService`.
- `TerritorioService`.
- `HistoricoService`.
- `PdfService`, `PdfHistoricoService`, `RelatorioAnaliticoPdfService`.
- Serviços analíticos (`Analise*Service`).

## 4. Endpoints de API
- `api/mapa/*`: dados geográficos e KPIs do mapa.
- `api/ia/consultar.php`: assistente operacional baseado em contexto e SQL local.
- `api/alertas/enviar_alerta.php`: envio de alerta para COMPDEC.

## 5. Persistência e consistência
- Modelo relacional com chaves primárias e constraints básicas.
- Relacionamentos diretos com `alertas` em tabelas satélite.
- Histórico auditável por usuário/ação/data.

## 6. Segurança operacional do backend
- Autenticação por sessão.
- Controle de perfil por rota.
- CSRF obrigatório em operações mutáveis.
- Headers de segurança para HTML/JSON.

## 7. Característica estrutural
Backend orientado a scripts PHP com serviços compartilhados, adequado para manutenção incremental e evolução por módulos.
