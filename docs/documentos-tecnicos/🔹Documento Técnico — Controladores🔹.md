# 🔹Documento Técnico — Controladores🔹

## 1. Definição adotada
No projeto atual, os controladores são scripts PHP de entrada em `pages/*` e `api/*`.

## 2. Controladores web por módulo

### Alertas (`pages/alertas`)
- `listar.php`: lista e filtra alertas; exporta CSV.
- `cadastrar.php`: formulário de criação manual.
- `salvar.php`: persistência de alerta manual.
- `editar.php` / `atualizar.php`: edição e atualização.
- `detalhe.php`: visualização completa do alerta.
- `encerrar_alerta.php`: encerramento/cancelamento.
- `importar_inmet.php`: entrada da URL oficial.
- `preview_inmet.php`: validação e prévia da importação.
- `salvar_inmet.php`: confirmação de importação.
- `salvar_mapa.php`: grava mapa PNG do alerta.
- `baixar_pdf.php` / `pdf.php`: geração e download de PDF.
- `kml.php`: exportação KML.
- `territorio_alerta.php`: consulta territorial do alerta.
- `territorio_preview.php`: prévia territorial em tempo real.

### Análises (`pages/analises`)
- `index.php`: hub analítico.
- `temporal.php`, `severidade.php`, `tipologia.php`, `indice_risco.php`: painéis especializados.
- `api/analise_global.php`: consolidação analítica em JSON.
- `api/filtros_base.php`: insumos de filtros.
- `ajax_municipios.php`: municípios por região.
- `pdf/relatorio_analitico.php`: PDF consolidado.

### Outros domínios
- `pages/painel.php`: dashboard operacional.
- `pages/mapas/mapa_multirriscos.php`: visualização territorial integrada.
- `pages/historico/index.php` e `pages/historico/relatorio_pdf.php`: auditoria e relatório.
- `pages/usuarios/*`: gestão administrativa de usuários e senha.

## 3. Controladores de API (`public_html/api`)
- `api/mapa/alertas_ativos.php`: geojson e metadados de alertas.
- `api/mapa/kpis.php`: indicadores do recorte ativo.
- `api/mapa/linha_tempo_pressao.php`: série temporal de pressão.
- `api/mapa/municipios_pressao.php`: ranking municipal.
- `api/mapa/regioes_pressao.php`: ranking regional.
- `api/mapa/compdec.php`: catálogo COMPDEC por CSV externo.
- `api/ia/consultar.php`: assistente operacional orientado a contexto.
- `api/alertas/enviar_alerta.php`: acionamento de envio por e-mail.

## 4. Padrão de controlador observado
1. `require_once` de dependências.
2. `Protect::check` quando rota protegida.
3. Validação de método/entrada.
4. Execução de service e persistência.
5. Resposta com redirect (web) ou JSON (api).
