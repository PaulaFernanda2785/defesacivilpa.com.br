<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Helpers/AlertaFormHelper.php';

Protect::check(['ADMIN', 'GESTOR', 'ANALISTA', 'OPERACOES']);

$usuario = $_SESSION['usuario'];
$db = Database::getConnection();

$eventosDisponiveis = AlertaFormHelper::eventos();
$gravidadesDisponiveis = AlertaFormHelper::niveis();
$fontesDisponiveis = array_values(array_unique(array_merge(['MANUAL'], AlertaFormHelper::fontes())));
sort($fontesDisponiveis, SORT_NATURAL | SORT_FLAG_CASE);

$stmtTerritorios = $db->query("
    SELECT cod_ibge, municipio, regiao_integracao
    FROM municipios_regioes_pa
    WHERE cod_ibge IS NOT NULL
      AND cod_ibge <> ''
      AND municipio IS NOT NULL
      AND municipio <> ''
      AND regiao_integracao IS NOT NULL
      AND regiao_integracao <> ''
      AND regiao_integracao <> 'regiao_integracao'
    ORDER BY regiao_integracao, municipio
");

$regioesDisponiveis = [];
$municipiosDisponiveis = [];

foreach ($stmtTerritorios->fetchAll(PDO::FETCH_ASSOC) as $territorio) {
    $regiao = trim((string) ($territorio['regiao_integracao'] ?? ''));
    $municipio = trim((string) ($territorio['municipio'] ?? ''));
    $codIbge = trim((string) ($territorio['cod_ibge'] ?? ''));

    if ($regiao === '' || $municipio === '' || $codIbge === '') {
        continue;
    }

    $regioesDisponiveis[$regiao] = true;

    $municipiosDisponiveis[] = [
        'cod_ibge' => $codIbge,
        'municipio' => $municipio,
        'regiao' => $regiao,
    ];
}

$regioesDisponiveis = array_keys($regioesDisponiveis);
sort($regioesDisponiveis, SORT_NATURAL | SORT_FLAG_CASE);

$totalAtivos = (int) $db->query("
    SELECT COUNT(*)
    FROM alertas
    WHERE status = 'ATIVO'
")->fetchColumn();

$totalMunicipiosBase = count($municipiosDisponiveis);
$totalRegioesBase = count($regioesDisponiveis);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Mapa Multirriscos - Sistema Inteligente Multirriscos</title>
<link rel="icon" type="image/x-icon" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/logo.cbmpa.ico">

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/painel.css">
<link rel="stylesheet" href="/assets/css/pages/alertas-form.css">
<link rel="stylesheet" href="/assets/css/pages/usuarios-listar.css">
<link rel="stylesheet" href="/assets/css/pages/painel.css">
<link rel="stylesheet" href="/assets/css/mapa_multirriscos.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>

<body>
<div class="layout">
    <?php include __DIR__ . '/../_sidebar.php'; ?>

    <main class="content">
        <?php include __DIR__ . '/../_topbar.php'; ?>

        <?php
        $breadcrumb = [
            'Painel' => '/pages/painel.php',
            'Mapa multirriscos' => null,
        ];
        include __DIR__ . '/../_breadcrumb.php';
        ?>

        <section class="dashboard alerta-form-shell usuarios-shell multirrisco-shell">
            <div class="usuarios-hero-grid multirrisco-hero-grid">
                <div class="alerta-form-hero usuarios-hero-panel multirrisco-hero-panel">
                    <div class="alerta-form-lead usuarios-hero-copy multirrisco-hero-copy">
                        <span class="alerta-form-kicker">Monitoramento territorial</span>
                        <h1 class="alerta-form-title">Mapa multirriscos com foco territorial</h1>
                        <p class="alerta-form-description">
                            Consulte alertas ativos, destaque regiões e municípios no mapa, acompanhe a pressão de risco
                            por território e abra modais detalhados com o histórico ativo de cada recorte territorial.
                        </p>

                        <div class="usuarios-hero-chip-row multirrisco-hero-chip-row">
                            <span class="usuarios-hero-chip"><?= $totalAtivos ?> alertas ativos monitorados</span>
                            <span class="usuarios-hero-chip"><?= $totalMunicipiosBase ?> municípios na base territorial</span>
                            <span class="usuarios-hero-chip"><?= $totalRegioesBase ?> regiões de integração mapeadas</span>
                        </div>

                        <div class="usuarios-hero-actions multirrisco-hero-actions">
                            <a href="#multirrisco-filtros" class="btn btn-primary">Aplicar filtros</a>
                            <a href="#multirrisco-mapa" class="btn btn-secondary">Ir para o mapa</a>
                        </div>
                    </div>
                </div>

                <div class="usuarios-summary-grid multirrisco-summary-grid">
                    <article class="usuarios-summary-card usuarios-summary-card-primary">
                        <span class="usuarios-summary-label">Monitoramento ativo</span>
                        <strong class="usuarios-summary-value" id="hero-alertas-ativos"><?= $totalAtivos ?> alertas</strong>
                        <span class="usuarios-summary-note">
                            Total atual de alertas com status ativo disponíveis para leitura territorial.
                        </span>
                    </article>

                    <article class="usuarios-summary-card usuarios-summary-card-success">
                        <span class="usuarios-summary-label">Base territorial</span>
                        <strong class="usuarios-summary-value"><?= $totalMunicipiosBase ?> municípios</strong>
                        <span class="usuarios-summary-note">
                            Cobertura completa em <?= $totalRegioesBase ?> regiões de integração do estado.
                        </span>
                    </article>

                    <article class="usuarios-summary-card usuarios-summary-card-neutral">
                        <span class="usuarios-summary-label">Leitura executiva</span>
                        <strong class="usuarios-summary-value">Mapa + ranking + IRP</strong>
                        <span class="usuarios-summary-note">
                            Painel unificado para leitura operacional rápida e decisão territorial.
                        </span>
                    </article>

                    <article class="usuarios-summary-card usuarios-summary-card-warning">
                        <span class="usuarios-summary-label">Foco atual</span>
                        <strong class="usuarios-summary-value" id="hero-foco-value">Sem recorte territorial</strong>
                        <span class="usuarios-summary-note" id="hero-foco-note">
                            Selecione região, município ou clique no mapa para abrir o detalhamento operacional.
                        </span>
                    </article>
                </div>

                <aside class="usuarios-command-card multirrisco-command-card">
                    <span class="usuarios-command-kicker">Comando operacional</span>
                    <h2>Coordenação territorial do multirrisco</h2>
                    <p>
                        Este painel apoia a navegação do mapa com foco em resposta rápida, priorizando o território
                        que exige maior atenção operacional no momento.
                    </p>

                    <div class="usuarios-command-grid multirrisco-command-grid">
                        <article class="usuarios-command-item">
                            <span>Usuário responsável</span>
                            <strong><?= htmlspecialchars((string) ($usuario['nome'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
                            <small>Sessão com acesso para consultar, filtrar e detalhar o cenário territorial.</small>
                        </article>

                        <article class="usuarios-command-item">
                            <span>Carga atual monitorada</span>
                            <strong><?= $totalAtivos ?> alertas ativos</strong>
                            <small>Distribuídos em <?= $totalMunicipiosBase ?> municípios e <?= $totalRegioesBase ?> regiões.</small>
                        </article>

                        <article class="usuarios-command-item">
                            <span>Prioridade sugerida</span>
                            <strong>Aplicar recorte territorial</strong>
                            <small>
                                Filtre por região ou município para destacar o foco, abrir o modal e acelerar a leitura.
                            </small>
                        </article>
                    </div>
                </aside>
            </div>

            <div class="alerta-form-panel usuarios-control-panel multirrisco-control-panel" id="multirrisco-filtros">
                <div class="alerta-form-grid multirrisco-overview-grid">
                    <section class="alerta-form-section">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Seção 1</span>
                            <h2 class="alerta-section-title">Filtros e recorte territorial</h2>
                            <p class="alerta-section-text">
                                Os filtros abaixo sincronizam mapa, indicadores, ranking regional, gráfico de pressão
                                e modais territoriais.
                            </p>
                        </header>

                        <form id="multirrisco-form" class="multirrisco-filter-form">
                            <div class="multirrisco-filter-grid">
                                <div class="form-group">
                                    <label for="data_inicio">Período inicial</label>
                                    <input type="date" id="data_inicio" name="data_inicio">
                                </div>

                                <div class="form-group">
                                    <label for="data_fim">Período final</label>
                                    <input type="date" id="data_fim" name="data_fim">
                                </div>

                                <div class="form-group">
                                    <label for="tipo_evento">Tipo de evento</label>
                                    <select id="tipo_evento" name="tipo_evento">
                                        <option value="">Todos os eventos</option>
                                        <?php foreach ($eventosDisponiveis as $evento): ?>
                                            <option value="<?= htmlspecialchars($evento, ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($evento, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="gravidade">Gravidade</label>
                                    <select id="gravidade" name="gravidade">
                                        <option value="">Todas as gravidades</option>
                                        <?php foreach ($gravidadesDisponiveis as $gravidade): ?>
                                            <option value="<?= htmlspecialchars($gravidade, ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($gravidade, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="fonte">Fonte</label>
                                    <select id="fonte" name="fonte">
                                        <option value="">Todas as fontes</option>
                                        <?php foreach ($fontesDisponiveis as $fonte): ?>
                                            <option value="<?= htmlspecialchars($fonte, ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($fonte, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="regiao">Região</label>
                                    <select id="regiao" name="regiao">
                                        <option value="">Todas as regiões</option>
                                        <?php foreach ($regioesDisponiveis as $regiao): ?>
                                            <option value="<?= htmlspecialchars($regiao, ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($regiao, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="municipio">Município</label>
                                    <select id="municipio" name="municipio">
                                        <option value="">Todos os municípios</option>
                                    </select>
                                    <span class="field-helper">
                                        Ao selecionar uma região, a lista de municípios é filtrada automaticamente.
                                    </span>
                                </div>
                            </div>

                            <div class="alerta-form-actions multirrisco-filter-actions">
                                <div class="alerta-form-actions-left">
                                    <span class="alerta-inline-note" id="resumo-filtros">
                                        Filtros ativos: sem recorte adicional.
                                    </span>
                                </div>

                                <div class="alerta-form-actions-right">
                                    <button type="button" class="btn btn-secondary" id="btnLimparFiltros">
                                        Limpar filtros
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        Aplicar filtros
                                    </button>
                                    <button type="button" class="btn btn-secondary" id="btnAbrirAjuda">
                                        Como usar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </section>

                    <section class="alerta-form-section">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Seção 2</span>
                            <h2 class="alerta-section-title">Leitura operacional</h2>
                            <p class="alerta-section-text">
                                O painel ao lado resume a carga territorial ativa e ajuda a direcionar a leitura do mapa.
                            </p>
                        </header>

                        <div class="painel-kpi-grid multirrisco-kpi-grid">
                            <article class="painel-kpi-card is-active">
                                <span class="painel-kpi-label">Alertas ativos</span>
                                <strong class="painel-kpi-value" id="kpi-ativos">-</strong>
                                <span class="painel-kpi-note">Total de alertas no recorte territorial atual.</span>
                            </article>

                            <article class="painel-kpi-card">
                                <span class="painel-kpi-label">Municípios em risco</span>
                                <strong class="painel-kpi-value" id="kpi-municipios">-</strong>
                                <span class="painel-kpi-note">Municípios com alertas ativos considerando os filtros.</span>
                            </article>

                            <article class="painel-kpi-card is-neutral">
                                <span class="painel-kpi-label">Regiões afetadas</span>
                                <strong class="painel-kpi-value" id="kpi-regioes">-</strong>
                                <span class="painel-kpi-note">Regiões de integração atingidas pelo recorte consultado.</span>
                            </article>

                            <article class="painel-kpi-card is-warning multirrisco-focus-card">
                                <span class="painel-kpi-label">Território em foco</span>
                                <strong class="painel-kpi-value" id="foco-territorial-titulo">Nenhum foco definido</strong>
                                <span class="painel-kpi-note" id="foco-territorial-texto">
                                    Clique em um município ou região para abrir o modal detalhado com os alertas ativos.
                                </span>
                            </article>
                        </div>

                        <div class="alerta-callout multirrisco-callout">
                            <strong>Experiência recomendada</strong>
                            Comece pelo filtro territorial. Ao escolher uma região, o contorno regional ganha destaque no
                            mapa; ao escolher um município, o recorte municipal passa a ser o foco principal da tela.
                        </div>
                    </section>
                </div>
            </div>

            <section class="alerta-form-panel multirrisco-map-panel" id="multirrisco-mapa">
                <header class="painel-section-head">
                    <div class="alerta-section-header">
                        <span class="alerta-section-kicker">Seção 3</span>
                        <h2 class="alerta-section-title">Mapa territorial e pressão ativa</h2>
                        <p class="alerta-section-text">
                            O mapa é o elemento central da página. Use camadas territoriais, alertas e o ranking regional
                            para navegar rapidamente pelo cenário multirrisco.
                        </p>
                    </div>

                    <div class="painel-map-meta">
                        <span class="painel-chip" id="chip-modo-territorial">Modo: municípios</span>
                        <span class="painel-chip" id="chip-filtro-territorial">Sem recorte territorial</span>
                    </div>
                </header>

                <div class="multirrisco-map-layout">
                    <div class="map-card multirrisco-map-card">
                        <div class="map-card-header">
                            <div>
                                <span class="map-card-title">Mapa multirriscos do Pará</span>
                                <p class="map-card-text">
                                    Alertas ativos, pressão territorial, regiões de integração e municípios são atualizados
                                    juntos, com destaque automático a partir do filtro selecionado.
                                </p>
                            </div>

                            <div class="multirrisco-toolbar-status" id="status-atualizacao">
                                Pronto para consulta operacional
                            </div>
                        </div>

                        <div class="multirrisco-toolbar">
                            <div class="multirrisco-toolbar-group">
                                <span class="multirrisco-toolbar-label">Camadas</span>

                                <label class="multirrisco-toggle">
                                    <input type="checkbox" id="toggle-alertas" checked>
                                    <span>Alertas ativos</span>
                                </label>

                                <label class="multirrisco-toggle">
                                    <input type="checkbox" id="toggle-compdec">
                                    <span>DC municipais</span>
                                </label>
                            </div>

                            <div class="multirrisco-toolbar-group multirrisco-toolbar-group-segmented">
                                <span class="multirrisco-toolbar-label">Recorte territorial</span>

                                <label class="multirrisco-segment">
                                    <input type="radio" name="modoTerritorial" value="municipios" checked>
                                    <span>Municípios</span>
                                </label>

                                <label class="multirrisco-segment">
                                    <input type="radio" name="modoTerritorial" value="regioes">
                                    <span>Regiões</span>
                                </label>
                            </div>
                        </div>

                        <div class="multirrisco-map-stage">
                            <div id="mapa" class="alerta-map multirrisco-map-canvas"></div>
                            <div class="multirrisco-map-loading" id="mapaLoading" hidden>
                                Atualizando mapa e indicadores...
                            </div>
                        </div>
                    </div>

                    <aside class="multirrisco-side-stack">
                        <section class="alerta-form-section multirrisco-side-section">
                            <header class="alerta-section-header">
                                <span class="alerta-section-kicker">Ranking</span>
                                <h2 class="alerta-section-title">Regiões mais pressionadas</h2>
                                <p class="alerta-section-text">
                                    Clique em uma região da lista para centralizar o mapa e abrir o detalhamento regional.
                                </p>
                            </header>

                            <div id="lista-regioes" class="lista-regioes">
                                <div class="multirrisco-empty-box">Carregando leitura regional...</div>
                            </div>
                        </section>

                        <section class="alerta-form-section multirrisco-side-section">
                            <div id="filtro-dia-ativo" class="multirrisco-day-filter" hidden>
                                <span>
                                    Filtro ativo para o dia:
                                    <strong id="diaSelecionadoTxt"></strong>
                                </span>
                                <button type="button" class="btn btn-secondary" id="btnLimparFiltroDia">
                                    Limpar
                                </button>
                            </div>

                            <header class="alerta-section-header">
                                <span class="alerta-section-kicker">Série diária</span>
                                <h2 class="alerta-section-title">Evolução do IRP</h2>
                                <p class="alerta-section-text">
                                    Clique em um ponto do gráfico para filtrar o mapa e os modais por um dia específico.
                                </p>
                            </header>

                            <div class="grafico-container multirrisco-chart-container">
                                <canvas id="graficoLinhaTempo"></canvas>
                            </div>

                            <div class="alerta-form-actions multirrisco-chart-actions">
                                <div class="alerta-form-actions-left">
                                    <span class="alerta-inline-note">
                                        IRP diário = soma de (peso da gravidade × municípios afetados no recorte) para cada alerta ativo do dia.
                                    </span>
                                </div>

                                <div class="alerta-form-actions-right">
                                    <button type="button" class="btn btn-secondary" id="btnAbrirIRP">
                                        Entender o IRP
                                    </button>
                                    <button type="button" class="btn btn-secondary" id="btnAbrirValidacaoIRP">
                                        Ver validação IRP
                                    </button>
                                </div>
                            </div>
                        </section>
                    </aside>
                </div>
            </section>
        </section>

        <?php include __DIR__ . '/../_footer.php'; ?>
    </main>
</div>

<div id="modalTerritorio" class="modal-territorio" aria-hidden="true">
    <div class="modal-territorio-dialog" role="dialog" aria-modal="true" aria-labelledby="modalTerritorioTitulo">
        <div class="modal-territorio-header">
            <div class="modal-territorio-header-copy">
                <span class="modal-territorio-kicker" id="modalTerritorioKicker">Território</span>
                <h3 id="modalTerritorioTitulo">Detalhamento territorial</h3>
                <p id="modalTerritorioResumo">Carregando detalhamento do território selecionado.</p>
            </div>

            <button type="button" class="modal-territorio-close" data-close-territorio aria-label="Fechar modal">
                X
            </button>
        </div>

        <div class="modal-territorio-body" id="modalTerritorioBody"></div>
    </div>
</div>

<div id="modalIRP" class="modal">
    <div class="modal-conteudo">
        <h3>Como o IRP é calculado</h3>

        <p>
            O índice de pressão de risco mede a carga operacional causada pelos alertas ativos no território filtrado.
        </p>

        <p>
            Fórmula no recorte atual: <strong>Pontos IRP = peso da gravidade × municípios afetados no próprio recorte</strong>.
        </p>

        <ul>
            <li>Baixo = 1</li>
            <li>Moderado = 2</li>
            <li>Alto = 3</li>
            <li>Muito alto = 4</li>
            <li>Extremo = 5</li>
        </ul>

        <p>
            Exemplo regional: alerta <strong>ALTO</strong> em 4 municípios da região = <strong>3 × 4 = 12 pontos</strong>.
        </p>

        <p>
            Exemplo municipal: no filtro de um município, o mesmo alerta soma <strong>3 × 1 = 3 pontos</strong>.
        </p>

        <p>
            Quanto maior o IRP, maior a pressão territorial e a necessidade de resposta operacional.
        </p>

        <button type="button" data-close-irp>Fechar</button>
    </div>
</div>

<div id="modalValidacaoIRP" class="modal modal-validacao" aria-hidden="true">
    <div class="modal-conteudo modal-validacao-conteudo">
        <h3>Validação automática do IRP</h3>
        <p class="modal-validacao-meta" id="validacaoIrpMeta">Carregando relatório...</p>
        <div class="modal-validacao-resumo" id="validacaoIrpResumo"></div>
        <div class="modal-validacao-cenarios" id="validacaoIrpCenarios"></div>
        <div class="modal-validacao-tabela-wrap">
            <table class="modal-validacao-tabela" aria-label="Resultado detalhado da validação do IRP">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Cenário</th>
                        <th>Etapa</th>
                        <th>Detalhe</th>
                    </tr>
                </thead>
                <tbody id="validacaoIrpTabelaBody">
                    <tr>
                        <td colspan="4">Carregando resultados detalhados...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <details class="modal-validacao-raw">
            <summary>Ver markdown bruto</summary>
            <pre class="modal-validacao-markdown" id="validacaoIrpConteudo">Aguarde, estamos carregando o relatório mais recente.</pre>
        </details>
        <div class="modal-validacao-actions">
            <button type="button" data-close-validacao-irp>Fechar</button>
        </div>
    </div>
</div>

<div id="modalAjuda" class="modal-ajuda">
    <div class="modal-ajuda-conteudo">
        <div class="modal-ajuda-header">
            <div class="modal-ajuda-heading">
                <span class="modal-ajuda-kicker">Guia rápido</span>
                <h3>Como usar o mapa multirriscos</h3>
                <p>
                    Um roteiro simples para filtrar, interpretar o mapa e abrir o detalhamento territorial com mais
                    rapidez.
                </p>
            </div>
            <button type="button" data-close-ajuda aria-label="Fechar ajuda">X</button>
        </div>

        <div class="modal-ajuda-body">
            <section class="modal-ajuda-hero">
                <div class="modal-ajuda-hero-copy">
                    <strong>Leitura recomendada</strong>
                    <p>
                        Comece pelos filtros, use o mapa como referência central e abra os modais territoriais para
                        entender cada alerta ativo no contexto da região ou município.
                    </p>
                </div>

                <div class="modal-ajuda-pill-row">
                    <span class="modal-ajuda-pill">1. Filtre o cenário</span>
                    <span class="modal-ajuda-pill">2. Observe o destaque territorial</span>
                    <span class="modal-ajuda-pill">3. Abra o detalhamento</span>
                </div>
            </section>

            <div class="modal-ajuda-grid">
                <article class="modal-ajuda-card modal-ajuda-card-flow">
                    <span class="modal-ajuda-card-kicker">Passo a passo</span>
                    <h4>Fluxo ideal de uso</h4>
                    <ol class="modal-ajuda-sequencia">
                        <li>Defina período, evento, gravidade e fonte para reduzir o cenário.</li>
                        <li>Selecione uma região para destacar o contorno regional no mapa.</li>
                        <li>Selecione um município para mudar automaticamente o foco para o nível municipal.</li>
                        <li>Clique na região, município ou ranking para abrir o modal territorial completo.</li>
                    </ol>
                </article>

                <article class="modal-ajuda-card">
                    <span class="modal-ajuda-card-kicker">Detalhamento</span>
                    <h4>O que aparece nos modais territoriais</h4>
                    <ul class="modal-ajuda-lista">
                        <li>Nome do território consultado e pressão territorial acumulada.</li>
                        <li>Quantidade de alertas ativos e tipos de evento presentes no recorte.</li>
                        <li>Número do alerta, data, vigência, gravidade, pressão e fonte de cada alerta ativo.</li>
                        <li>Quando houver mais de um alerta ativo, cada alerta é exibido separadamente.</li>
                    </ul>
                </article>

                <article class="modal-ajuda-card">
                    <span class="modal-ajuda-card-kicker">Camadas</span>
                    <h4>Como navegar no mapa</h4>
                    <ul class="modal-ajuda-lista">
                        <li>O modo territorial alterna entre municípios e regiões.</li>
                        <li>A camada de alertas pode ser ligada ou desligada sem perder o recorte atual.</li>
                        <li>A camada de DC municipais fica disponível como apoio operacional complementar.</li>
                        <li>O ranking regional e o gráfico do IRP ajudam a encontrar rapidamente as áreas críticas.</li>
                    </ul>
                </article>
            </div>

            <section class="modal-ajuda-highlight">
                <div class="modal-ajuda-highlight-copy">
                    <span class="modal-ajuda-card-kicker">Leitura visual</span>
                    <h4>Como interpretar as cores no mapa</h4>
                    <p>
                        Quanto mais intensa a cor, maior a pressão operacional e a gravidade predominante no território.
                    </p>
                </div>

                <div class="modal-ajuda-legenda-grid">
                    <span class="modal-ajuda-legenda-item"><i class="legenda baixa"></i>Baixo</span>
                    <span class="modal-ajuda-legenda-item"><i class="legenda moderada"></i>Moderado</span>
                    <span class="modal-ajuda-legenda-item"><i class="legenda alta"></i>Alto</span>
                    <span class="modal-ajuda-legenda-item"><i class="legenda muito-alta"></i>Muito alto</span>
                    <span class="modal-ajuda-legenda-item"><i class="legenda extrema"></i>Extremo</span>
                </div>
            </section>
        </div>

        <div class="modal-ajuda-footer">
            <span class="modal-ajuda-footer-note">
                Dica: se quiser entender um território específico, filtre por região ou município antes de clicar no mapa.
            </span>
            <button type="button" data-close-ajuda>Fechar</button>
        </div>
    </div>
</div>

<div id="modalIA" class="modal-ia">
    <div class="modal-ia-conteudo">
        <div class="modal-ia-header">
            <div class="modal-ia-heading">
                <span class="modal-ia-kicker">Assistente operacional</span>
                <h3>IA Multirriscos</h3>
                <p>
                    Converse com o mapa, entenda o recorte atual e aplique ações diretamente na tela.
                </p>
            </div>
            <button type="button" onclick="fecharIA()" aria-label="Fechar assistente">X</button>
        </div>

        <div class="modal-ia-contexto" id="iaContextoResumo"></div>

        <div class="modal-ia-body">
            <section class="ia-sugestoes" id="iaSugestoes"></section>

            <div class="ia-conversa" id="iaMensagens">
                <article class="ia-msg ia-resposta ia-msg-apresentacao">
                    <div class="ia-msg-head">
                        <strong>Assistente operacional</strong>
                        <span>Pronto para apoiar a leitura do mapa</span>
                    </div>
                    <div class="ia-msg-body">
                        <p>
                            Posso resumir o recorte atual, identificar regiões ou municípios prioritários, aplicar foco
                            territorial, abrir o IRP e orientar sua navegação no mapa.
                        </p>
                    </div>
                </article>
            </div>
        </div>

        <div class="modal-ia-footer">
            <div class="modal-ia-input-shell">
                <textarea
                    id="iaPergunta"
                    rows="2"
                    placeholder="Pergunte algo como: qual região está mais pressionada no recorte atual?"
                ></textarea>

                <div class="modal-ia-footer-actions">
                    <button type="button" class="btn-ia-limpar" id="btnIANovaConversa">
                        Nova conversa
                    </button>
                    <button type="button" class="btn-ia-enviar" onclick="enviarPerguntaIA()">
                        Enviar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<button class="btn-ia" onclick="abrirIA()" aria-label="Abrir assistente multirriscos">IA</button>

<div id="drawer-compdec" class="drawer-compdec">
    <div class="drawer-header">
        <h3>Defesa Civil Municipal</h3>
        <button type="button" data-close-compdec aria-label="Fechar painel">X</button>
    </div>

    <div class="drawer-body" id="conteudo-compdec"></div>
</div>

<div id="overlay-compdec" class="overlay-compdec" data-close-compdec></div>

<script id="multirrisco-bootstrap" type="application/json"><?= json_encode([
    'regioes' => $regioesDisponiveis,
    'municipios' => $municipiosDisponiveis,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/assets/vendor/chartjs/chart-lite.js"></script>
<script src="/assets/js/assistente-ia.js"></script>
<script src="/assets/js/pages/mapas-mapa_multirriscos.js"></script>
</body>
</html>

