<?php
require_once __DIR__ . '/../app/Core/Protect.php';
require_once __DIR__ . '/../app/Core/Database.php';

Protect::check(['ADMIN', 'GESTOR', 'ANALISTA', 'OPERACOES']);

$usuario = $_SESSION['usuario'];
$db = Database::getConnection();
$agoraLocal = TimeHelper::now();
$anoAtual = TimeHelper::currentYear();
$mesAtual = TimeHelper::currentMonth();

$totalAtivos = (int) $db->query("
    SELECT COUNT(*) FROM alertas WHERE status = 'ATIVO'
")->fetchColumn();

$stmtTotalExpirados = $db->prepare("
    SELECT COUNT(*) FROM alertas
    WHERE status = 'ATIVO'
      AND fim_alerta IS NOT NULL
      AND fim_alerta < :agora_local
");
$stmtTotalExpirados->execute([':agora_local' => $agoraLocal]);
$totalExpirados = (int) $stmtTotalExpirados->fetchColumn();

$stmtTotalMes = $db->prepare("
    SELECT COUNT(*) FROM alertas
    WHERE MONTH(data_alerta) = :mes_atual
      AND YEAR(data_alerta) = :ano_atual
");
$stmtTotalMes->execute([
    ':mes_atual' => $mesAtual,
    ':ano_atual' => $anoAtual,
]);
$totalMes = (int) $stmtTotalMes->fetchColumn();

$stmtTotalEncerrados = $db->prepare("
    SELECT COUNT(*)
    FROM alertas
    WHERE status = 'ENCERRADO'
      AND YEAR(data_alerta) = :ano_atual
");
$stmtTotalEncerrados->execute([':ano_atual' => $anoAtual]);
$totalEncerrados = (int) $stmtTotalEncerrados->fetchColumn();

$stmtTotalCancelados = $db->prepare("
    SELECT COUNT(*)
    FROM alertas
    WHERE status = 'CANCELADO'
      AND YEAR(data_alerta) = :ano_atual
");
$stmtTotalCancelados->execute([':ano_atual' => $anoAtual]);
$totalCancelados = (int) $stmtTotalCancelados->fetchColumn();

$gravidades = $db->query("
    SELECT nivel_gravidade, COUNT(*) total
    FROM alertas
    WHERE status = 'ATIVO'
    GROUP BY nivel_gravidade
")->fetchAll(PDO::FETCH_ASSOC);

$eventos = $db->query("
    SELECT tipo_evento, COUNT(*) total
    FROM alertas
    WHERE status = 'ATIVO'
    GROUP BY tipo_evento
    ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);

$labelsGravidade = array_column($gravidades, 'nivel_gravidade');
$dadosGravidade = array_map('intval', array_column($gravidades, 'total'));
$labelsEvento = array_column($eventos, 'tipo_evento');
$dadosEvento = array_map('intval', array_column($eventos, 'total'));

$totalTiposEventoAtivos = count($eventos);
$gravidadeDominante = 'Sem dados';
$gravidadeDominanteTotal = 0;

foreach ($gravidades as $item) {
    $totalItem = (int) ($item['total'] ?? 0);

    if ($totalItem > $gravidadeDominanteTotal) {
        $gravidadeDominante = (string) ($item['nivel_gravidade'] ?? 'Sem dados');
        $gravidadeDominanteTotal = $totalItem;
    }
}

$eventoPrincipal = $labelsEvento[0] ?? 'Sem dados';
$eventoPrincipalTotal = (int) ($dadosEvento[0] ?? 0);
$podeCadastrar = in_array($usuario['perfil'] ?? '', ['ADMIN', 'GESTOR', 'ANALISTA'], true);

$stmtMapa = $db->query("
    SELECT
        a.id,
        a.numero,
        a.tipo_evento,
        a.nivel_gravidade,
        a.data_alerta,
        a.inicio_alerta,
        a.fim_alerta,
        a.area_geojson,
        (
            SELECT COUNT(*)
            FROM alerta_municipios am
            WHERE am.alerta_id = a.id
        ) AS total_municipios
    FROM alertas a
    WHERE a.status = 'ATIVO'
      AND a.area_geojson IS NOT NULL
");

$geojsonAlertas = [
    'type' => 'FeatureCollection',
    'features' => [],
];

$totalAlertasMapeados = 0;

foreach ($stmtMapa as $alerta) {
    $geo = json_decode((string) $alerta['area_geojson'], true);

    if (!$geo || empty($geo['features'])) {
        continue;
    }

    $geometrias = [];

    foreach ($geo['features'] as $feature) {
        if (!empty($feature['geometry'])) {
            $geometrias[] = $feature['geometry'];
        }
    }

    if ($geometrias === []) {
        continue;
    }

    $totalAlertasMapeados++;

    foreach ($geometrias as $index => $geometry) {
        $geojsonAlertas['features'][] = [
            'type' => 'Feature',
            'id' => 'alerta_' . $alerta['id'] . '_' . $index,
            'geometry' => $geometry,
            'properties' => [
                'alerta_id' => (int) $alerta['id'],
                'numero' => $alerta['numero'],
                'evento' => $alerta['tipo_evento'],
                'gravidade' => $alerta['nivel_gravidade'],
                'status' => 'ATIVO',
                'data_alerta' => $alerta['data_alerta']
                    ? TimeHelper::formatDate($alerta['data_alerta'], '')
                    : null,
                'inicio_alerta' => $alerta['inicio_alerta']
                    ? TimeHelper::formatDateTime($alerta['inicio_alerta'], '')
                    : null,
                'fim_alerta' => $alerta['fim_alerta']
                    ? TimeHelper::formatDateTime($alerta['fim_alerta'], '')
                    : null,
                'total_municipios' => (int) $alerta['total_municipios'],
            ],
        ];
    }
}

$alertasSemArea = max(0, $totalAtivos - $totalAlertasMapeados);
$monitoramentoVigencia = $totalExpirados > 0
    ? $totalExpirados . ' alertas ativos exigem revisão de vigência imediata.'
    : 'Todos os alertas ativos estão dentro da vigência registrada.';
$cssBasePainelPath = __DIR__ . '/../assets/css/painel.css';
$cssAlertasFormPath = __DIR__ . '/../assets/css/pages/alertas-form.css';
$cssUsuariosListarPath = __DIR__ . '/../assets/css/pages/usuarios-listar.css';
$cssPainelPath = __DIR__ . '/../assets/css/pages/painel.css';
$cssBasePainelVersion = (string) ((int) @filemtime($cssBasePainelPath));
$cssAlertasFormVersion = (string) ((int) @filemtime($cssAlertasFormPath));
$cssUsuariosListarVersion = (string) ((int) @filemtime($cssUsuariosListarPath));
$cssPainelVersion = (string) ((int) @filemtime($cssPainelPath));
$chartLitePath = __DIR__ . '/../assets/vendor/chartjs/chart-lite.js';
$chartLiteVersion = (string) ((int) @filemtime($chartLitePath));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Painel - Sistema Inteligente Multirriscos</title>
<link rel="icon" type="image/x-icon" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/logo.cbmpa.ico">

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/painel.css?v=<?= htmlspecialchars($cssBasePainelVersion, ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="/assets/css/pages/alertas-form.css?v=<?= htmlspecialchars($cssAlertasFormVersion, ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="/assets/css/pages/usuarios-listar.css?v=<?= htmlspecialchars($cssUsuariosListarVersion, ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="/assets/css/pages/painel.css?v=<?= htmlspecialchars($cssPainelVersion, ENT_QUOTES, 'UTF-8') ?>">
</head>

<body>
<div class="layout">
    <?php include __DIR__ . '/_sidebar.php'; ?>

    <main class="content">
        <?php include __DIR__ . '/_topbar.php'; ?>

        <?php
        $breadcrumb = [
            'Painel operacional' => null,
        ];
        include __DIR__ . '/_breadcrumb.php';
        ?>

        <section class="dashboard alerta-form-shell usuarios-shell painel-shell">
            <div class="usuarios-hero-grid painel-hero-grid">
                <div class="alerta-form-hero usuarios-hero-panel painel-hero-panel">
                    <div class="alerta-form-lead usuarios-hero-copy painel-hero-copy">
                        <span class="alerta-form-kicker">Monitoramento operacional</span>
                        <h1 class="alerta-form-title">Painel operacional multirriscos</h1>
                        <p class="alerta-form-description">
                            Acompanhe o panorama atual dos alertas ativos, vigências, severidade e cobertura territorial
                            no mesmo layout novo aplicado nas demais telas de operação.
                        </p>

                        <div class="usuarios-hero-chip-row painel-hero-chip-row">
                            <span class="usuarios-hero-chip"><?= $totalAtivos ?> alertas ativos</span>
                            <span class="usuarios-hero-chip"><?= $totalAlertasMapeados ?> com área georreferenciada</span>
                            <span class="usuarios-hero-chip"><?= $totalTiposEventoAtivos ?> tipologias ativas</span>
                        </div>

                        <div class="usuarios-hero-actions painel-hero-actions">
                            <a href="#painel-indicadores" class="btn btn-primary">Indicadores</a>
                            <a href="#painel-mapa" class="btn btn-secondary">Mapa operacional</a>
                            <a href="#painel-analises" class="btn btn-secondary">Gráficos</a>
                        </div>
                    </div>
                </div>

                <div class="usuarios-summary-grid painel-summary-grid">
                    <article class="usuarios-summary-card usuarios-summary-card-primary">
                        <span class="usuarios-summary-label">Monitoramento ativo</span>
                        <strong class="usuarios-summary-value"><?= $totalAtivos ?> alertas</strong>
                        <span class="usuarios-summary-note">Base operacional em acompanhamento neste ambiente local do Wamp.</span>
                    </article>

                    <article class="usuarios-summary-card usuarios-summary-card-success">
                        <span class="usuarios-summary-label">Cobertura cartográfica</span>
                        <strong class="usuarios-summary-value"><?= $totalAlertasMapeados ?> mapeados</strong>
                        <span class="usuarios-summary-note">
                            <?= $alertasSemArea > 0 ? $alertasSemArea . ' alertas ativos ainda sem área georreferenciada.' : 'Todos os alertas ativos possuem área georreferenciada.' ?>
                        </span>
                    </article>

                    <article class="usuarios-summary-card usuarios-summary-card-neutral">
                        <span class="usuarios-summary-label">Gravidade dominante</span>
                        <strong class="usuarios-summary-value"><?= htmlspecialchars($gravidadeDominante, ENT_QUOTES, 'UTF-8') ?></strong>
                        <span class="usuarios-summary-note">
                            <?= $gravidadeDominanteTotal > 0 ? $gravidadeDominanteTotal . ' alertas ativos nesta faixa.' : 'Sem alertas ativos distribuídos por gravidade.' ?>
                        </span>
                    </article>

                    <article class="usuarios-summary-card usuarios-summary-card-warning">
                        <span class="usuarios-summary-label">Evento principal</span>
                        <strong class="usuarios-summary-value"><?= htmlspecialchars($eventoPrincipal, ENT_QUOTES, 'UTF-8') ?></strong>
                        <span class="usuarios-summary-note">
                            <?= $eventoPrincipalTotal > 0 ? $eventoPrincipalTotal . ' alertas ativos nesta tipologia.' : 'Sem evento predominante no momento.' ?>
                        </span>
                    </article>
                </div>

                <aside class="usuarios-command-card painel-command-card">
                    <span class="usuarios-command-kicker">Comando do painel</span>
                    <h2>Coordenação operacional</h2>
                    <p>
                        Use o painel para priorizar revisão de vigências, validar cobertura territorial no mapa e
                        direcionar a equipe para cadastro, listagem ou análises.
                    </p>

                    <div class="usuarios-command-grid painel-command-grid">
                        <article class="usuarios-command-item">
                            <span>Operador da sessão</span>
                            <strong><?= htmlspecialchars((string) ($usuario['nome'] ?? 'Não identificado'), ENT_QUOTES, 'UTF-8') ?></strong>
                            <small>Perfil atual: <?= htmlspecialchars((string) ($usuario['perfil'] ?? 'Não informado'), ENT_QUOTES, 'UTF-8') ?>.</small>
                        </article>

                        <article class="usuarios-command-item">
                            <span>Vigência</span>
                            <strong><?= htmlspecialchars($monitoramentoVigencia, ENT_QUOTES, 'UTF-8') ?></strong>
                            <small>Monitoramento automático baseado no horário local da operação.</small>
                        </article>

                        <article class="usuarios-command-item">
                            <span>Fluxo sugerido</span>
                            <strong>Mapear, priorizar e agir</strong>
                            <small>Valide o mapa, abra os alertas e avance para análises de apoio tático.</small>
                        </article>
                    </div>
                </aside>
            </div>

            <div id="painel-indicadores" class="alerta-form-panel usuarios-control-panel">
                <div class="alerta-form-grid painel-overview-grid">
                    <section class="alerta-form-section">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Seção 1</span>
                            <h2 class="alerta-section-title">Indicadores operacionais</h2>
                            <p class="alerta-section-text">
                                Consolide o cenário atual de alertas, vigências e histórico anual em uma leitura executiva imediata.
                            </p>
                        </header>

                        <div class="painel-kpi-grid">
                            <article class="painel-kpi-card is-active">
                                <span class="painel-kpi-label">Ativos agora</span>
                                <strong class="painel-kpi-value"><?= $totalAtivos ?></strong>
                                <span class="painel-kpi-note">Alertas em estado ativo cadastrados na base.</span>
                            </article>

                            <article class="painel-kpi-card is-warning">
                                <span class="painel-kpi-label">Vigência vencida</span>
                                <strong class="painel-kpi-value"><?= $totalExpirados ?></strong>
                                <span class="painel-kpi-note">Alertas ativos que precisam de revisão operacional.</span>
                            </article>

                            <article class="painel-kpi-card">
                                <span class="painel-kpi-label">Registros no mês</span>
                                <strong class="painel-kpi-value"><?= $totalMes ?></strong>
                                <span class="painel-kpi-note">Cadastros realizados no mês corrente.</span>
                            </article>

                            <article class="painel-kpi-card is-neutral">
                                <span class="painel-kpi-label">Encerrados no ano</span>
                                <strong class="painel-kpi-value"><?= $totalEncerrados ?></strong>
                                <span class="painel-kpi-note">Histórico anual de alertas finalizados.</span>
                            </article>

                            <article class="painel-kpi-card is-danger">
                                <span class="painel-kpi-label">Cancelados no ano</span>
                                <strong class="painel-kpi-value"><?= $totalCancelados ?></strong>
                                <span class="painel-kpi-note">Cancelamentos registrados ao longo do ano corrente.</span>
                            </article>
                        </div>

                        <div class="alerta-callout painel-callout">
                            <strong>Estado atual da vigência</strong>
                            <?= htmlspecialchars($monitoramentoVigencia, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </section>

                    <section class="alerta-form-section">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Seção 2</span>
                            <h2 class="alerta-section-title">Leituras rápidas e atalhos</h2>
                            <p class="alerta-section-text">
                                Use estes destaques para decidir o próximo passo entre listagem, cadastro, mapa detalhado e análises.
                            </p>
                        </header>

                        <div class="painel-insight-grid">
                            <article class="alerta-summary-card painel-mini-card">
                                <span class="alerta-summary-label">Gravidade dominante</span>
                                <span class="alerta-summary-value"><?= htmlspecialchars($gravidadeDominante, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="alerta-summary-note">
                                    <?= $gravidadeDominanteTotal > 0 ? $gravidadeDominanteTotal . ' alertas ativos nesta faixa.' : 'Sem alertas ativos distribuídos por gravidade.' ?>
                                </span>
                            </article>

                            <article class="alerta-summary-card painel-mini-card">
                                <span class="alerta-summary-label">Evento principal</span>
                                <span class="alerta-summary-value"><?= htmlspecialchars($eventoPrincipal, ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="alerta-summary-note">
                                    <?= $eventoPrincipalTotal > 0 ? $eventoPrincipalTotal . ' alertas ativos nesta tipologia.' : 'Sem evento predominante no momento.' ?>
                                </span>
                            </article>

                            <article class="alerta-summary-card painel-mini-card">
                                <span class="alerta-summary-label">Perfil conectado</span>
                                <span class="alerta-summary-value"><?= htmlspecialchars((string) ($usuario['perfil'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="alerta-summary-note">Os atalhos abaixo respeitam as permissões operacionais deste perfil.</span>
                            </article>
                        </div>

                        <div class="alerta-callout painel-callout">
                            <strong>Fluxo recomendado</strong>
                            Use o mapa abaixo para localizar rapidamente territórios em risco e siga para a lista de alertas quando precisar detalhar, editar ou exportar.
                        </div>

                        <div class="alerta-form-actions painel-actions">
                            <div class="alerta-form-actions-left">
                                <span class="alerta-inline-note">O painel funciona como porta de entrada para as rotinas operacionais do sistema.</span>
                            </div>

                            <div class="alerta-form-actions-right painel-action-buttons">
                                <a href="/pages/alertas/listar.php" class="btn btn-secondary">Ver alertas</a>
                                <a href="/pages/mapas/mapa_multirriscos.php" class="btn btn-secondary">Abrir mapa</a>
                                <a href="/pages/analises/index.php" class="btn btn-primary">Abrir análises</a>
                                <?php if ($podeCadastrar): ?>
                                    <a href="/pages/alertas/cadastrar.php" class="btn btn-secondary">Cadastrar alerta</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <section id="painel-mapa" class="alerta-form-panel usuarios-control-panel painel-map-panel">
                <header class="painel-section-head">
                    <div class="alerta-section-header">
                        <span class="alerta-section-kicker">Seção 3</span>
                        <h2 class="alerta-section-title">Mapa e cobertura territorial</h2>
                        <p class="alerta-section-text">
                            Selecione uma geometria para abrir o território do alerta no painel lateral e verificar regiões e municípios associados.
                        </p>
                    </div>

                    <div class="painel-map-meta">
                        <span class="painel-chip"><?= $totalAlertasMapeados ?> alertas com área</span>
                        <span class="painel-chip"><?= $alertasSemArea ?> sem área georreferenciada</span>
                    </div>
                </header>

                <div class="painel-map-layout">
                    <div class="map-card painel-map-card">
                        <div class="map-card-header">
                            <div>
                                <span class="map-card-title">Mapa operacional dos alertas ativos</span>
                                <p class="map-card-text">
                                    O painel mostra apenas alertas ativos com área válida. Clique no polígono para abrir o território e destacar a abrangência regional.
                                </p>
                            </div>
                        </div>

                        <div id="mapaPainel" class="alerta-map painel-map-canvas"></div>
                    </div>

                    <aside class="painel-side-stack">
                        <article class="alerta-summary-card painel-mini-card">
                            <span class="alerta-summary-label">Cobertura de mapa</span>
                            <span class="alerta-summary-value"><?= $totalAlertasMapeados ?> alertas</span>
                            <span class="alerta-summary-note">
                                <?= $alertasSemArea > 0 ? 'Há alertas ativos fora do mapa por ausência de geometria salva.' : 'Toda a operação ativa está refletida no mapa atual.' ?>
                            </span>
                        </article>

                        <article class="alerta-summary-card painel-mini-card">
                            <span class="alerta-summary-label">Território interativo</span>
                            <span class="alerta-summary-value">Clique no mapa</span>
                            <span class="alerta-summary-note">O painel lateral mostra evento, vigência, municípios afetados e distribuição regional.</span>
                        </article>

                        <div class="alerta-callout painel-side-note">
                            <strong>Dica operacional</strong>
                            Use este mapa para validar rapidamente se o alerta já possui geometria adequada antes de seguir para PDF, envio ou acompanhamento territorial.
                        </div>
                    </aside>
                </div>
            </section>

            <div id="painel-analises" class="alerta-form-panel usuarios-control-panel">
                <div class="alerta-form-grid painel-analytics-grid">
                    <section class="alerta-form-section">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Seção 4</span>
                            <h2 class="alerta-section-title">Distribuição por gravidade</h2>
                            <p class="alerta-section-text">
                                Visualize a concentração dos alertas ativos por nível de gravidade para identificar a carga de severidade do cenário atual.
                            </p>
                        </header>

                        <div class="grafico-card painel-chart-card">
                            <canvas id="graficoGravidade"></canvas>
                        </div>
                    </section>

                    <section class="alerta-form-section">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Seção 5</span>
                            <h2 class="alerta-section-title">Distribuição por evento</h2>
                            <p class="alerta-section-text">
                                Compare as tipologias ativas para identificar o tipo de evento predominante no monitoramento operacional.
                            </p>
                        </header>

                        <div class="grafico-card painel-chart-card">
                            <canvas id="graficoEvento"></canvas>
                        </div>
                    </section>
                </div>
            </div>
        </section>

        <?php include __DIR__ . '/_footer.php'; ?>
    </main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/assets/vendor/chartjs/chart-lite.js?v=<?= htmlspecialchars($chartLiteVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
<script id="painel-data" type="application/json"><?= json_encode([
    'geojsonAlertas' => $geojsonAlertas,
    'labelsGravidade' => $labelsGravidade,
    'dadosGravidade' => $dadosGravidade,
    'labelsEvento' => $labelsEvento,
    'dadosEvento' => $dadosEvento,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="/assets/js/pages/painel.js"></script>

<div id="drawer-territorio" class="drawer-territorio">
    <div class="drawer-header">
        <h3>Território do alerta</h3>
        <button onclick="fecharTerritorio()">X</button>
    </div>

    <div class="drawer-topo">
        <div class="alerta-numero" id="t-alerta-numero">
            Alerta -
        </div>

        <div class="alerta-dados">
            <div class="dado">
                <span class="rotulo">Tipo de evento</span>
                <span class="valor" id="t-evento">-</span>
            </div>

            <div class="dado">
                <span class="rotulo">Grau de gravidade</span>
                <span class="valor badge-gravidade" id="t-gravidade">-</span>
            </div>

            <div class="dado">
                <span class="rotulo">Data do alerta</span>
                <span class="valor" id="t-data">-</span>
            </div>

            <div class="dado">
                <span class="rotulo">Vigência</span>
                <span class="valor" id="t-vigencia">-</span>
            </div>

            <div class="dado destaque">
                <span class="rotulo">Municípios afetados</span>
                <span class="valor" id="t-total-municipios">-</span>
            </div>
        </div>

        <hr class="divisor">

        <h4 class="titulo-territorio">Regiões e municípios</h4>
    </div>

    <div id="conteudo-territorio" class="drawer-body"></div>
</div>

<div id="overlay-territorio" class="overlay-territorio" onclick="fecharTerritorio()"></div>
</body>
</html>
