<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Session.php';
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Core/AppConfig.php';
require_once __DIR__ . '/../../app/Helpers/SecurityHeaders.php';
require_once __DIR__ . '/../../app/Helpers/TimeHelper.php';
require_once __DIR__ . '/../../app/Services/AnaliseTipologiaService.php';

$modoEmbedPublico = isset($_GET['embed']) && $_GET['embed'] === '1';
$appConfig = AppConfig::get();

$versionBase = rawurlencode((string) ($appConfig['version'] ?? '1.0.0'));
$cssTipologiaPath = __DIR__ . '/../../assets/css/pages/analises-tipologia.css';
$jsTipologiaPath = __DIR__ . '/../../assets/js/pages/analises-tipologia.js';
$jsCommonPath = __DIR__ . '/../../assets/js/pages/analises-common.js';
$chartLitePath = __DIR__ . '/../../assets/vendor/chartjs/chart-lite.js';

$versionCssTipologia = $versionBase
    . '.' . ((int) @filemtime($cssTipologiaPath))
    . '.' . substr((string) @md5_file($cssTipologiaPath), 0, 8);
$versionJsTipologia = $versionBase
    . '.' . ((int) @filemtime($jsTipologiaPath))
    . '.' . substr((string) @md5_file($jsTipologiaPath), 0, 8);
$versionJsCommon = $versionBase
    . '.' . ((int) @filemtime($jsCommonPath))
    . '.' . substr((string) @md5_file($jsCommonPath), 0, 8);
$versionChartLite = $versionBase
    . '.' . ((int) @filemtime($chartLitePath))
    . '.' . substr((string) @md5_file($chartLitePath), 0, 8);

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
    3 => 'Março',
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

$parseIntFiltro = static function (string $valor): ?int {
    $valor = trim($valor);

    if ($valor === '' || !preg_match('/^\d{1,4}$/', $valor)) {
        return null;
    }

    return (int) $valor;
};

$filtroAno = $parseIntFiltro((string) ($_GET['ano'] ?? ''));
$filtroMes = $parseIntFiltro((string) ($_GET['mes'] ?? ''));
$filtroRegiao = trim((string) ($_GET['regiao'] ?? ''));
$filtroMunicipio = trim((string) ($_GET['municipio'] ?? ''));

if ($filtroAno !== null && ($filtroAno < 2025 || $filtroAno > $anoAtual)) {
    $filtroAno = null;
}

if ($filtroMes !== null && ($filtroMes < 1 || $filtroMes > 12)) {
    $filtroMes = null;
}

if (strlen($filtroRegiao) > 120) {
    $filtroRegiao = '';
}

if (strlen($filtroMunicipio) > 120) {
    $filtroMunicipio = '';
}

