# 🔹 DOCUMENTO TÉCNICO — ETAPA 1🔹

## 1. Objetivo da etapa
Estabelecer a base funcional mínima do sistema com autenticação, operação de alertas, visualização e auditoria inicial.

## 2. Entregas-base implementadas
- Estrutura do projeto com `public_html/app`, `pages`, `api`, `assets` e `storage`.
- Módulo de login/logout com controle de sessão.
- Cadastros e gestão de alertas (criar, editar, listar, detalhar).
- Territorialização do alerta em municípios e regiões.
- Página de painel operacional com indicadores iniciais.
- Exportações essenciais: PDF e KML de alerta.
- Trilha de histórico de ações de usuários.

## 3. Componentes estruturantes da etapa
- **Core**: `Session`, `Auth`, `Protect`, `Csrf`, `Database`, `Env`.
- **Helpers**: validação de formulário, segurança de cabeçalhos, tempo e upload.
- **Services**: alertas, território, histórico e PDF.

## 4. Regras funcionais já consolidadas
- Perfis de acesso com restrições por módulo.
- Número de alerta sequencial por ano operacional.
- Persistência de área geográfica (`area_geojson`) e origem (`DESENHO`/`KML`).
- Registro de ações críticas no histórico.

## 5. Resultado da etapa
A etapa 1 entregou uma **base funcional operacional** para evolução incremental dos módulos de mapa, análises e integrações externas.

## 6. Referência
- Data de referência: **2026-03-28**.
