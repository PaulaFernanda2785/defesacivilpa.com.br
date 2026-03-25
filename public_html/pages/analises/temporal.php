<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Session.php';
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Core/AppConfig.php';
require_once __DIR__ . '/../../app/Helpers/SecurityHeaders.php';
require_once __DIR__ . '/../../app/Helpers/TimeHelper.php';
require_once __DIR__ . '/../../app/Services/AnaliseTemporalService.php';

$modoEmbedPublico = isset($_GET['embed']) && $_GET['embed'] === '1';
$appConfig = AppConfig::get();

$versionBase = rawurlencode((string) ($appConfig['version'] ?? '1.0.0'));
$versionCssTemporal = $versionBase . '.' . ((int) @filemtime(__DIR__ . '/../../assets/css/pages/analises-temporal.css'));
$versionJsTemporal = $versionBase . '.' . ((int) @filemtime(__DIR__ . '/../../assets/js/pages/analises-temporal.js'));
$versionJsCommon = $versionBase . '.' . ((int) @filemtime(__DIR__ . '/../../assets/js/pages/analises-common.js'));
$versionChartLite = $versionBase . '.' . ((int) @filemtime(__DIR__ . '/../../assets/vendor/chartjs/chart-lite.js'));

TimeHelper::bootstrap();
Session::start();
SecurityHeaders::applyHtmlPage();
$cspNonce = '';

if ($modoEmbedPublico) {
    $cspNonce = SecurityHeaders::scriptNonce();
    SecurityHeaders::applyPublicCsp([
        'include_nonce' => true,
        'style_src' => ["'self'", "'unsafe-inline'"],
        'img_src' => ["'self'", 'data:', 'blob:'],
        'script_src' => ["'self'"],
        'frame_src' => ["'self'"],
        'frame_ancestors' => ["'self'"],
    ]);
}

if (!$modoEmbedPublico) {
    Protect::check(['ADMIN', 'GESTOR', 'ANALISTA', 'OPERACOES']);
}

$db = Database::getConnection();
$usuario = $_SESSION['usuario'] ?? [];
$anoAtual = TimeHelper::currentYear();

$meses = [
    1 => 'Janeiro',
    2 => 'Fevereiro',
    3 => 'Marco',
    4 => 'Abril',
    5 => 'Maio',
    6 => 'Junho',
    7 => 'Julho',
    8 => 'Agosto',
    9 => 'Setembro',
    10 => 'Outubro',
    11 => 'Novembro',
    12 => 'Dezembro',
];

$filtroAno = isset($_GET['ano']) && $_GET['ano'] !== '' ? (int) $_GET['ano'] : $anoAtual;
$filtroMes = isset($_GET['mes']) && $_GET['mes'] !== '' ? (int) $_GET['mes'] : null;
$filtroRegiao = trim((string) ($_GET['regiao'] ?? ''));
$filtroMunicipio = trim((string) ($_GET['municipio'] ?? ''));
$eventoSelecionado = trim((string) ($_GET['evento'] ?? ''));

if ($filtroMes !== null && ($filtroMes < 1 || $filtroMes > 12)) {
    $filtroMes = null;
}

