<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Session.php';
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Core/AppConfig.php';
require_once __DIR__ . '/../../app/Helpers/SecurityHeaders.php';
require_once __DIR__ . '/../../app/Helpers/TimeHelper.php';
require_once __DIR__ . '/../../app/Services/AnaliseIndiceRiscoService.php';

$modoEmbedPublico = isset($_GET['embed']) && $_GET['embed'] === '1';
$appConfig = AppConfig::get();

$versionBase = rawurlencode((string) ($appConfig['version'] ?? '1.0.0'));
$cssIndicePath = __DIR__ . '/../../assets/css/pages/analises-indice_risco.css';
$jsIndicePath = __DIR__ . '/../../assets/js/pages/analises-indice_risco.js';
$jsCommonPath = __DIR__ . '/../../assets/js/pages/analises-common.js';
$chartLitePath = __DIR__ . '/../../assets/vendor/chartjs/chart-lite.js';

$versionCssIndice = $versionBase
    . '.' . ((int) @filemtime($cssIndicePath))
    . '.' . substr((string) @md5_file($cssIndicePath), 0, 8);
$versionJsIndice = $versionBase
    . '.' . ((int) @filemtime($jsIndicePath))
    . '.' . substr((string) @md5_file($jsIndicePath), 0, 8);
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
$perfilUsuarioExibicao = trim((string) ($usuario['perfil'] ?? ''));

if ($perfilUsuarioExibicao === '') {
    $perfilUsuarioExibicao = $modoEmbedPublico ? 'Visitante publico' : 'Nao informado';
}

$filtroAno = isset($_GET['ano']) && $_GET['ano'] !== '' ? (int) $_GET['ano'] : null;
$filtroMes = isset($_GET['mes']) && $_GET['mes'] !== '' ? (int) $_GET['mes'] : null;
$filtroRegiao = trim((string) ($_GET['regiao'] ?? ''));
$filtroMunicipio = trim((string) ($_GET['municipio'] ?? ''));

$filtro = [
    'ano' => $filtroAno,
    'mes' => $filtroMes,
    'regiao' => $filtroRegiao !== '' ? $filtroRegiao : null,
    'municipio' => $filtroMunicipio !== '' ? $filtroMunicipio : null,
];

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

