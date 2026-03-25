# Fase 01 - Wamp e Modernizacao Segura

## Estado atual

- Banco local espelhado no Wamp a partir do dump oficial.
- Compatibilidade do dump ajustada no import local para trocar `utf8mb4_uca1400_ai_ci` por `utf8mb4_unicode_ci`.
- Base PHP validada com `php -l` em todos os arquivos customizados.

## Melhorias aplicadas nesta fase

- Endurecimento de sessao com cookie `HttpOnly`, `SameSite=Lax` e regeneracao de ID no login.
- Camada CSRF adicionada para formularios e chamadas JavaScript mutaveis.
- Upload de imagem e KML centralizado em helper com validacao de tamanho e conteudo.
- Correcao do fluxo de cadastro e edicao de alertas com normalizacao de datas.
- Correcao do formulario de edicao de alerta para `datetime-local`.
- Remocao de `display_errors` no logout.
- Protecao basica de diretivas e arquivos sensiveis via `.htaccess`.

## Fase 02 - Segredos, UTF-8 e shell compartilhado

- Credenciais sairam do `public_html/config/database.php` e passaram a carregar a partir do `.env` raiz.
- O arquivo `public_html/.env` foi saneado para virar apenas modelo legado, sem segredos reais.
- A senha do usuario MySQL local foi rotacionada no Wamp e a antiga deixou de funcionar no ambiente local.
- O script `storage/database/setup_local_database.ps1` passou a gerar dump normalizado em UTF-8 antes da importacao, evitando perda de acentos por pipeline do PowerShell.
- A base local foi recriada e validada com amostras reais (`Lobao`, `Franca`, `Marajo`) ja restauradas corretamente em UTF-8.
- O endpoint `api/ia/consultar.php` agora exige `POST`, sessao valida, perfil autorizado e token CSRF.
- A troca de status de usuarios deixou de usar `GET` e agora opera apenas por formulario `POST`.
- Sidebar, footer e shell visual base foram redesenhados com configuracao central em `AppConfig`.

## Fase 03 - Shell responsivo e organizacao de assets

- O shell compartilhado passou a ter menu hamburguer, backdrop e navegacao lateral responsiva em `public_html/assets/css/app-shell.css`.
- O `base.css` passou a importar o shell dedicado, reduzindo dependencia de estilos misturados em paginas antigas.
- O `_topbar.php` foi propagado para os modulos principais, removendo repeticao de cabecalho manual.
- CSS inline ja extraido continua centralizado em `public_html/assets/css/pages/`.
- JS inline foi removido das paginas `painel.php`, `alertas/detalhe.php`, `alertas/editar.php`, `alertas/preview_inmet.php`, `analises/indice_risco.php`, `analises/temporal.php`, `analises/severidade.php` e `analises/tipologia.php`.
- Os scripts por pagina agora ficam organizados em `public_html/assets/js/pages/`.
- Emojis foram retirados da interface visivel do sistema para padronizar linguagem institucional e reduzir ruido visual.
- A navegacao principal e os componentes compartilhados agora respeitam comportamento mobile antes do pacote de retorno para a Hostinger.

## Fase 04 - Formularios de alerta, KML e gravidade extrema

- As paginas `alertas/cadastrar.php` e `alertas/editar.php` passaram a usar um formulario em duas secoes com layout responsivo e blocos mais profissionais.
- O upload da imagem informativa agora aceita arrastar, colar e selecionar, com preview visual antes do envio.
- O KML deixou de depender da lib externa fragil no navegador e passou a ser lido por parser proprio do formulario, com suporte a geometrias de area em KML valido.
- A primeira secao do formulario agora mostra automaticamente as regioes de integracao e os municipios afetados quando a area e desenhada no mapa ou carregada por KML.
- A fonte `MANUAL` saiu da selecao operacional do formulario e a validacao backend deixou de assumir esse valor como fallback silencioso.
- O nivel de gravidade `EXTREMO` foi incorporado como quinto nivel no formulario, filtros, analises, mapas e PDF com cor roxa `#7A28C6`, preservando `MUITO ALTO`.
- A migracao de banco para adicionar `EXTREMO` sem remover `MUITO ALTO` foi documentada em `docs/MIGRACAO_NIVEL_GRAVIDADE_EXTREMO.md`.

## Riscos ainda identificados

- A senha SMTP de producao continua dependendo de rotacao no painel externo da Hostinger; localmente ela foi apenas retirada da area publica.
- Ainda existem atributos `onclick` legados em paginas antigas; o JS principal saiu do inline, mas a camada final de comportamento ainda pode ser refinada em componentes desacoplados.
- Vale revisar paginas menos usadas e relatorios auxiliares para ampliar a padronizacao visual no mesmo padrao do novo shell.

## Proxima fase sugerida

1. Rotacionar no painel da Hostinger as credenciais SMTP e atualizar apenas o `.env` privado do ambiente.
2. Revisar os `onclick` remanescentes e padronizar eventos com arquivos JS dedicados quando fizer sentido.
3. Padronizar formularios, tabelas e breadcrumbs restantes sobre o novo shell visual.
4. Separar melhor camadas de controller, service e validacao.
5. Preparar pacote de deploy limpo para Hostinger com checklist de publicacao e validacao de UTF-8.