$regioes = $db->query("
    SELECT DISTINCT regiao_integracao
    FROM municipios_regioes_pa
    WHERE regiao_integracao IS NOT NULL
      AND regiao_integracao <> ''
      AND regiao_integracao <> 'regiao_integracao'
    ORDER BY regiao_integracao
")->fetchAll(PDO::FETCH_COLUMN);

if ($filtroRegiao !== '' && !in_array($filtroRegiao, array_map('strval', $regioes), true)) {
    $filtroRegiao = '';
    $filtroMunicipio = '';
}

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

$filtro = [
    'ano' => $filtroAno,
    'mes' => $filtroMes,
    'regiao' => $filtroRegiao !== '' ? $filtroRegiao : null,
    'municipio' => $filtroMunicipio !== '' ? $filtroMunicipio : null,
];

$dadosEventos = AnaliseTipologiaService::quantidadePorEvento($db, $filtro);
$labelsEventos = array_map('strval', array_column($dadosEventos, 'tipo_evento'));
$valoresEventos = array_map('intval', array_column($dadosEventos, 'total'));
$totalAlertasEventos = array_sum($valoresEventos);

$percentuaisEventos = array_map(function ($valor) use ($totalAlertasEventos) {
    return $totalAlertasEventos > 0
        ? round(($valor / $totalAlertasEventos) * 100, 1)
        : 0;
}, $valoresEventos);

$dadosCorrelacao = AnaliseTipologiaService::correlacaoEventoSeveridade($db, $filtro);
$eventos = [];
$severidades = ['BAIXO', 'MODERADO', 'ALTO', 'MUITO ALTO', 'EXTREMO'];
$mapaSeveridade = [];
$severidadesAtivas = [];

foreach ($dadosCorrelacao as $dado) {
    $evento = (string) $dado['tipo_evento'];
    $severidade = (string) $dado['nivel_gravidade'];
    $total = (int) $dado['total'];

    $eventos[$evento] = true;
    $mapaSeveridade[$severidade][$evento] = $total;
    $severidadesAtivas[$severidade] = true;
}

$labelsEventosCorrelacao = array_keys($eventos);
$datasetsSeveridade = [];

$coresSeveridade = [
    'BAIXO' => '#CCC9C7',
    'MODERADO' => '#FFE000',
    'ALTO' => '#FF7B00',
    'MUITO ALTO' => '#FF1D08',
    'EXTREMO' => '#7A28C6',
];

foreach ($severidades as $severidade) {
    $datasetsSeveridade[] = [
        'label' => $severidade,
        'data' => array_map(
            fn ($evento) => $mapaSeveridade[$severidade][$evento] ?? 0,
            $labelsEventosCorrelacao
        ),
        'backgroundColor' => $coresSeveridade[$severidade],
    ];
}

$dadosTipologiaRegiao = AnaliseTipologiaService::tipologiaPorRegiao($db, $filtro);
$regioesTipologia = [];
$eventosTipologia = [];
$mapaTipologiaRegiao = [];
$totaisPorRegiao = [];

foreach ($dadosTipologiaRegiao as $dado) {
    $regiao = (string) $dado['regiao_integracao'];
    $evento = (string) $dado['tipo_evento'];
    $total = (int) $dado['total'];

    $regioesTipologia[$regiao] = true;
    $eventosTipologia[$evento] = true;
    $mapaTipologiaRegiao[$evento][$regiao] = $total;
    $totaisPorRegiao[$regiao] = ($totaisPorRegiao[$regiao] ?? 0) + $total;
}

$labelsRegioes = array_keys($regioesTipologia);
$labelsEventosRegiao = array_keys($eventosTipologia);

$coresEventos = [
    '#0b3c68',
    '#2e7d32',
    '#f9a825',
    '#ef6c00',
    '#7a28c6',
    '#0277bd',
];

$datasetsRegiao = [];

foreach ($labelsEventosRegiao as $index => $evento) {
    $datasetsRegiao[] = [
        'label' => $evento,
        'data' => array_map(
            fn ($regiao) => $mapaTipologiaRegiao[$evento][$regiao] ?? 0,
            $labelsRegioes
        ),
        'backgroundColor' => $coresEventos[$index % count($coresEventos)],
    ];
}

$dadosTipologiaMunicipio = AnaliseTipologiaService::tipologiaPorMunicipio($db, $filtro);
$municipiosTipologia = [];
$eventosMunicipio = [];
$mapaTipologiaMunicipio = [];
$totaisPorMunicipio = [];

foreach ($dadosTipologiaMunicipio as $dado) {
    $municipio = (string) $dado['municipio'];
    $evento = (string) $dado['tipo_evento'];
    $total = (int) $dado['total'];

    $municipiosTipologia[$municipio] = true;
    $eventosMunicipio[$evento] = true;
    $mapaTipologiaMunicipio[$evento][$municipio] = $total;
    $totaisPorMunicipio[$municipio] = ($totaisPorMunicipio[$municipio] ?? 0) + $total;
}

$labelsEventosMunicipio = array_keys($eventosMunicipio);

arsort($totaisPorRegiao);
arsort($totaisPorMunicipio);

$exibirTopMunicipiosNacional = $filtro['regiao'] === null && $filtro['municipio'] === null;
$labelsMunicipios = array_keys($totaisPorMunicipio);

if ($exibirTopMunicipiosNacional) {
    $labelsMunicipios = array_slice($labelsMunicipios, 0, 10);
}

$datasetsMunicipio = [];

foreach ($labelsEventosMunicipio as $index => $evento) {
    $datasetsMunicipio[] = [
        'label' => $evento,
        'data' => array_map(
            fn ($municipio) => $mapaTipologiaMunicipio[$evento][$municipio] ?? 0,
            $labelsMunicipios
        ),
        'backgroundColor' => $coresEventos[$index % count($coresEventos)],
    ];
}

$eventoPrincipal = $dadosEventos[0]['tipo_evento'] ?? 'Sem dados';
$eventoPrincipalTotal = (int) ($dadosEventos[0]['total'] ?? 0);
$regiaoLider = $totaisPorRegiao !== [] ? (string) array_key_first($totaisPorRegiao) : 'Sem dados';
$regiaoLiderTotal = $totaisPorRegiao !== [] ? (int) current($totaisPorRegiao) : 0;
$municipioLider = $totaisPorMunicipio !== [] ? (string) array_key_first($totaisPorMunicipio) : 'Sem dados';
$municipioLiderTotal = $totaisPorMunicipio !== [] ? (int) current($totaisPorMunicipio) : 0;

$filtrosResumo = [];

if ($filtro['ano'] !== null) {
    $filtrosResumo[] = 'Ano: ' . $filtro['ano'];
}

if ($filtro['mes'] !== null && isset($meses[$filtro['mes']])) {
    $filtrosResumo[] = 'Mês: ' . $meses[$filtro['mes']];
}

if ($filtro['regiao'] !== null) {
    $filtrosResumo[] = 'Região: ' . $filtro['regiao'];
}

if ($filtro['municipio'] !== null) {
    $filtrosResumo[] = 'Município: ' . $filtro['municipio'];
}

$contextoPeriodo = $filtrosResumo !== []
    ? implode(' | ', $filtrosResumo)
    : 'Sem recorte temporal adicional';
$contextoTerritorial = $filtro['municipio']
    ?? $filtro['regiao']
    ?? 'Estado do Pará';
$operadorNome = trim((string) ($usuario['nome'] ?? ($modoEmbedPublico ? 'Visitante público' : 'Não identificado')));
$operadorPerfil = trim((string) ($usuario['perfil'] ?? ($modoEmbedPublico ? 'Público' : 'Não informado')));
$quantidadeEventos = count($labelsEventos);
$quantidadeEventosCorrelacao = count($labelsEventosCorrelacao);
$quantidadeSeveridadesAtivas = count($severidadesAtivas);
$quantidadeRegioes = count($labelsRegioes);
$quantidadeMunicipios = count($labelsMunicipios);
$quantidadeTipologiasRegiao = count($labelsEventosRegiao);
$quantidadeTipologiasMunicipio = count($labelsEventosMunicipio);

$resumoExecutivo = [
    [
        'label' => 'Alertas no recorte',
        'value' => (int) $totalAlertasEventos . ' alertas',
        'note' => 'Volume consolidado das tipologias no recorte temporal e territorial selecionado.',
        'tone' => 'primary',
    ],
    [
        'label' => 'Evento dominante',
        'value' => (string) $eventoPrincipal,
        'note' => $eventoPrincipalTotal > 0
            ? $eventoPrincipalTotal . ' alertas concentrados nessa tipologia.'
            : 'Sem predominância tipológica identificada no recorte atual.',
        'tone' => 'success',
    ],
    [
        'label' => 'Cobertura territorial',
        'value' => (int) $quantidadeRegioes . ' regiões / ' . (int) $quantidadeMunicipios . ' municípios',
        'note' => 'Alcance consolidado da leitura territorial da tipologia.',
        'tone' => 'neutral',
    ],
    [
        'label' => 'Base comparativa',
        'value' => (int) max(1, $quantidadeSeveridadesAtivas) . ' severidades / ' . (int) max(1, $quantidadeEventosCorrelacao) . ' eventos',
        'note' => (string) $contextoTerritorial,
        'tone' => 'warning',
    ],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Análise de Tipologia de Alertas</title>
<link rel="icon" type="image/x-icon" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/logo.cbmpa.ico">

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/painel.css">
<link rel="stylesheet" href="/assets/css/pages/alertas-form.css">
<link rel="stylesheet" href="/assets/css/pages/usuarios-listar.css">
<link rel="stylesheet" href="/assets/css/pages/analises-tipologia.css?v=<?= htmlspecialchars($versionCssTipologia, ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="/assets/css/pages/analises-embed.css">
</head>

<body class="<?= $modoEmbedPublico ? 'analise-embed-body' : '' ?>">
<?php if ($modoEmbedPublico): ?>
    <header class="analise-embed-topbar">
        <a href="/index.php#inicio" class="analise-embed-brand">
            <img src="/assets/images/logo-cedec.png" alt="CEDEC-PA">
            <span>
                <small><?= htmlspecialchars((string) ($appConfig['name'] ?? 'Sistema Multirriscos'), ENT_QUOTES, 'UTF-8') ?></small>
                <strong><?= htmlspecialchars((string) ($appConfig['institution'] ?? 'Defesa Civil do Estado do Pará'), ENT_QUOTES, 'UTF-8') ?></strong>
                <em><?= htmlspecialchars((string) ($appConfig['department'] ?? 'Monitoramento e resposta operacional'), ENT_QUOTES, 'UTF-8') ?></em>
            </span>
        </a>

        <nav class="analise-embed-nav" aria-label="Navegação pública de análises">
            <a href="/index.php#mapa-publico">Mapa ao vivo</a>
            <a href="/index.php#analises-publicas">Análises públicas</a>
            <a href="/index.php#alertas-ativos">Alertas ativos</a>
        </nav>

        <div class="analise-embed-topbar-meta">
            <span class="analise-embed-pill">Versão <?= htmlspecialchars((string) ($appConfig['version'] ?? '1.0.0'), ENT_QUOTES, 'UTF-8') ?></span>
            <a href="/index.php#analises-publicas" class="analise-embed-topbar-link">Início público</a>
        </div>
    </header>

    <main class="analise-embed-shell">
<?php else: ?>
<div class="layout">
    <?php include __DIR__ . '/../_sidebar.php'; ?>

    <main class="content">
        <?php include __DIR__ . '/../_topbar.php'; ?>

        <?php
        $breadcrumb = [
            'Painel' => '/pages/painel.php',
            'Tipologia de alertas' => null,
        ];
        include __DIR__ . '/../_breadcrumb.php';
        ?>
<?php endif; ?>

        <section class="dashboard alerta-form-shell usuarios-shell tipologia-shell">
            <div class="usuarios-hero-grid tipologia-hero-grid">
                <div class="alerta-form-hero usuarios-hero-panel tipologia-hero-panel">
                    <div class="alerta-form-lead usuarios-hero-copy tipologia-hero-copy">
                        <span class="alerta-form-kicker">Análise operacional</span>
                        <h1 class="alerta-form-title">Tipologia de alertas</h1>
                        <p class="alerta-form-description">
                            Observe os tipos de evento mais recorrentes, sua correlação com a severidade e a distribuição territorial
                            por regiões e municípios no mesmo padrão visual das telas analíticas mais recentes.
                        </p>

                        <div class="usuarios-hero-chip-row tipologia-hero-chip-row">
                            <span class="usuarios-hero-chip"><?= (int) $totalAlertasEventos ?> alertas no recorte</span>
                            <span class="usuarios-hero-chip"><?= htmlspecialchars((string) $contextoTerritorial, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="usuarios-hero-chip"><?= htmlspecialchars($contextoPeriodo, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>

                        <div class="usuarios-hero-actions tipologia-hero-actions">
                            <a href="#tipologia-filtros" class="btn btn-primary">Aplicar filtros</a>
                            <a href="#tipologia-graficos" class="btn btn-secondary">Ver gráficos</a>
                            <?php if ($modoEmbedPublico): ?>
                                <a href="/index.php#analises-publicas" class="btn btn-secondary">Voltar para página inicial</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <aside class="usuarios-command-card tipologia-command-card">
                    <span class="usuarios-command-kicker">Comando de tipologia</span>
                    <h2>Leitura integrada de eventos</h2>
                    <p>
                        Use este painel para validar operador, foco territorial e prioridades da análise
                        antes de comparar distribuição por evento, severidade e território.
                    </p>

                    <div class="usuarios-command-grid tipologia-command-grid">
                        <article class="usuarios-command-item">
                            <span>Operador da sessão</span>
                            <strong><?= htmlspecialchars($operadorNome, ENT_QUOTES, 'UTF-8') ?></strong>
                            <small>Perfil atual: <?= htmlspecialchars($operadorPerfil, ENT_QUOTES, 'UTF-8') ?>.</small>
                        </article>

                        <article class="usuarios-command-item">
                            <span>Foco territorial</span>
                            <strong><?= htmlspecialchars((string) $contextoTerritorial, ENT_QUOTES, 'UTF-8') ?></strong>
                            <small>Recorte aplicado em todos os gráficos desta análise.</small>
                        </article>

                        <article class="usuarios-command-item">
                            <span>Prioridade sugerida</span>
                            <strong>Do evento para o território</strong>
                            <small>Comece pelo volume de tipologias e finalize nas concentrações por região e município.</small>
                        </article>
                    </div>
                </aside>

                <div class="usuarios-summary-grid tipologia-summary-grid">
                    <?php foreach ($resumoExecutivo as $cardResumo): ?>
                        <article class="usuarios-summary-card usuarios-summary-card-<?= htmlspecialchars((string) ($cardResumo['tone'] ?? 'primary'), ENT_QUOTES, 'UTF-8') ?>">
                            <span class="usuarios-summary-label"><?= htmlspecialchars((string) ($cardResumo['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            <strong class="usuarios-summary-value"><?= htmlspecialchars((string) ($cardResumo['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                            <span class="usuarios-summary-note"><?= htmlspecialchars((string) ($cardResumo['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="alerta-form-panel usuarios-control-panel tipologia-control-panel">
                <div class="usuarios-control-grid tipologia-overview-grid">
                    <section id="tipologia-filtros" class="alerta-form-section usuarios-filter-panel tipologia-filter-panel">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Seção 1</span>
                            <h2 class="alerta-section-title">Filtros do recorte analítico</h2>
                            <p class="alerta-section-text">
                                Ajuste ano, mês, região e município para recalcular automaticamente a leitura tipológica.
                            </p>
                        </header>

                        <form method="get" class="usuarios-filters tipologia-filters tipologia-filter-grid">
                            <?php if ($modoEmbedPublico): ?>
                                <input type="hidden" name="embed" value="1">
                            <?php endif; ?>
                            <div class="form-group">
                                <label for="ano">Ano</label>
                                <select id="ano" name="ano" data-auto-submit>
                                    <option value="">Todos</option>
                                    <?php for ($ano = $anoAtual; $ano >= 2025; $ano--): ?>
                                        <option value="<?= $ano ?>" <?= $filtro['ano'] === $ano ? 'selected' : '' ?>>
                                            <?= $ano ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="mes">Mês</label>
                                <select id="mes" name="mes" data-auto-submit>
                                    <option value="">Todos</option>
                                    <?php foreach ($meses as $numeroMes => $nomeMes): ?>
                                        <option value="<?= $numeroMes ?>" <?= $filtro['mes'] === $numeroMes ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($nomeMes, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="filtro-regiao">Região de integração</label>
                                <select id="filtro-regiao" name="regiao" data-auto-submit>
                                    <option value="">Todas</option>
                                    <?php foreach ($regioes as $regiao): ?>
                                        <option value="<?= htmlspecialchars((string) $regiao, ENT_QUOTES, 'UTF-8') ?>" <?= $filtro['regiao'] === $regiao ? 'selected' : '' ?>>
                                            <?= htmlspecialchars((string) $regiao, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="filtro-municipio">Município</label>
                                <select id="filtro-municipio" name="municipio" disabled>
                                    <option value=""><?= $filtro['regiao'] !== null ? 'Carregando municípios...' : 'Selecione uma região' ?></option>
                                </select>
                            </div>

                            <div class="usuarios-filter-meta tipologia-filter-meta form-group field-span-2">
                                <span class="usuarios-filter-meta-label">Recorte ativo</span>
                                <div class="usuarios-filter-pill-row">
                                    <?php if ($filtrosResumo !== []): ?>
                                        <?php foreach ($filtrosResumo as $filtroAtivo): ?>
                                            <span class="usuarios-filter-pill"><?= htmlspecialchars((string) $filtroAtivo, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="usuarios-filter-pill is-neutral">Sem filtros adicionais</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="alerta-callout tipologia-filter-callout form-group field-span-2">
                                <strong>Atualização imediata</strong>
                                Ano, mês e região reaplicam o recorte automaticamente. O município é carregado após a seleção da região correspondente.
                            </div>

                            <div class="alerta-form-actions tipologia-filter-actions usuarios-filter-actions form-group field-span-2">
                                <div class="alerta-form-actions-left">
                                    <span class="alerta-inline-note">Filtros aplicados: <?= htmlspecialchars($contextoPeriodo, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>

                                <div class="alerta-form-actions-right">
                                    <button type="button" class="btn btn-secondary" data-clear-filtros>Limpar filtros</button>
                                </div>
                            </div>
                        </form>
                    </section>

                    <section class="alerta-form-section usuarios-governance-panel tipologia-governance-panel">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Seção 2</span>
                            <h2 class="alerta-section-title">Leituras rápidas do período</h2>
                            <p class="alerta-section-text">
                                Use os destaques abaixo para identificar a tipologia dominante e os territórios com maior recorrência.
                            </p>
                        </header>

                        <div class="usuarios-insight-grid tipologia-insight-grid">
                            <article class="usuarios-insight-card usuarios-insight-card-emphasis tipologia-mini-card">
                                <span class="usuarios-insight-kicker">Evento mais recorrente</span>
                                <strong><?= htmlspecialchars($eventoPrincipal, ENT_QUOTES, 'UTF-8') ?></strong>
                                <p>
                                    <?= $eventoPrincipalTotal > 0 ? $eventoPrincipalTotal . ' alertas distintos neste tipo de evento.' : 'Sem tipologia predominante no recorte atual.' ?>
                                </p>
                            </article>

                            <article class="usuarios-insight-card tipologia-mini-card">
                                <span class="usuarios-insight-kicker">Região com maior recorrência</span>
                                <strong><?= htmlspecialchars($regiaoLider, ENT_QUOTES, 'UTF-8') ?></strong>
                                <p>
                                    <?= $regiaoLiderTotal > 0 ? $regiaoLiderTotal . ' ocorrências tipológicas somadas nesta região.' : 'Sem concentração regional identificada.' ?>
                                </p>
                            </article>

                            <article class="usuarios-insight-card tipologia-mini-card">
                                <span class="usuarios-insight-kicker">Município com maior recorrência</span>
                                <strong><?= htmlspecialchars($municipioLider, ENT_QUOTES, 'UTF-8') ?></strong>
                                <p>
                                    <?= $municipioLiderTotal > 0 ? $municipioLiderTotal . ' ocorrências tipológicas somadas neste município.' : 'Sem concentração municipal identificada.' ?>
                                </p>
                            </article>

                            <article class="usuarios-insight-card tipologia-mini-card">
                                <span class="usuarios-insight-kicker">Base comparativa</span>
                                <strong><?= (int) max(1, $quantidadeSeveridadesAtivas) ?> severidades</strong>
                                <p>
                                    <?= (int) max(1, $quantidadeEventosCorrelacao) ?> eventos considerados na correlação com gravidade.
                                </p>
                            </article>
                        </div>

                        <div class="alerta-callout tipologia-info-callout">
                            <strong>Como interpretar</strong>
                            Combine a correlação com severidade e a distribuição territorial para entender quais eventos aparecem com mais frequência e onde eles pressionam mais a operação.
                        </div>

                        <div class="alerta-form-actions tipologia-actions usuarios-filter-actions">
                            <div class="alerta-form-actions-left">
                                <span class="alerta-inline-note">A leitura abaixo permanece sincronizada com o recorte territorial e temporal selecionado.</span>
                            </div>

                            <?php if (!$modoEmbedPublico): ?>
                                <div class="alerta-form-actions-right tipologia-action-buttons">
                                    <a href="/pages/analises/severidade.php" class="btn btn-secondary">Abrir severidade</a>
                                    <a href="/pages/mapas/mapa_multirriscos.php" class="btn btn-secondary">Abrir mapa multirriscos</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </div>

            <section id="tipologia-graficos" class="alerta-form-panel usuarios-table-panel tipologia-chart-panel">
                <header class="usuarios-table-head tipologia-section-head">
                    <div class="alerta-section-header">
                        <span class="alerta-section-kicker">Painel principal</span>
                        <h2 class="alerta-section-title">Tipologias mais recorrentes</h2>
                        <p class="alerta-section-text">
                            Veja o volume absoluto por tipo de evento para identificar quais tipologias dominam o recorte selecionado.
                        </p>
                    </div>

                    <div class="usuarios-table-head-actions">
                        <span class="usuarios-result-chip"><?= (int) max(1, $quantidadeEventos) ?> eventos no gráfico</span>
                    </div>
                </header>

                <div class="usuarios-table-toolbar tipologia-chart-toolbar">
                    <div class="usuarios-table-toolbar-copy">
                        <strong>Recorte ativo:</strong>
                        <span><?= htmlspecialchars($contextoPeriodo, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <div class="usuarios-table-toolbar-pills">
                        <span class="usuarios-toolbar-pill"><?= (int) $totalAlertasEventos ?> alertas contabilizados</span>
                        <span class="usuarios-toolbar-pill"><?= (int) max(1, $quantidadeEventosCorrelacao) ?> eventos correlacionados</span>
                        <span class="usuarios-toolbar-pill"><?= (int) $quantidadeRegioes ?> regiões / <?= (int) $quantidadeMunicipios ?> municípios</span>
                    </div>
                </div>

                <div class="tipologia-chart-card">
                    <div class="tipologia-chart-stage">
                        <canvas class="tipologia-chart-canvas" id="graficoTipologiaEvento"></canvas>
                    </div>
                </div>
            </section>

            <div class="alerta-form-panel usuarios-control-panel tipologia-chart-panel">
                <div class="tipologia-analytics-grid">
                    <section class="alerta-form-section tipologia-chart-section">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Seção 4</span>
                            <h2 class="alerta-section-title">Percentual por tipologia</h2>
                            <p class="alerta-section-text">
                                Compare o peso relativo de cada tipo de evento no total de alertas do recorte.
                            </p>
                        </header>

                        <div class="tipologia-chart-card">
                            <div class="tipologia-chart-meta">
                                <span class="tipologia-chart-chip"><?= (int) max(1, $quantidadeEventos) ?> tipologias no recorte</span>
                                <span class="tipologia-chart-chip">Leitura percentual consolidada</span>
                            </div>
                            <div class="tipologia-chart-stage">
                                <canvas class="tipologia-chart-canvas" id="graficoTipologiaPercentual"></canvas>
                            </div>
                        </div>
                    </section>

                    <section class="alerta-form-section tipologia-chart-section">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Seção 5</span>
                            <h2 class="alerta-section-title">Correlação entre evento e severidade</h2>
                            <p class="alerta-section-text">
                                Observe como cada tipologia se distribui nas faixas de gravidade para identificar padrões recorrentes.
                            </p>
                        </header>

                        <div class="tipologia-chart-card tipologia-chart-card--tall">
                            <div class="tipologia-chart-meta">
                                <span class="tipologia-chart-chip"><?= (int) max(1, $quantidadeEventosCorrelacao) ?> eventos correlacionados</span>
                                <span class="tipologia-chart-chip"><?= (int) max(1, $quantidadeSeveridadesAtivas) ?> severidades presentes</span>
                            </div>
                            <div class="tipologia-chart-stage tipologia-chart-stage--tall">
                                <canvas class="tipologia-chart-canvas" id="graficoTipologiaSeveridade"></canvas>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div class="alerta-form-panel usuarios-control-panel tipologia-chart-panel">
                <div class="tipologia-analytics-grid">
                    <section class="alerta-form-section tipologia-chart-section">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Seção 6</span>
                            <h2 class="alerta-section-title">Tipologia por região de integração</h2>
                            <p class="alerta-section-text">
                                Compare como as tipologias se distribuem entre as regiões contempladas no recorte atual.
                            </p>
                        </header>

                        <div class="tipologia-chart-card tipologia-chart-card--tall">
                            <div class="tipologia-chart-meta">
                                <span class="tipologia-chart-chip"><?= (int) $quantidadeRegioes ?> regiões analisadas</span>
                                <span class="tipologia-chart-chip"><?= (int) max(1, $quantidadeTipologiasRegiao) ?> tipologias territoriais</span>
                            </div>
                            <div class="tipologia-chart-stage tipologia-chart-stage--tall">
                                <canvas class="tipologia-chart-canvas" id="graficoTipologiaRegiao"></canvas>
                            </div>
                        </div>
                    </section>

                    <section class="alerta-form-section tipologia-chart-section">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Seção 7</span>
                            <h2 class="alerta-section-title">Tipologia por município</h2>
                            <p class="alerta-section-text">
                                Visualize os municípios com maior recorrência tipológica e os tipos de evento associados.
                            </p>
                        </header>

                        <div class="tipologia-chart-card tipologia-chart-card--tall">
                            <div class="tipologia-chart-meta">
                                <span class="tipologia-chart-chip"><?= (int) $quantidadeMunicipios ?> municípios analisados</span>
                                <span class="tipologia-chart-chip"><?= (int) max(1, $quantidadeTipologiasMunicipio) ?> tipologias territoriais</span>
                            </div>
                            <div class="tipologia-chart-stage tipologia-chart-stage--tall">
                                <canvas class="tipologia-chart-canvas" id="graficoTipologiaMunicipio"></canvas>
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
    <footer class="analise-embed-footer">
        <div class="analise-embed-footer-copy">
            <strong><?= htmlspecialchars((string) ($appConfig['institution'] ?? 'Defesa Civil do Estado do Pará'), ENT_QUOTES, 'UTF-8') ?></strong>
            <span><?= htmlspecialchars((string) ($appConfig['department'] ?? 'Central de monitoramento'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="analise-embed-footer-meta">
            <span>Painel público de análises multirriscos</span>
            <a href="mailto:<?= htmlspecialchars((string) ($appConfig['support_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars((string) ($appConfig['support_email'] ?? 'suporte@defesacivil.pa.gov.br'), ENT_QUOTES, 'UTF-8') ?>
            </a>
        </div>
        <a href="/index.php#analises-publicas" class="btn btn-secondary">Voltar para página inicial</a>
    </footer>
<?php else: ?>
    </main>
</div>
<?php endif; ?>

<script src="/assets/vendor/chartjs/chart-lite.js?v=<?= htmlspecialchars($versionChartLite, ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="/assets/js/pages/analises-common.js?v=<?= htmlspecialchars($versionJsCommon, ENT_QUOTES, 'UTF-8') ?>"></script>
<script nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>" id="analise-tipologia-data" type="application/json"><?= json_encode([
    'labelsEventos' => $labelsEventos,
    'labelsEventosCorrelacao' => $labelsEventosCorrelacao,
    'valoresEventos' => $valoresEventos,
    'percentuaisEventos' => $percentuaisEventos,
    'datasetsSeveridade' => $datasetsSeveridade,
    'labelsRegioes' => $labelsRegioes,
    'datasetsRegiao' => $datasetsRegiao,
    'labelsMeses' => $labelsMeses ?? [],
    'datasetsSazonalidade' => $datasetsSazonalidade ?? [],
    'labelsMunicipios' => $labelsMunicipios,
    'datasetsMunicipio' => $datasetsMunicipio,
    'municipioAtual' => $filtro['municipio'] ?? '',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="/assets/js/pages/analises-tipologia.js?v=<?= htmlspecialchars($versionJsTipologia, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