$regioes = $db->query("
    SELECT DISTINCT regiao_integracao
    FROM municipios_regioes_pa
    WHERE regiao_integracao IS NOT NULL
      AND regiao_integracao <> ''
      AND regiao_integracao <> 'regiao_integracao'
    ORDER BY regiao_integracao
")->fetchAll(PDO::FETCH_COLUMN);

if ($filtro['regiao'] === null) {
    $filtro['municipio'] = null;
    $filtroMunicipio = '';
}

if ($filtro['municipio'] !== null && $filtro['regiao'] !== null) {
    $stmtMunicipioValido = $db->prepare("
        SELECT 1
        FROM municipios_regioes_pa
        WHERE municipio = ?
          AND regiao_integracao = ?
        LIMIT 1
    ");
    $stmtMunicipioValido->execute([$filtro['municipio'], $filtro['regiao']]);

    if (!$stmtMunicipioValido->fetch()) {
        $filtro['municipio'] = null;
        $filtroMunicipio = '';
    }
}

$rankingIRP = AnaliseIndiceRiscoService::rankingIRP($db, $filtro);
$rankingIPTCompleto = AnaliseIndiceRiscoService::rankingIPT($db, $filtro);
$exibirTopMunicipiosNacional = $filtro['regiao'] === null && $filtro['municipio'] === null;
$rankingIPT = $exibirTopMunicipiosNacional
    ? array_slice($rankingIPTCompleto, 0, 10)
    : $rankingIPTCompleto;

$labelsIRP = array_column($rankingIRP, 'regiao_integracao');
$valoresIRP = array_map('floatval', array_column($rankingIRP, 'irp'));
$labelsIPT = array_column($rankingIPT, 'municipio');
$valoresIPT = array_map('floatval', array_column($rankingIPT, 'ipt'));

$totalIRP = array_sum($valoresIRP);
$totalIPT = array_sum($valoresIPT);

$mediaIRP = count($valoresIRP) ? $totalIRP / count($valoresIRP) : 0;
$mediaIPT = count($valoresIPT) ? $totalIPT / count($valoresIPT) : 0;

$regiaoLider = $rankingIRP[0]['regiao_integracao'] ?? 'Sem dados';
$valorRegiaoLider = (float) ($rankingIRP[0]['irp'] ?? 0);
$municipioLider = $rankingIPT[0]['municipio'] ?? 'Sem dados';
$valorMunicipioLider = (float) ($rankingIPT[0]['ipt'] ?? 0);

$filtrosResumo = [];

if ($filtroAno !== null) {
    $filtrosResumo[] = 'Ano: ' . $filtroAno;
}

if ($filtroMes !== null && isset($meses[$filtroMes])) {
    $filtrosResumo[] = 'Mes: ' . $meses[$filtroMes];
}

if ($filtro['regiao'] !== null) {
    $filtrosResumo[] = 'Regiao: ' . $filtro['regiao'];
}

if ($filtro['municipio'] !== null) {
    $filtrosResumo[] = 'Municipio: ' . $filtro['municipio'];
}

$contextoPeriodo = $filtrosResumo !== []
    ? implode(' | ', $filtrosResumo)
    : 'Sem recorte temporal adicional';
$contextoTerritorial = $filtro['municipio']
    ?? $filtro['regiao']
    ?? 'Estado do Para';
$operadorNome = trim((string) ($usuario['nome'] ?? ($modoEmbedPublico ? 'Visitante publico' : 'Nao identificado')));
$quantidadeRegioes = count($rankingIRP);
$quantidadeMunicipios = count($rankingIPTCompleto);
$resumoMunicipiosSecao4 = $exibirTopMunicipiosNacional
    ? 'Top 10 municipios com maior IPT'
    : (count($rankingIPT) . ' municipios no recorte filtrado');

$resumoExecutivo = [
    [
        'label' => 'Media IRP',
        'value' => number_format($mediaIRP, 1, ',', '.'),
        'note' => 'Pressao operacional regional media no recorte atual.',
        'tone' => 'primary',
    ],
    [
        'label' => 'Media IPT',
        'value' => number_format($mediaIPT, 1, ',', '.'),
        'note' => 'Carga territorial media observada entre os municipios.',
        'tone' => 'success',
    ],
    [
        'label' => 'Cobertura territorial',
        'value' => $quantidadeRegioes . ' regioes / ' . $quantidadeMunicipios . ' municipios',
        'note' => 'Abrangencia atual dos indices no recorte aplicado.',
        'tone' => 'neutral',
    ],
    [
        'label' => 'Recorte consolidado',
        'value' => 'Ano, mes, regiao e municipio',
        'note' => (string) $contextoTerritorial,
        'tone' => 'warning',
    ],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Indices de Risco - IRP / IPT</title>
<link rel="icon" type="image/x-icon" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/logo.cbmpa.ico">

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/painel.css">
<link rel="stylesheet" href="/assets/css/pages/alertas-form.css">
<link rel="stylesheet" href="/assets/css/pages/usuarios-listar.css">
<link rel="stylesheet" href="/assets/css/pages/analises-indice_risco.css?v=<?= htmlspecialchars($versionCssIndice, ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="/assets/css/pages/analises-embed.css">
</head>

<body class="<?= $modoEmbedPublico ? 'analise-embed-body' : '' ?>">
<?php if ($modoEmbedPublico): ?>
    <header class="analise-embed-topbar">
        <a href="/index.php#inicio" class="analise-embed-brand">
            <img src="/assets/images/logo-cedec.png" alt="CEDEC-PA">
            <span>
                <small><?= htmlspecialchars((string) ($appConfig['name'] ?? 'Sistema Multirriscos'), ENT_QUOTES, 'UTF-8') ?></small>
                <strong><?= htmlspecialchars((string) ($appConfig['institution'] ?? 'Defesa Civil do Estado do Para'), ENT_QUOTES, 'UTF-8') ?></strong>
                <em><?= htmlspecialchars((string) ($appConfig['department'] ?? 'Monitoramento e resposta operacional'), ENT_QUOTES, 'UTF-8') ?></em>
            </span>
        </a>

        <nav class="analise-embed-nav" aria-label="Navegacao publica de analises">
            <a href="/index.php#mapa-publico">Mapa ao vivo</a>
            <a href="/index.php#analises-publicas">Analises publicas</a>
            <a href="/index.php#alertas-ativos">Alertas ativos</a>
        </nav>

        <div class="analise-embed-topbar-meta">
            <span class="analise-embed-pill">Versao <?= htmlspecialchars((string) ($appConfig['version'] ?? '1.0.0'), ENT_QUOTES, 'UTF-8') ?></span>
            <a href="/index.php#analises-publicas" class="analise-embed-topbar-link">Inicio publico</a>
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
            'Indices de risco' => null,
        ];
        include __DIR__ . '/../_breadcrumb.php';
        ?>
<?php endif; ?>

        <section class="dashboard alerta-form-shell usuarios-shell indice-shell">
            <div class="usuarios-hero-grid indice-hero-grid">
                <div class="alerta-form-hero usuarios-hero-panel indice-hero-panel">
                    <div class="alerta-form-lead usuarios-hero-copy indice-hero-copy">
                        <span class="alerta-form-kicker">Analise operacional</span>
                        <h1 class="alerta-form-title">Indices de risco IRP e IPT</h1>
                        <p class="alerta-form-description">
                            Acompanhe a pressao regional e territorial provocada pelos alertas multirriscos, com leitura
                            consolidada por periodo, destaques de lideranca e acesso rapido a metodologia usada no calculo.
                        </p>

                        <div class="usuarios-hero-chip-row indice-hero-chip-row">
                            <span class="usuarios-hero-chip"><?= number_format($mediaIRP, 1, ',', '.') ?> media IRP</span>
                            <span class="usuarios-hero-chip"><?= number_format($mediaIPT, 1, ',', '.') ?> media IPT</span>
                            <span class="usuarios-hero-chip"><?= htmlspecialchars($contextoPeriodo, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>

                        <div class="usuarios-hero-actions indice-hero-actions">
                            <a href="#indice-filtros" class="btn btn-primary">Aplicar filtros</a>
                            <a href="#indice-graficos" class="btn btn-secondary">Ver graficos</a>
                            <?php if ($modoEmbedPublico): ?>
                                <a href="/index.php#analises-publicas" class="btn btn-secondary">Voltar para pagina inicial</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="usuarios-summary-grid indice-summary-grid">
                    <?php foreach ($resumoExecutivo as $cardResumo): ?>
                        <article class="usuarios-summary-card usuarios-summary-card-<?= htmlspecialchars((string) ($cardResumo['tone'] ?? 'primary'), ENT_QUOTES, 'UTF-8') ?>">
                            <span class="usuarios-summary-label"><?= htmlspecialchars((string) ($cardResumo['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            <strong class="usuarios-summary-value"><?= htmlspecialchars((string) ($cardResumo['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                            <span class="usuarios-summary-note"><?= htmlspecialchars((string) ($cardResumo['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>

                <aside class="usuarios-command-card indice-command-card">
                    <span class="usuarios-command-kicker">Comando de indices</span>
                    <h2>Coordenacao da leitura de pressao</h2>
                    <p>
                        Use este painel para validar operador, recorte territorial e prioridade analitica
                        antes de comparar os rankings IRP e IPT.
                    </p>

                    <div class="usuarios-command-grid indice-command-grid">
                        <article class="usuarios-command-item">
                            <span>Operador da sessao</span>
                            <strong><?= htmlspecialchars($operadorNome, ENT_QUOTES, 'UTF-8') ?></strong>
                            <small>Perfil atual: <?= htmlspecialchars($perfilUsuarioExibicao, ENT_QUOTES, 'UTF-8') ?>.</small>
                        </article>

                        <article class="usuarios-command-item">
                            <span>Foco territorial</span>
                            <strong><?= htmlspecialchars((string) $contextoTerritorial, ENT_QUOTES, 'UTF-8') ?></strong>
                            <small>Recorte aplicado aos dois indices no mesmo painel.</small>
                        </article>

                        <article class="usuarios-command-item">
                            <span>Prioridade sugerida</span>
                            <strong>Da regiao ao municipio</strong>
                            <small>Comece pelo IRP para visao macro e aprofunde no IPT para priorizacao territorial.</small>
                        </article>
                    </div>
                </aside>
            </div>

            <div class="alerta-form-panel usuarios-control-panel indice-control-panel">
                <div class="usuarios-control-grid indice-overview-grid">
                    <section id="indice-filtros" class="alerta-form-section usuarios-filter-panel indice-filter-panel">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Secao 1</span>
                            <h2 class="alerta-section-title">Filtros do recorte analitico</h2>
                            <p class="alerta-section-text">
                                Selecione o periodo para recalcular automaticamente o IRP e o IPT no mesmo painel.
                            </p>
                        </header>

                        <form method="get" class="usuarios-filters indice-filters indice-filter-grid">
                            <?php if ($modoEmbedPublico): ?>
                                <input type="hidden" name="embed" value="1">
                            <?php endif; ?>
                            <div class="form-group">
                                <label for="ano">Ano</label>
                                <select id="ano" name="ano" data-auto-submit>
                                    <option value="">Todos</option>
                                    <?php for ($ano = $anoAtual; $ano >= 2025; $ano--): ?>
                                        <option value="<?= $ano ?>" <?= $filtroAno === $ano ? 'selected' : '' ?>>
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
                                        <option value="<?= $numeroMes ?>" <?= $filtroMes === $numeroMes ? 'selected' : '' ?>>
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
                                        <option value="<?= htmlspecialchars((string) $regiao, ENT_QUOTES, 'UTF-8') ?>" <?= $filtro['regiao'] === $regiao ? 'selected' : '' ?>>
                                            <?= htmlspecialchars((string) $regiao, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="filtro-municipio">Municipio</label>
                                <select id="filtro-municipio" name="municipio" disabled>
                                    <option value=""><?= $filtro['regiao'] !== null ? 'Carregando municipios...' : 'Selecione uma regiao' ?></option>
                                </select>
                            </div>

                            <div class="usuarios-filter-meta indice-filter-meta form-group field-span-2">
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

                            <div class="alerta-callout indice-filter-callout form-group field-span-2">
                                <strong>Atualizacao imediata</strong>
                                Ano, mes, regiao e municipio reaplicam o recorte automaticamente. O municipio so fica disponivel depois da selecao da regiao correspondente.
                            </div>

                            <div class="alerta-form-actions indice-filter-actions usuarios-filter-actions form-group field-span-2">
                                <div class="alerta-form-actions-left">
                                    <span class="alerta-inline-note">Filtros aplicados: <?= htmlspecialchars($contextoPeriodo, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>

                                <div class="alerta-form-actions-right">
                                    <button type="button" class="btn btn-secondary" data-clear-filtros>Limpar filtros</button>
                                </div>
                            </div>
                        </form>
                    </section>

                    <section class="alerta-form-section usuarios-governance-panel indice-governance-panel">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Secao 2</span>
                            <h2 class="alerta-section-title">Leituras rapidas do periodo</h2>
                            <p class="alerta-section-text">
                                Use os destaques abaixo para identificar os lideres de pressao e interpretar rapidamente o comportamento do recorte selecionado.
                            </p>
                        </header>

                        <div class="usuarios-insight-grid indice-insight-grid">
                            <article class="usuarios-insight-card usuarios-insight-card-emphasis indice-mini-card">
                                <span class="usuarios-insight-kicker">Regiao com maior IRP</span>
                                <strong><?= htmlspecialchars($regiaoLider, ENT_QUOTES, 'UTF-8') ?></strong>
                                <p>
                                    <?= $valorRegiaoLider > 0 ? 'Indice atual: ' . number_format($valorRegiaoLider, 1, ',', '.') : 'Sem pressao regional registrada no recorte atual.' ?>
                                </p>
                            </article>

                            <article class="usuarios-insight-card indice-mini-card">
                                <span class="usuarios-insight-kicker">Municipio com maior IPT</span>
                                <strong><?= htmlspecialchars($municipioLider, ENT_QUOTES, 'UTF-8') ?></strong>
                                <p>
                                    <?= $valorMunicipioLider > 0 ? 'Indice atual: ' . number_format($valorMunicipioLider, 1, ',', '.') : 'Sem pressao territorial registrada no recorte atual.' ?>
                                </p>
                            </article>

                            <article class="usuarios-insight-card indice-mini-card">
                                <span class="usuarios-insight-kicker">Perfil conectado</span>
                                <strong><?= htmlspecialchars($perfilUsuarioExibicao, ENT_QUOTES, 'UTF-8') ?></strong>
                                <p>Leitura analitica com acesso liberado para este perfil.</p>
                            </article>
                        </div>

                        <div class="alerta-callout indice-info-callout">
                            <strong>Como interpretar</strong>
                            O IRP evidencia a pressao operacional por regiao, enquanto o IPT mostra a carga acumulada sobre cada municipio com base em severidade e duracao dos alertas.
                        </div>

                        <div class="alerta-form-actions indice-actions usuarios-filter-actions">
                            <div class="alerta-form-actions-left">
                                <span class="alerta-inline-note">A metodologia detalhada fica disponivel em modal sem sair da tela.</span>
                            </div>

                            <div class="alerta-form-actions-right indice-action-buttons">
                                <button type="button" class="btn btn-secondary" data-open-metodologia>Ver metodologia</button>
                                <?php if (!$modoEmbedPublico): ?>
                                    <a href="/pages/mapas/mapa_multirriscos.php" class="btn btn-secondary">Abrir mapa multirriscos</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div id="indice-graficos" class="alerta-form-panel usuarios-control-panel indice-chart-panel">
                <div class="indice-analytics-grid">
                    <section class="alerta-form-section indice-chart-section">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Secao 3</span>
                            <h2 class="alerta-section-title">Ranking regional de pressao</h2>
                            <p class="alerta-section-text">
                                Compare as regioes de integracao e identifique onde a pressao operacional esta mais concentrada no recorte atual.
                            </p>
                        </header>

                        <div class="indice-chart-card">
                            <div class="indice-chart-meta">
                                <span class="indice-chart-chip"><?= count($rankingIRP) ?> regioes analisadas</span>
                                <span class="indice-chart-chip">Soma IRP: <?= number_format($totalIRP, 1, ',', '.') ?></span>
                            </div>
                            <canvas id="graficoIRP"></canvas>
                        </div>
                    </section>

                    <section class="alerta-form-section indice-chart-section">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Secao 4</span>
                            <h2 class="alerta-section-title">Ranking territorial de pressao</h2>
                            <p class="alerta-section-text">
                                Observe os municipios com maior acumulado de pressao para apoiar priorizacao de monitoramento e resposta.
                            </p>
                        </header>

                        <div class="indice-chart-card">
                            <div class="indice-chart-meta">
                                <span class="indice-chart-chip"><?= htmlspecialchars((string) $resumoMunicipiosSecao4, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="indice-chart-chip">Soma IPT: <?= number_format($totalIPT, 1, ',', '.') ?></span>
                            </div>
                            <canvas id="graficoIPT"></canvas>
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
            <strong><?= htmlspecialchars((string) ($appConfig['institution'] ?? 'Defesa Civil do Estado do Para'), ENT_QUOTES, 'UTF-8') ?></strong>
            <span><?= htmlspecialchars((string) ($appConfig['department'] ?? 'Central de monitoramento'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="analise-embed-footer-meta">
            <span>Painel publico de analises multirriscos</span>
            <a href="mailto:<?= htmlspecialchars((string) ($appConfig['support_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars((string) ($appConfig['support_email'] ?? 'suporte@defesacivil.pa.gov.br'), ENT_QUOTES, 'UTF-8') ?>
            </a>
        </div>
        <a href="/index.php#analises-publicas" class="btn btn-secondary">Voltar para pagina inicial</a>
    </footer>
<?php else: ?>
    </main>
</div>
<?php endif; ?>

<div class="modal" id="modalInfo">
    <div class="modal-content indice-modal-content">
        <div class="indice-modal-header">
            <div>
                <span class="alerta-section-kicker">Metodologia</span>
                <h2>Como os indices IRP e IPT sao calculados</h2>
            </div>
            <button type="button" class="indice-modal-close" data-close-metodologia>X</button>
        </div>

        <div class="indice-modal-body">
            <article class="indice-modal-block">
                <h3>IPT - Indice de Pressao Territorial</h3>
                <p>
                    Mede a intensidade da pressao sofrida por um municipio considerando a severidade do alerta e o tempo de duracao do evento.
                </p>
                <ul>
                    <li>IPT = soma de alertas x peso da severidade x duracao em horas.</li>
                    <li>Pesos: Baixo = 1, Moderado = 2, Alto = 3, Muito Alto = 4 e Extremo = 5.</li>
                </ul>
            </article>

            <article class="indice-modal-block">
                <h3>IRP - Indice Regional de Pressao</h3>
                <p>
                    Avalia a pressao operacional sobre uma regiao de integracao considerando a severidade do alerta e o numero de municipios afetados.
                </p>
                <ul>
                    <li>IRP = soma de alertas x peso da severidade x municipios afetados.</li>
                    <li>Pesos: Baixo = 1, Moderado = 2, Alto = 3, Muito Alto = 4 e Extremo = 5.</li>
                </ul>
            </article>
        </div>

        <div class="indice-modal-actions">
            <button type="button" class="btn btn-primary" data-close-metodologia>Fechar</button>
        </div>
    </div>
</div>

<script src="/assets/vendor/chartjs/chart-lite.js?v=<?= htmlspecialchars($versionChartLite, ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="/assets/js/pages/analises-common.js?v=<?= htmlspecialchars($versionJsCommon, ENT_QUOTES, 'UTF-8') ?>"></script>
<script nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>" id="indice-risco-data" type="application/json"><?= json_encode([
    'labelsIRP' => $labelsIRP,
    'valoresIRP' => $valoresIRP,
    'labelsIPT' => $labelsIPT,
    'valoresIPT' => $valoresIPT,
    'municipioAtual' => $filtro['municipio'] ?? '',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="/assets/js/pages/analises-indice_risco.js?v=<?= htmlspecialchars($versionJsIndice, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
