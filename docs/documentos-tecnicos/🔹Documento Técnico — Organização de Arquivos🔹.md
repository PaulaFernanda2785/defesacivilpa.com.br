# 🔹Documento Técnico — Organização de Arquivos🔹

## 1. Convenção geral
- **Entrada web**: scripts em `pages/*`.
- **Entrada API**: scripts em `api/*`.
- **Negócio reutilizável**: classes em `app/Services/*`.
- **Validação/apoio**: classes em `app/Helpers/*`.
- **Infra de execução**: classes em `app/Core/*`.

## 2. Regra de responsabilidade
- Arquivos de entrada devem:
  - validar método HTTP,
  - validar acesso/perfil,
  - normalizar entrada,
  - delegar para service.
- Services devem:
  - concentrar regra de negócio,
  - evitar acoplamento com HTML,
  - retornar estruturas simples (array/bool/string).

## 3. Organização de front-end
- `assets/css/pages`: estilo por página/módulo.
- `assets/js/pages`: script por tela com comportamento específico.
- `assets/js` raiz: scripts compartilhados (ex.: shell e componentes globais).

## 4. Organização por domínio
- `pages/alertas`: ciclo completo de vida do alerta.
- `pages/analises`: módulos analíticos e relatório consolidado.
- `pages/historico`: auditoria operacional.
- `pages/usuarios`: gestão de conta e administração.

## 5. Organização de dados e artefatos
- `uploads/informacoes`: anexos de imagem.
- `uploads/kml`: arquivos KML enviados.
- `storage/mapas`: PNG gerado para PDF de alerta.
- `storage/cache/pdf`: cache de relatórios.

## 6. Diretriz de manutenção
- Novas funcionalidades devem entrar no domínio correto.
- Evitar lógica de negócio extensa diretamente em views.
- Preferir naming consistente com o verbo de ação (`salvar`, `atualizar`, `listar`, `detalhe`).
