<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Session.php';
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Core/AppConfig.php';
require_once __DIR__ . '/../../app/Helpers/SecurityHeaders.php';
require_once __DIR__ . '/../../app/Helpers/TimeHelper.php';
require_once __DIR__ . '/../../app/Services/AnaliseSeveridadeService.php';
require_once __DIR__ . '/../../app/Services/AnaliseMunicipiosImpactadosService.php';
require_once __DIR__ . '/../../app/Services/AnaliseAlertasEmitidosService.php';

$modoEmbedPublico = isset($_GET['embed']) && $_GET['embed'] === '1';
$appConfig = AppConfig::get();

$versionBase = rawurlencode((string) ($appConfig['version'] ?? '1.0.0'));
$cssSeveridadePath = __DIR__ . '/../../assets/css/pages/analises-severidade.css';
$jsSeveridadePath = __DIR__ . '/../../assets/js/pages/analises-severidade.js';
$jsCommonPath = __DIR__ . '/../../assets/js/pages/analises-common.js';
$chartLitePath = __DIR__ . '/../../assets/vendor/chartjs/chart-lite.js';

$versionCssSeveridade = $versionBase
    . '.' . ((int) @filemtime($cssSeveridadePath))
    . '.' . substr((string) @md5_file($cssSeveridadePath), 0, 8);
$versionJsSeveridade = $versionBase
    . '.' . ((int) @filemtime($jsSeveridadePath))
    . '.' . substr((string) @md5_file($jsSeveridadePath), 0, 8);
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

$exibirTopMunicipiosNacional = $filtro['regiao'] === null && $filtro['municipio'] === null;
$rankingMunicipiosCompleto = AnaliseMunicipiosImpactadosService::ranking($db, $filtro, null);
$rankingMunicipios = $exibirTopMunicipiosNacional
    ? array_slice($rankingMunicipiosCompleto, 0, 10)
    : $rankingMunicipiosCompleto;
$dadosAlertasEvento = AnaliseAlertasEmitidosService::porTipoEvento($db, $filtro);
$dadosSeveridade = AnaliseSeveridadeService::proporcaoPorSeveridade($db, $filtro);
$dadosDuracao = AnaliseSeveridadeService::duracaoMediaPorEvento($db, $filtro);

$labelsMunicipios = array_map('strval', array_column($rankingMunicipios, 'municipio'));
$valoresMunicipios = array_map('intval', array_column($rankingMunicipios, 'total_alertas'));

$labelsAlertasEvento = array_map('strval', array_column($dadosAlertasEvento, 'tipo_evento'));
$valoresAlertasEvento = array_map('intval', array_column($dadosAlertasEvento, 'total'));

$labelsSeveridade = array_map('strval', array_column($dadosSeveridade, 'nivel_gravidade'));
$valoresSeveridade = array_map('intval', array_column($dadosSeveridade, 'total'));

$labelsDuracao = array_map('strval', array_column($dadosDuracao, 'tipo_evento'));
$valoresDuracao = array_map(static function ($valor) {
    return round((float) $valor, 1);
}, array_column($dadosDuracao, 'duracao_media_horas'));
$quantidadeMunicipiosImpactados = count($rankingMunicipiosCompleto);
$quantidadeMunicipiosExibidos = count($labelsMunicipios);

$totalAlertasNoRecorte = array_sum($valoresSeveridade);
$faixasAtivas = count(array_filter($valoresSeveridade, static fn ($valor) => (int) $valor > 0));
$totalTiposEvento = count(array_filter($labelsAlertasEvento, static fn ($valor) => trim((string) $valor) !== ''));
$abrangenciaRegioes = $filtro['regiao'] !== null ? 1 : count($regioes);
$abrangenciaMunicipios = $filtro['municipio'] !== null ? 1 : $quantidadeMunicipiosImpactados;

$totaisPorSeveridade = [];
foreach ($dadosSeveridade as $item) {
    $nivel = (string) ($item['nivel_gravidade'] ?? '');
    $total = (int) ($item['total'] ?? 0);

    if ($nivel !== '') {
        $totaisPorSeveridade[$nivel] = $total;
    }
}
arsort($totaisPorSeveridade);
$severidadePrincipal = $totaisPorSeveridade !== [] ? (string) array_key_first($totaisPorSeveridade) : 'Sem dados';
$severidadePrincipalTotal = $totaisPorSeveridade !== [] ? (int) current($totaisPorSeveridade) : 0;

