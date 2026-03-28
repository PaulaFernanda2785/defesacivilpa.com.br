# 🔹 Documento Técnico — Arquitetura do Projeto🔹

## 1. Visão arquitetural
A solução adota arquitetura **modular em PHP por scripts de entrada**, com separação de responsabilidades em camadas utilitárias e de serviço.

## 2. Macrocomponentes
- **Camada de apresentação**
  - `public_html/pages/*`: telas HTML/PHP por módulo.
  - `public_html/assets/*`: CSS, JS, imagens e dependências front-end.
- **Camada de API**
  - `public_html/api/*`: endpoints JSON para mapa, IA operacional e envio.
- **Camada de aplicação/domínio**
  - `public_html/app/Core/*`: segurança, sessão, conexão e bootstrap lógico.
  - `public_html/app/Helpers/*`: validações e funções transversais.
  - `public_html/app/Services/*`: regras de negócio e relatórios.
- **Persistência**
  - MariaDB com modelo relacional e tabelas de alertas, usuários e histórico.

## 3. Fluxo de requisição
1. Requisição chega em `pages/*` ou `api/*`.
2. `Protect::check` valida autenticação, sessão e perfil.
3. Entrada é validada por helpers/form rules.
4. Service executa regra de negócio.
5. Persistência ocorre via PDO no banco.
6. Resposta retorna como HTML renderizado ou JSON.

## 4. Padrões adotados
- Segurança por padrão em métodos sensíveis (`POST/PUT/PATCH/DELETE`).
- Uso predominante de SQL parametrizado.
- Composição de layout por componentes compartilhados (`_sidebar`, `_topbar`, `_breadcrumb`, `_footer`).
- Serviços especializados por domínio (alerta, território, análises, PDF, histórico).

## 5. Dependências centrais
- PHPMailer (envio de e-mails).
- Dompdf (relatórios PDF).
- Leaflet + OSM (mapas).
- Fontes externas front-end via `unpkg` quando aplicável.

## 6. Data de referência
- Arquitetura consolidada em **28/03/2026**.