$regioes = $db->query("
    SELECT DISTINCT regiao_integracao
    FROM municipios_regioes_pa
    WHERE regiao_integracao IS NOT NULL
      AND regiao_integracao <> ''
      AND regiao_integracao <> 'regiao_integracao'
    ORDER BY regiao_integracao
")->fetchAll(PDO::FETCH_COLUMN);

if ($filtroRegiao === '') {
    $filtroMunicipio = '';
}

if ($filtroMunicipio !== '' && $filtroRegiao !== '') {
    $stmtMunicipioValido = $db->prepare("
        SELECT 1
        FROM municipios_regioes_pa
        WHERE municipio = ?
          AND regiao_integracao = ?
        LIMIT 1
    ");
    $stmtMunicipioValido->execute([$filtroMunicipio, $filtroRegiao]);

    if (!$stmtMunicipioValido->fetch()) {
        $filtroMunicipio = '';
    }
}

$eventosDisponiveis = AnaliseTemporalService::listaEventos();

if ($eventoSelecionado !== '' && !in_array($eventoSelecionado, $eventosDisponiveis, true)) {
    $eventoSelecionado = '';
}

$filtroTemporal = [
    'ano' => $filtroAno,
    'mes' => $filtroMes,
    'regiao' => $filtroRegiao !== '' ? $filtroRegiao : null,
    'municipio' => $filtroMunicipio !== '' ? $filtroMunicipio : null,
];

$sazonalidadeEvento = $eventoSelecionado !== ''
    ? AnaliseTemporalService::sazonalidadeMensalPorEvento($filtroTemporal, $eventoSelecionado)
    : [];

$sazonalidade = AnaliseTemporalService::sazonalidadeMensal($filtroTemporal);
$frequenciaHora = AnaliseTemporalService::frequenciaPorHora($filtroTemporal);
$dadosMultiEvento = AnaliseTemporalService::sazonalidadeMensalMultiEvento($filtroTemporal);
$evolucaoAnual = AnaliseTemporalService::evolucaoAnual($filtroTemporal);
$dadosEvolucaoAnual = AnaliseTemporalService::evolucaoAnualPorEvento($filtroTemporal);
$canceladosPorAno = AnaliseTemporalService::canceladosPorAno($filtroTemporal);

$totalAlertasRecorte = array_sum(array_map('intval', array_values($sazonalidade)));

$sazonalidadeOrdenada = $sazonalidade;
arsort($sazonalidadeOrdenada);
$mesPico = $sazonalidadeOrdenada !== [] ? (string) array_key_first($sazonalidadeOrdenada) : 'Sem dados';
$mesPicoTotal = $sazonalidadeOrdenada !== [] ? (int) current($sazonalidadeOrdenada) : 0;

$frequenciaOrdenada = $frequenciaHora;
arsort($frequenciaOrdenada);
$periodoPico = $frequenciaOrdenada !== [] ? (string) array_key_first($frequenciaOrdenada) : 'Sem dados';
$periodoPicoTotal = $frequenciaOrdenada !== [] ? (int) current($frequenciaOrdenada) : 0;

$totaisPorEvento = [];
foreach ($dadosMultiEvento as $evento => $serie) {
    $totaisPorEvento[(string) $evento] = array_sum(array_map('intval', array_values((array) $serie)));
}
arsort($totaisPorEvento);
$eventoLider = $eventoSelecionado !== ''
    ? $eventoSelecionado
    : ($totaisPorEvento !== [] ? (string) array_key_first($totaisPorEvento) : 'Sem dados');
$eventoLiderTotal = $eventoSelecionado !== ''
    ? array_sum(array_map('intval', array_values((array) $sazonalidadeEvento)))
    : ($totaisPorEvento !== [] ? (int) current($totaisPorEvento) : 0);

$evolucaoOrdenada = $evolucaoAnual;
arsort($evolucaoOrdenada);
$anoPico = $evolucaoOrdenada !== [] ? (string) array_key_first($evolucaoOrdenada) : 'Sem dados';
$anoPicoTotal = $evolucaoOrdenada !== [] ? (int) current($evolucaoOrdenada) : 0;

$filtrosResumo = [
    'Ano: ' . $filtroTemporal['ano'],
];

if ($filtroTemporal['mes'] !== null && isset($meses[$filtroTemporal['mes']])) {
    $filtrosResumo[] = 'Mes: ' . $meses[$filtroTemporal['mes']];
}

if ($filtroTemporal['regiao'] !== null) {
    $filtrosResumo[] = 'Regiao: ' . $filtroTemporal['regiao'];
}

if ($filtroTemporal['municipio'] !== null) {
    $filtrosResumo[] = 'Municipio: ' . $filtroTemporal['municipio'];
}

if ($eventoSelecionado !== '') {
    $filtrosResumo[] = 'Evento: ' . $eventoSelecionado;
}

$contextoPeriodo = implode(' | ', $filtrosResumo);
$contextoTerritorial = $filtroTemporal['municipio']
    ?? $filtroTemporal['regiao']
    ?? 'Estado do Para';
$quantidadeEventosComparados = count($dadosMultiEvento);
$quantidadeAnosComparados = count($evolucaoAnual);
$quantidadeSeriesAnuais = count($dadosEvolucaoAnual);
$operadorNome = trim((string) ($usuario['nome'] ?? ($modoEmbedPublico ? 'Visitante publico' : 'Nao identificado')));
$operadorPerfil = trim((string) ($usuario['perfil'] ?? ($modoEmbedPublico ? 'Publico' : 'Nao informado')));
$resumoExecutivo = [
    [
        'label' => 'Alertas no recorte',
        'value' => (int) $totalAlertasRecorte . ' alertas',
        'note' => 'Volume consolidado para o recorte temporal e territorial selecionado.',
        'tone' => 'primary',
    ],
    [
        'label' => 'Pico sazonal',
        'value' => $mesPico,
        'note' => $mesPicoTotal > 0
            ? $mesPicoTotal . ' alertas no mes mais critico do recorte.'
            : 'Sem pico sazonal identificado no recorte atual.',
        'tone' => 'success',
    ],
    [
        'label' => 'Turno predominante',
        'value' => $periodoPico,
        'note' => $periodoPicoTotal > 0
            ? $periodoPicoTotal . ' ocorrencias na faixa horaria dominante.'
            : 'Sem concentracao por periodo do dia no recorte.',
        'tone' => 'neutral',
    ],
    [
        'label' => 'Base comparativa',
        'value' => (int) $quantidadeEventosComparados . ' eventos / ' . (int) $quantidadeAnosComparados . ' anos',
        'note' => $contextoTerritorial,
        'tone' => 'warning',
    ],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Analise Temporal de Alertas</title>
<link rel="icon" type="image/x-icon" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/logo.cbmpa.ico">

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/painel.css">
<link rel="stylesheet" href="/assets/css/pages/alertas-form.css">
<link rel="stylesheet" href="/assets/css/pages/usuarios-listar.css">
<link rel="stylesheet" href="/assets/css/pages/analises-temporal.css?v=<?= htmlspecialchars($versionCssTemporal, ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="/assets/css/pages/analises-embed.css">
</head>

<body class="<?= $modoEmbedPublico ? 'analise-embed-body' : '' ?>">
<?php if ($modoEmbedPublico): ?>
    <main class="analise-embed-shell">
<?php else: ?>
<div class="layout">
    <?php include __DIR__ . '/../_sidebar.php'; ?>

    <main class="content">
        <?php include __DIR__ . '/../_topbar.php'; ?>

        <?php
        $breadcrumb = [
            'Painel' => '/pages/painel.php',
            'Analise temporal' => null,
        ];
        include __DIR__ . '/../_breadcrumb.php';
        ?>
<?php endif; ?>

        <section class="dashboard alerta-form-shell usuarios-shell temporal-shell">
            <div class="usuarios-hero-grid temporal-hero-grid">
                <div class="alerta-form-hero usuarios-hero-panel temporal-hero-panel">
                    <div class="alerta-form-lead usuarios-hero-copy temporal-hero-copy">
                        <span class="alerta-form-kicker">Analise operacional</span>
                        <h1 class="alerta-form-title">Analise temporal de alertas</h1>
                        <p class="alerta-form-description">
                            Organize a leitura de sazonalidade, recorrencia por periodo do dia e evolucao historica
                            dos alertas com o mesmo layout novo aplicado na central de analises multirriscos.
                        </p>

                        <div class="usuarios-hero-chip-row temporal-hero-chip-row">
                            <span class="usuarios-hero-chip"><?= (int) $totalAlertasRecorte ?> alertas no recorte</span>
                            <span class="usuarios-hero-chip"><?= htmlspecialchars((string) $contextoTerritorial, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="usuarios-hero-chip"><?= htmlspecialchars($contextoPeriodo, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>

                        <div class="usuarios-hero-actions temporal-hero-actions">
                            <a href="#temporal-filtros" class="btn btn-primary">Aplicar filtros</a>
                            <a href="#temporal-graficos" class="btn btn-secondary">Ver graficos</a>
                        </div>
                    </div>
                </div>

                <div class="usuarios-summary-grid temporal-summary-grid">
                    <?php foreach ($resumoExecutivo as $cardResumo): ?>
                        <article class="usuarios-summary-card usuarios-summary-card-<?= htmlspecialchars((string) ($cardResumo['tone'] ?? 'primary'), ENT_QUOTES, 'UTF-8') ?>">
                            <span class="usuarios-summary-label"><?= htmlspecialchars((string) ($cardResumo['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            <strong class="usuarios-summary-value"><?= htmlspecialchars((string) ($cardResumo['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                            <span class="usuarios-summary-note"><?= htmlspecialchars((string) ($cardResumo['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>

                <aside class="usuarios-command-card temporal-command-card">
                    <span class="usuarios-command-kicker">Comando temporal</span>
                    <h2>Coordenacao da leitura cronologica</h2>
                    <p>
                        Use este painel para alinhar operador, foco territorial e prioridade de leitura antes de navegar
                        pelos graficos de sazonalidade e evolucao historica.
                    </p>

                    <div class="usuarios-command-grid temporal-command-grid">
                        <article class="usuarios-command-item">
                            <span>Operador da sessao</span>
                            <strong><?= htmlspecialchars($operadorNome, ENT_QUOTES, 'UTF-8') ?></strong>
                            <small>Perfil atual: <?= htmlspecialchars($operadorPerfil, ENT_QUOTES, 'UTF-8') ?>.</small>
                        </article>

                        <article class="usuarios-command-item">
                            <span>Foco territorial</span>
                            <strong><?= htmlspecialchars((string) $contextoTerritorial, ENT_QUOTES, 'UTF-8') ?></strong>
                            <small>Recorte ativo para todos os graficos desta analise.</small>
                        </article>

                        <article class="usuarios-command-item">
                            <span>Prioridade sugerida</span>
                            <strong>Do pico ao historico</strong>
                            <small>Comece por sazonalidade e depois valide tendencia anual por evento.</small>
                        </article>
                    </div>
                </aside>
            </div>

            <div class="alerta-form-panel usuarios-control-panel temporal-control-panel">
                <div class="usuarios-control-grid temporal-overview-grid">
                    <section id="temporal-filtros" class="alerta-form-section usuarios-filter-panel temporal-filter-panel">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Secao 1</span>
                            <h2 class="alerta-section-title">Filtros do recorte temporal</h2>
                            <p class="alerta-section-text">
                                Ajuste ano, mes, regiao, municipio e evento para recalcular a leitura temporal e manter
                                a comparacao historica coerente com o recorte selecionado.
                            </p>
                        </header>

                        <form method="get" class="usuarios-filters temporal-filters temporal-filter-grid">
                            <div class="form-group">
                                <label for="ano">Ano</label>
                                <select id="ano" name="ano" data-auto-submit>
                                    <?php for ($ano = $anoAtual; $ano >= 2025; $ano--): ?>
                                        <option value="<?= $ano ?>" <?= $filtroTemporal['ano'] === $ano ? 'selected' : '' ?>>
                                            <?= $ano ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="mes">Mes</label>
                                <select id="mes" name="mes" data-auto-submit>
                                    <option value="">Todos</option>
                                    <?php foreach ($meses as $numeroMes => $nomeMes): ?>
                                        <option value="<?= $numeroMes ?>" <?= $filtroTemporal['mes'] === $numeroMes ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($nomeMes, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="filtro-regiao">Regiao de integracao</label>
                                <select id="filtro-regiao" name="regiao" data-auto-submit>
                                    <option value="">Todas</option>
                                    <?php foreach ($regioes as $regiao): ?>
                                        <option value="<?= htmlspecialchars((string) $regiao, ENT_QUOTES, 'UTF-8') ?>" <?= $filtroTemporal['regiao'] === $regiao ? 'selected' : '' ?>>
                                            <?= htmlspecialchars((string) $regiao, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="filtro-municipio">Municipio</label>
                                <select id="filtro-municipio" name="municipio" disabled>
                                    <option value=""><?= $filtroTemporal['regiao'] !== null ? 'Carregando municipios...' : 'Selecione uma regiao' ?></option>
                                </select>
                            </div>

                            <div class="form-group field-span-2">
                                <label for="evento">Tipo de evento</label>
                                <select id="evento" name="evento" data-auto-submit>
                                    <option value="">Todos</option>
                                    <?php foreach ($eventosDisponiveis as $evento): ?>
                                        <option value="<?= htmlspecialchars((string) $evento, ENT_QUOTES, 'UTF-8') ?>" <?= $eventoSelecionado === $evento ? 'selected' : '' ?>>
                                            <?= htmlspecialchars((string) $evento, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="usuarios-filter-meta temporal-filter-meta form-group field-span-2">
                                <span class="usuarios-filter-meta-label">Recorte ativo</span>
                                <div class="usuarios-filter-pill-row">
                                    <?php foreach ($filtrosResumo as $filtroAtivo): ?>
                                        <span class="usuarios-filter-pill"><?= htmlspecialchars((string) $filtroAtivo, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="alerta-callout temporal-filter-callout form-group field-span-2">
                                <strong>Atualizacao imediata</strong>
                                Ano, mes, regiao e evento reaplicam o recorte automaticamente. O municipio fica disponivel somente
                                apos a selecao da regiao correspondente.
                            </div>

                            <div class="alerta-form-actions temporal-filter-actions form-group field-span-2">
                                <div class="alerta-form-actions-left">
                                    <span class="alerta-inline-note">Filtros aplicados: <?= htmlspecialchars($contextoPeriodo, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>

                                <div class="alerta-form-actions-right">
                                    <button type="button" class="btn btn-secondary" data-clear-filtros>Limpar filtros</button>
                                </div>
                            </div>
                        </form>
                    </section>

                    <section class="alerta-form-section usuarios-governance-panel temporal-governance-panel">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Secao 2</span>
                            <h2 class="alerta-section-title">Leituras rapidas do periodo</h2>
                            <p class="alerta-section-text">
                                Use os indicadores abaixo para identificar picos sazonais, turno mais frequente,
                                evento dominante e referencia historica do recorte atual.
                            </p>
                        </header>

                        <div class="usuarios-insight-grid temporal-insight-grid">
                            <article class="usuarios-insight-card usuarios-insight-card-emphasis temporal-mini-card">
                                <span class="usuarios-insight-kicker">Mes com maior volume</span>
                                <strong><?= htmlspecialchars($mesPico, ENT_QUOTES, 'UTF-8') ?></strong>
                                <p>
                                    <?= $mesPicoTotal > 0 ? $mesPicoTotal . ' alertas registrados neste mes do recorte.' : 'Sem pico sazonal identificado no recorte atual.' ?>
                                </p>
                            </article>

                            <article class="usuarios-insight-card temporal-mini-card">
                                <span class="usuarios-insight-kicker">Periodo do dia predominante</span>
                                <strong><?= htmlspecialchars($periodoPico, ENT_QUOTES, 'UTF-8') ?></strong>
                                <p>
                                    <?= $periodoPicoTotal > 0 ? $periodoPicoTotal . ' alertas iniciados nesta faixa horaria.' : 'Sem concentracao horaria identificada.' ?>
                                </p>
                            </article>

                            <article class="usuarios-insight-card temporal-mini-card">
                                <span class="usuarios-insight-kicker">Evento dominante</span>
                                <strong><?= htmlspecialchars($eventoLider, ENT_QUOTES, 'UTF-8') ?></strong>
                                <p>
                                    <?= $eventoLiderTotal > 0 ? $eventoLiderTotal . ' ocorrencias acumuladas neste recorte temporal.' : 'Sem evento dominante identificado.' ?>
                                </p>
                            </article>

                            <article class="usuarios-insight-card temporal-mini-card">
                                <span class="usuarios-insight-kicker">Ano historicamente mais ativo</span>
                                <strong><?= htmlspecialchars($anoPico, ENT_QUOTES, 'UTF-8') ?></strong>
                                <p>
                                    <?= $anoPicoTotal > 0 ? $anoPicoTotal . ' alertas somados na comparacao anual filtrada.' : 'Sem historico suficiente para comparacao anual.' ?>
                                </p>
                            </article>
                        </div>

                        <div class="alerta-callout temporal-info-callout">
                            <strong>Como interpretar</strong>
                            Relacione a sazonalidade mensal com o periodo do dia e a evolucao historica para antecipar janelas de maior pressao operacional.
                        </div>

                        <div class="alerta-form-actions temporal-actions usuarios-filter-actions">
                            <div class="alerta-form-actions-left">
                                <span class="alerta-inline-note">Os paineis abaixo se mantem sincronizados com o mesmo recorte temporal, territorial e tipologico.</span>
                            </div>

                            <div class="alerta-form-actions-right temporal-action-buttons">
                                <a href="/pages/analises/tipologia.php" class="btn btn-secondary">Abrir tipologia</a>
                                <a href="/pages/mapas/mapa_multirriscos.php" class="btn btn-secondary">Abrir mapa multirriscos</a>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <section id="temporal-graficos" class="alerta-form-panel usuarios-table-panel temporal-chart-panel">
                <header class="usuarios-table-head temporal-section-head">
                    <div class="alerta-section-header">
                        <span class="alerta-section-kicker">Painel principal</span>
                        <h2 class="alerta-section-title">Sazonalidade mensal do recorte</h2>
                        <p class="alerta-section-text">
                            Observe como os alertas se distribuem ao longo dos meses dentro do recorte principal definido na tela.
                        </p>
                    </div>

                    <div class="usuarios-table-head-actions">
                        <span class="usuarios-result-chip"><?= (int) $filtroTemporal['ano'] ?> como ano-base</span>
                    </div>
                </header>

                <div class="usuarios-table-toolbar temporal-chart-toolbar">
                    <div class="usuarios-table-toolbar-copy">
                        <strong>Recorte temporal:</strong>
                        <span><?= htmlspecialchars($contextoPeriodo, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <div class="usuarios-table-toolbar-pills">
                        <span class="usuarios-toolbar-pill"><?= $filtroTemporal['mes'] !== null ? htmlspecialchars($meses[$filtroTemporal['mes']], ENT_QUOTES, 'UTF-8') : 'Todos os meses' ?></span>
                        <span class="usuarios-toolbar-pill"><?= (int) $quantidadeEventosComparados ?> eventos</span>
                    </div>
                </div>

                <div class="temporal-chart-card">
                    <div class="temporal-chart-stage">
                        <canvas class="temporal-chart-canvas" id="graficoSazonalidade"></canvas>
                    </div>
                </div>
            </section>

            <div class="alerta-form-panel usuarios-control-panel temporal-chart-panel">
                <div class="temporal-analytics-grid">
                    <section class="alerta-form-section temporal-chart-section">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Secao 4</span>
                            <h2 class="alerta-section-title">Comparativo mensal de eventos</h2>
                            <p class="alerta-section-text">
                                Compare o comportamento mensal das principais tipologias dentro do recorte selecionado.
                            </p>
                        </header>

                        <div class="temporal-chart-card temporal-chart-card--tall">
                            <div class="temporal-chart-meta">
                                <span class="temporal-chart-chip"><?= (int) $quantidadeEventosComparados ?> eventos comparados</span>
                                <span class="temporal-chart-chip">Leitura sazonal por tipologia</span>
                            </div>
                            <div class="temporal-chart-stage temporal-chart-stage--tall">
                                <canvas class="temporal-chart-canvas" id="graficoMultiEvento"></canvas>
                            </div>
                        </div>
                    </section>

                    <section class="alerta-form-section temporal-chart-section">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Secao 5</span>
                            <h2 class="alerta-section-title">Sazonalidade do evento selecionado</h2>
                            <p class="alerta-section-text">
                                Quando um evento for escolhido no filtro, o grafico ao lado mostra a distribuicao mensal desse tipo de alerta no recorte atual.
                            </p>
                        </header>

                        <?php if ($eventoSelecionado !== ''): ?>
                            <div class="temporal-chart-card">
                                <div class="temporal-chart-meta">
                                    <span class="temporal-chart-chip"><?= htmlspecialchars($eventoSelecionado, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="temporal-chart-chip"><?= (int) $eventoLiderTotal ?> registros no recorte</span>
                                </div>
                                <div class="temporal-chart-stage">
                                    <canvas class="temporal-chart-canvas" id="graficoEventoMensal"></canvas>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="temporal-chart-card temporal-empty-state">
                                <strong>Nenhum evento selecionado</strong>
                                <p>Escolha um tipo de evento nos filtros para habilitar a leitura mensal dedicada dessa tipologia.</p>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            </div>

            <div class="alerta-form-panel usuarios-control-panel temporal-chart-panel">
                <div class="temporal-analytics-grid">
                    <section class="alerta-form-section temporal-chart-section">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Secao 6</span>
                            <h2 class="alerta-section-title">Evolucao anual de alertas</h2>
                            <p class="alerta-section-text">
                                Compare a variacao anual do volume de alertas preservando os filtros de mes, regiao e municipio quando aplicados.
                            </p>
                        </header>

                        <div class="temporal-chart-card">
                            <div class="temporal-chart-meta">
                                <span class="temporal-chart-chip"><?= (int) $quantidadeAnosComparados ?> anos comparados</span>
                                <span class="temporal-chart-chip">Historico consolidado</span>
                            </div>
                            <div class="temporal-chart-stage">
                                <canvas class="temporal-chart-canvas" id="graficoEvolucao"></canvas>
                            </div>
                        </div>
                    </section>

                    <section class="alerta-form-section temporal-chart-section">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Secao 7</span>
                            <h2 class="alerta-section-title">Evolucao anual por tipo de evento</h2>
                            <p class="alerta-section-text">
                                Veja como cada tipologia evolui ao longo do historico filtrado para reconhecer tendencias de medio prazo.
                            </p>
                        </header>

                        <div class="temporal-chart-card temporal-chart-card--tall">
                            <div class="temporal-chart-meta">
                                <span class="temporal-chart-chip"><?= (int) $quantidadeSeriesAnuais ?> series anuais</span>
                                <span class="temporal-chart-chip">Comparacao por tipologia</span>
                            </div>
                            <div class="temporal-chart-stage temporal-chart-stage--tall">
                                <canvas class="temporal-chart-canvas" id="graficoEvolucaoAnual"></canvas>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div class="alerta-form-panel usuarios-control-panel temporal-chart-panel">
                <div class="temporal-analytics-grid">
                    <section class="alerta-form-section temporal-chart-section">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Secao 8</span>
                            <h2 class="alerta-section-title">Alertas cancelados por ano</h2>
                            <p class="alerta-section-text">
                                Acompanhe o historico de cancelamentos dentro do mesmo recorte territorial e mensal aplicado a esta tela.
                            </p>
                        </header>

                        <div class="temporal-chart-card">
                            <div class="temporal-chart-meta">
                                <span class="temporal-chart-chip"><?= count($canceladosPorAno) ?> anos com cancelamentos</span>
                                <span class="temporal-chart-chip">Indicador de revisao operacional</span>
                            </div>
                            <div class="temporal-chart-stage">
                                <canvas class="temporal-chart-canvas" id="graficoCancelados"></canvas>
                            </div>
                        </div>
                    </section>

                    <section class="alerta-form-section temporal-chart-section">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Secao 9</span>
                            <h2 class="alerta-section-title">Frequencia por periodo do dia</h2>
                            <p class="alerta-section-text">
                                Identifique em que faixa horaria os alertas costumam iniciar para apoiar o planejamento operacional.
                            </p>
                        </header>

                        <div class="temporal-chart-card">
                            <div class="temporal-chart-meta">
                                <span class="temporal-chart-chip"><?= count($frequenciaHora) ?> faixas horarias</span>
                                <span class="temporal-chart-chip">Distribuicao de inicio dos alertas</span>
                            </div>
                            <div class="temporal-chart-stage">
                                <canvas class="temporal-chart-canvas" id="graficoHora"></canvas>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </section>

        <?php if (!$modoEmbedPublico): ?>
            <?php include __DIR__ . '/../_footer.php'; ?>
        <?php endif; ?>
<?php if ($modoEmbedPublico): ?>
    </main>
<?php else: ?>
    </main>
</div>
<?php endif; ?>

<script src="/assets/vendor/chartjs/chart-lite.js?v=<?= htmlspecialchars($versionChartLite, ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="/assets/js/pages/analises-common.js?v=<?= htmlspecialchars($versionJsCommon, ENT_QUOTES, 'UTF-8') ?>"></script>
<script nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>" id="analise-temporal-data" type="application/json"><?= json_encode([
    'sazonalidadeLabels' => array_keys($sazonalidade ?? []),
    'sazonalidadeValores' => array_values($sazonalidade ?? []),
    'evolucaoLabels' => array_keys($evolucaoAnual ?? []),
    'evolucaoValores' => array_values($evolucaoAnual ?? []),
    'horaLabels' => array_keys($frequenciaHora ?? []),
    'horaValores' => array_values($frequenciaHora ?? []),
    'eventoMensalLabels' => $eventoSelecionado !== '' ? array_keys($sazonalidadeEvento ?? []) : [],
    'eventoMensalValores' => $eventoSelecionado !== '' ? array_values($sazonalidadeEvento ?? []) : [],
    'dadosMultiEvento' => $dadosMultiEvento ?? [],
    'dadosEvolucaoAnual' => $dadosEvolucaoAnual ?? [],
    'canceladosLabels' => array_keys($canceladosPorAno ?? []),
    'canceladosValores' => array_values($canceladosPorAno ?? []),
    'municipioAtual' => $filtroTemporal['municipio'] ?? '',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="/assets/js/pages/analises-temporal.js?v=<?= htmlspecialchars($versionJsTemporal, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