$totaisPorEvento = [];
foreach ($dadosAlertasEvento as $item) {
    $evento = (string) ($item['tipo_evento'] ?? '');
    $total = (int) ($item['total'] ?? 0);

    if ($evento !== '') {
        $totaisPorEvento[$evento] = $total;
    }
}
arsort($totaisPorEvento);
$eventoMaisRecorrente = $totaisPorEvento !== [] ? (string) array_key_first($totaisPorEvento) : 'Sem dados';
$eventoMaisRecorrenteTotal = $totaisPorEvento !== [] ? (int) current($totaisPorEvento) : 0;

$eventoMaiorDuracao = 'Sem dados';
$eventoMaiorDuracaoHoras = 0.0;
foreach ($dadosDuracao as $item) {
    $duracaoAtual = round((float) ($item['duracao_media_horas'] ?? 0), 1);

    if ($duracaoAtual > $eventoMaiorDuracaoHoras) {
        $eventoMaiorDuracaoHoras = $duracaoAtual;
        $eventoMaiorDuracao = (string) ($item['tipo_evento'] ?? 'Sem dados');
    }
}

$municipioMaisImpactado = $rankingMunicipios[0]['municipio'] ?? 'Sem dados';
$municipioMaisImpactadoTotal = (int) ($rankingMunicipios[0]['total_alertas'] ?? 0);

$filtrosResumo = [];

if ($filtro['ano'] !== null) {
    $filtrosResumo[] = 'Ano: ' . $filtro['ano'];
}

if ($filtro['mes'] !== null && isset($meses[$filtro['mes']])) {
    $filtrosResumo[] = 'Mes: ' . $meses[$filtro['mes']];
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
$operadorPerfil = trim((string) ($usuario['perfil'] ?? ($modoEmbedPublico ? 'Publico' : 'Nao informado')));
$quantidadeFaixas = count($labelsSeveridade);
$quantidadeEventosDuracao = count($labelsDuracao);
$resumoMunicipiosSecao7 = $exibirTopMunicipiosNacional
    ? 'Top 10 municipios mais afetados no recorte geral'
    : (string) $quantidadeMunicipiosExibidos . ' municipios no recorte filtrado';
$resumoExecutivo = [
    [
        'label' => 'Alertas no recorte',
        'value' => (int) $totalAlertasNoRecorte . ' alertas',
        'note' => 'Volume consolidado para o recorte temporal e territorial selecionado.',
        'tone' => 'primary',
    ],
    [
        'label' => 'Severidade predominante',
        'value' => $severidadePrincipal,
        'note' => $severidadePrincipalTotal > 0
            ? $severidadePrincipalTotal . ' alertas concentrados nesta faixa.'
            : 'Sem concentracao de severidade identificada no recorte atual.',
        'tone' => 'success',
    ],
    [
        'label' => 'Evento mais recorrente',
        'value' => $eventoMaisRecorrente,
        'note' => $eventoMaisRecorrenteTotal > 0
            ? $eventoMaisRecorrenteTotal . ' ocorrencias do tipo dominante.'
            : 'Sem recorrencia tipificada no periodo atual.',
        'tone' => 'neutral',
    ],
    [
        'label' => 'Base comparativa',
        'value' => (int) max(1, $faixasAtivas) . ' faixas / ' . (int) max(1, $totalTiposEvento) . ' eventos',
        'note' => $contextoTerritorial,
        'tone' => 'warning',
    ],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Analise de Severidade</title>
<link rel="icon" type="image/x-icon" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/logo.cbmpa.ico">

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/painel.css">
<link rel="stylesheet" href="/assets/css/pages/alertas-form.css">
<link rel="stylesheet" href="/assets/css/pages/usuarios-listar.css">
<link rel="stylesheet" href="/assets/css/pages/analises-severidade.css?v=<?= htmlspecialchars($versionCssSeveridade, ENT_QUOTES, 'UTF-8') ?>">
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
            'Analise de severidade' => null,
        ];
        include __DIR__ . '/../_breadcrumb.php';
        ?>
<?php endif; ?>

        <section class="dashboard alerta-form-shell usuarios-shell severidade-shell">
            <div class="usuarios-hero-grid severidade-hero-grid">
                <div class="alerta-form-hero usuarios-hero-panel severidade-hero-panel">
                    <div class="alerta-form-lead usuarios-hero-copy severidade-hero-copy">
                        <span class="alerta-form-kicker">Analise operacional</span>
                        <h1 class="alerta-form-title">Severidade e impacto dos alertas</h1>
                        <p class="alerta-form-description">
                            Consolide a distribuicao de gravidade, a duracao media dos eventos e o impacto territorial
                            no mesmo padrao visual aplicado na analise temporal.
                        </p>

                        <div class="usuarios-hero-chip-row severidade-hero-chip-row">
                            <span class="usuarios-hero-chip"><?= (int) $totalAlertasNoRecorte ?> alertas no recorte</span>
                            <span class="usuarios-hero-chip"><?= htmlspecialchars((string) $contextoTerritorial, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="usuarios-hero-chip"><?= htmlspecialchars($contextoPeriodo, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>

                        <div class="usuarios-hero-actions severidade-hero-actions">
                            <a href="#severidade-filtros" class="btn btn-primary">Aplicar filtros</a>
                            <a href="#severidade-graficos" class="btn btn-secondary">Ver graficos</a>
                            <?php if ($modoEmbedPublico): ?>
                                <a href="/index.php#analises-publicas" class="btn btn-secondary">Voltar para pagina inicial</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="usuarios-summary-grid severidade-summary-grid">
                    <?php foreach ($resumoExecutivo as $cardResumo): ?>
                        <article class="usuarios-summary-card usuarios-summary-card-<?= htmlspecialchars((string) ($cardResumo['tone'] ?? 'primary'), ENT_QUOTES, 'UTF-8') ?>">
                            <span class="usuarios-summary-label"><?= htmlspecialchars((string) ($cardResumo['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            <strong class="usuarios-summary-value"><?= htmlspecialchars((string) ($cardResumo['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                            <span class="usuarios-summary-note"><?= htmlspecialchars((string) ($cardResumo['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>

                <aside class="usuarios-command-card severidade-command-card">
                    <span class="usuarios-command-kicker">Comando de severidade</span>
                    <h2>Coordenacao da leitura de impacto</h2>
                    <p>
                        Use este painel para validar operador, foco territorial e prioridade analitica antes de navegar
                        pelas secoes de gravidade, duracao e impacto territorial.
                    </p>

                    <div class="usuarios-command-grid severidade-command-grid">
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
                            <strong>Da gravidade ao territorio</strong>
                            <small>Comece pela distribuicao de severidade e finalize com a pressao por municipio.</small>
                        </article>
                    </div>
                </aside>
            </div>

            <div class="alerta-form-panel usuarios-control-panel severidade-control-panel">
                <div class="usuarios-control-grid severidade-overview-grid">
                    <section id="severidade-filtros" class="alerta-form-section usuarios-filter-panel severidade-filter-panel">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Secao 1</span>
                            <h2 class="alerta-section-title">Filtros do recorte analitico</h2>
                            <p class="alerta-section-text">
                                Ajuste ano, mes, regiao de integracao e municipio para recalcular a leitura de severidade
                                e impacto com o mesmo fluxo das outras analises.
                            </p>
                        </header>

                        <form method="get" class="usuarios-filters severidade-filters severidade-filter-grid">
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
                                <label for="mes">Mes</label>
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

                            <div class="usuarios-filter-meta severidade-filter-meta form-group field-span-2">
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

                            <div class="alerta-callout severidade-filter-callout form-group field-span-2">
                                <strong>Atualizacao imediata</strong>
                                Ano, mes e regiao reaplicam o recorte automaticamente. O municipio e carregado de forma relacionada apos a selecao da regiao correspondente.
                            </div>

                            <div class="alerta-form-actions severidade-filter-actions usuarios-filter-actions form-group field-span-2">
                                <div class="alerta-form-actions-left">
                                    <span class="alerta-inline-note">Filtros aplicados: <?= htmlspecialchars($contextoPeriodo, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>

                                <div class="alerta-form-actions-right">
                                    <button type="button" class="btn btn-secondary" data-clear-filtros>Limpar filtros</button>
                                </div>
                            </div>
                        </form>
                    </section>

                    <section class="alerta-form-section usuarios-governance-panel severidade-governance-panel">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Secao 2</span>
                            <h2 class="alerta-section-title">Leituras rapidas do periodo</h2>
                            <p class="alerta-section-text">
                                Use os indicadores abaixo para identificar gravidade predominante, evento mais recorrente,
                                maior duracao media e o municipio com maior pressao operacional.
                            </p>
                        </header>

                        <div class="usuarios-insight-grid severidade-insight-grid">
                            <article class="usuarios-insight-card usuarios-insight-card-emphasis severidade-mini-card">
                                <span class="usuarios-insight-kicker">Severidade predominante</span>
                                <strong><?= htmlspecialchars($severidadePrincipal, ENT_QUOTES, 'UTF-8') ?></strong>
                                <p>
                                    <?= $severidadePrincipalTotal > 0 ? $severidadePrincipalTotal . ' alertas concentrados nessa faixa.' : 'Sem predominancia de severidade no recorte atual.' ?>
                                </p>
                            </article>

                            <article class="usuarios-insight-card severidade-mini-card">
                                <span class="usuarios-insight-kicker">Evento mais recorrente</span>
                                <strong><?= htmlspecialchars($eventoMaisRecorrente, ENT_QUOTES, 'UTF-8') ?></strong>
                                <p>
                                    <?= $eventoMaisRecorrenteTotal > 0 ? $eventoMaisRecorrenteTotal . ' ocorrencias deste tipo de evento.' : 'Sem recorrencia tipificada no periodo atual.' ?>
                                </p>
                            </article>

                            <article class="usuarios-insight-card severidade-mini-card">
                                <span class="usuarios-insight-kicker">Maior duracao media</span>
                                <strong><?= htmlspecialchars($eventoMaiorDuracao, ENT_QUOTES, 'UTF-8') ?></strong>
                                <p>
                                    <?= $eventoMaiorDuracaoHoras > 0 ? number_format($eventoMaiorDuracaoHoras, 1, ',', '.') . ' horas de media neste evento.' : 'Sem duracao media consolidada no recorte atual.' ?>
                                </p>
                            </article>

                            <article class="usuarios-insight-card severidade-mini-card">
                                <span class="usuarios-insight-kicker">Municipio mais impactado</span>
                                <strong><?= htmlspecialchars((string) $municipioMaisImpactado, ENT_QUOTES, 'UTF-8') ?></strong>
                                <p>
                                    <?= $municipioMaisImpactadoTotal > 0 ? $municipioMaisImpactadoTotal . ' alertas registrados neste municipio.' : 'Sem concentracao municipal identificada.' ?>
                                </p>
                            </article>
                        </div>

                        <div class="alerta-callout severidade-info-callout">
                            <strong>Como interpretar</strong>
                            Cruze a distribuicao de gravidade com a duracao media e os municipios mais impactados para
                            entender onde a operacao tende a ficar mais pressionada.
                        </div>

                        <div class="alerta-form-actions severidade-actions usuarios-filter-actions">
                            <div class="alerta-form-actions-left">
                                <span class="alerta-inline-note">Os paineis abaixo permanecem sincronizados com o mesmo recorte temporal e territorial.</span>
                            </div>

                            <?php if (!$modoEmbedPublico): ?>
                                <div class="alerta-form-actions-right severidade-action-buttons">
                                    <a href="/pages/analises/indice_risco.php" class="btn btn-secondary">Abrir indices</a>
                                    <a href="/pages/mapas/mapa_multirriscos.php" class="btn btn-secondary">Abrir mapa multirriscos</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </div>

            <section id="severidade-graficos" class="alerta-form-panel usuarios-table-panel severidade-chart-panel">
                <header class="usuarios-table-head severidade-section-head">
                    <div class="alerta-section-header">
                        <span class="alerta-section-kicker">Painel principal</span>
                        <h2 class="alerta-section-title">Distribuicao final de severidade</h2>
                        <p class="alerta-section-text">
                            Veja a quantidade absoluta por faixa de gravidade para identificar a concentracao operacional
                            do recorte selecionado.
                        </p>
                    </div>

                    <div class="usuarios-table-head-actions">
                        <span class="usuarios-result-chip"><?= (int) $quantidadeFaixas ?> faixas no grafico</span>
                    </div>
                </header>

                <div class="usuarios-table-toolbar severidade-chart-toolbar">
                    <div class="usuarios-table-toolbar-copy">
                        <strong>Recorte ativo:</strong>
                        <span><?= htmlspecialchars($contextoPeriodo, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <div class="usuarios-table-toolbar-pills">
                        <span class="usuarios-toolbar-pill"><?= (int) $totalAlertasNoRecorte ?> alertas contabilizados</span>
                        <span class="usuarios-toolbar-pill"><?= (int) max(1, $faixasAtivas) ?> faixas ativas</span>
                        <span class="usuarios-toolbar-pill"><?= (int) $abrangenciaRegioes ?> regioes / <?= (int) $abrangenciaMunicipios ?> municipios</span>
                    </div>
                </div>

                <div class="severidade-chart-card">
                    <div class="severidade-chart-stage">
                        <canvas class="severidade-chart-canvas" id="graficoDistribuicao"></canvas>
                    </div>
                </div>
            </section>

            <div class="alerta-form-panel usuarios-control-panel severidade-chart-panel">
                <div class="severidade-analytics-grid">
                    <section class="alerta-form-section severidade-chart-section">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Secao 4</span>
                            <h2 class="alerta-section-title">Proporcao por grau de severidade</h2>
                            <p class="alerta-section-text">
                                Compare o peso percentual de cada faixa para entender a composicao relativa da severidade.
                            </p>
                        </header>

                        <div class="severidade-chart-card">
                            <div class="severidade-chart-meta">
                                <span class="severidade-chart-chip"><?= (int) max(1, $faixasAtivas) ?> faixas ativas</span>
                                <span class="severidade-chart-chip">Leitura percentual do recorte</span>
                            </div>
                            <div class="severidade-chart-stage">
                                <canvas class="severidade-chart-canvas" id="graficoProporcao"></canvas>
                            </div>
                        </div>
                    </section>

                    <section class="alerta-form-section severidade-chart-section">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Secao 5</span>
                            <h2 class="alerta-section-title">Duracao media por tipo de evento</h2>
                            <p class="alerta-section-text">
                                Identifique quais eventos tendem a permanecer ativos por mais tempo na operacao.
                            </p>
                        </header>

                        <div class="severidade-chart-card severidade-chart-card--tall">
                            <div class="severidade-chart-meta">
                                <span class="severidade-chart-chip"><?= (int) $quantidadeEventosDuracao ?> eventos comparados</span>
                                <span class="severidade-chart-chip">Media calculada em horas</span>
                            </div>
                            <div class="severidade-chart-stage severidade-chart-stage--tall">
                                <canvas class="severidade-chart-canvas" id="graficoDuracao"></canvas>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div class="alerta-form-panel usuarios-control-panel severidade-chart-panel">
                <div class="severidade-analytics-grid">
                    <section class="alerta-form-section severidade-chart-section">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Secao 6</span>
                            <h2 class="alerta-section-title">Alertas emitidos por tipo de evento</h2>
                            <p class="alerta-section-text">
                                Acompanhe o volume de emissoes por tipologia no mesmo recorte aplicado aos outros graficos.
                            </p>
                        </header>

                        <div class="severidade-chart-card">
                            <div class="severidade-chart-meta">
                                <span class="severidade-chart-chip"><?= (int) max(1, $totalTiposEvento) ?> tipos de evento</span>
                                <span class="severidade-chart-chip">Leitura de emissao operacional</span>
                            </div>
                            <div class="severidade-chart-stage">
                                <canvas class="severidade-chart-canvas" id="graficoAlertasEvento"></canvas>
                            </div>
                        </div>
                    </section>

                    <section class="alerta-form-section severidade-chart-section">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Secao 7</span>
                            <h2 class="alerta-section-title">Municipios mais impactados</h2>
                            <p class="alerta-section-text">
                                Visualize onde os alertas se concentraram com maior intensidade dentro do recorte selecionado.
                            </p>
                        </header>

                        <div class="severidade-chart-card severidade-chart-card--tall">
                            <div class="severidade-chart-meta">
                                <span class="severidade-chart-chip"><?= (int) $quantidadeMunicipiosExibidos ?> municipios analisados</span>
                                <span class="severidade-chart-chip"><?= htmlspecialchars($resumoMunicipiosSecao7, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <div class="severidade-chart-stage severidade-chart-stage--tall">
                                <canvas class="severidade-chart-canvas" id="graficoMunicipiosImpactados"></canvas>
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

<script src="/assets/vendor/chartjs/chart-lite.js?v=<?= htmlspecialchars($versionChartLite, ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="/assets/js/pages/analises-common.js?v=<?= htmlspecialchars($versionJsCommon, ENT_QUOTES, 'UTF-8') ?>"></script>
<script nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>" id="analise-severidade-data" type="application/json"><?= json_encode([
    'labelsSeveridade' => $labelsSeveridade,
    'valoresSeveridade' => $valoresSeveridade,
    'labelsDuracao' => $labelsDuracao,
    'valoresDuracao' => $valoresDuracao,
    'labelsAlertasEvento' => $labelsAlertasEvento,
    'valoresAlertasEvento' => $valoresAlertasEvento,
    'labelsMunicipios' => $labelsMunicipios,
    'valoresMunicipios' => $valoresMunicipios,
    'municipioAtual' => $filtro['municipio'] ?? '',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="/assets/js/pages/analises-severidade.js?v=<?= htmlspecialchars($versionJsSeveridade, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
