<?php
require_once __DIR__ . '/../../app/Core/Protect.php';

Protect::check(['ADMIN', 'GESTOR', 'ANALISTA', 'OPERACOES']);

$usuario = $_SESSION['usuario'] ?? [];

$analises = [
    [
        'kicker' => 'Temporal',
        'titulo' => 'Análise temporal de alertas',
        'descricao' => 'Avalie sazonalidade, frequência por período do dia, comparativos mensais e evolução histórica dos alertas.',
        'nivel' => 'Operacional / Tático',
        'filtros' => 'Ano, mês, região, município e evento',
        'href' => '/pages/analises/temporal.php',
    ],
    [
        'kicker' => 'Severidade',
        'titulo' => 'Severidade e impacto',
        'descricao' => 'Consolide gravidade, duração média, volume por evento e concentração territorial dos alertas.',
        'nivel' => 'Operacional / Tático',
        'filtros' => 'Ano, mês, região e município',
        'href' => '/pages/analises/severidade.php',
    ],
    [
        'kicker' => 'Tipologia',
        'titulo' => 'Tipologia de eventos',
        'descricao' => 'Leia correlação com severidade, distribuição por região e recorrência municipal por tipo de evento.',
        'nivel' => 'Operacional',
        'filtros' => 'Ano, mês, região e município',
        'href' => '/pages/analises/tipologia.php',
    ],
    [
        'kicker' => 'Índices',
        'titulo' => 'Índices de risco (IRP / IPT)',
        'descricao' => 'Acompanhe a pressão operacional regional e territorial a partir dos indicadores sintéticos do sistema.',
        'nivel' => 'Estratégico',
        'filtros' => 'Ano, mês, região e município',
        'href' => '/pages/analises/indice_risco.php',
    ],
];

$totalModulos = count($analises);
$totalOperacionais = 0;
$totalEstrategicos = 0;
$filtrosCatalogo = [];

foreach ($analises as $analise) {
    $nivelAtual = strtoupper((string) ($analise['nivel'] ?? ''));

    if (strpos($nivelAtual, 'OPERACIONAL') !== false) {
        $totalOperacionais++;
    }

    if (strpos($nivelAtual, 'ESTRATEGICO') !== false) {
        $totalEstrategicos++;
    }

    $filtrosAnalise = trim((string) ($analise['filtros'] ?? ''));

    if ($filtrosAnalise !== '') {
        $filtrosCatalogo[] = $filtrosAnalise;
    }
}

$catalogoFiltros = implode(' | ', array_values(array_unique($filtrosCatalogo)));
$operadorNome = trim((string) ($usuario['nome'] ?? 'Não identificado'));
$operadorPerfil = trim((string) ($usuario['perfil'] ?? 'Não informado'));
$cssAnalisesIndexPath = __DIR__ . '/../../assets/css/pages/analises-index.css';
$cssAnalisesIndexVersion = (string) ((int) @filemtime($cssAnalisesIndexPath));
$jsAnaliseGlobalPath = __DIR__ . '/../../assets/js/analise-global.js';
$jsAnaliseGlobalVersion = (string) ((int) @filemtime($jsAnaliseGlobalPath));
$resumoExecutivo = [
    [
        'label' => 'Módulos analíticos',
        'value' => $totalModulos . ' painéis',
        'note' => 'Central única com análises temporal, severidade, tipologia e índices.',
        'tone' => 'primary',
    ],
    [
        'label' => 'Cobertura operacional',
        'value' => $totalOperacionais . ' frentes',
        'note' => 'Leituras aplicadas ao monitoramento tático e operacional.',
        'tone' => 'success',
    ],
    [
        'label' => 'Foco estratégico',
        'value' => $totalEstrategicos . ' módulo(s)',
        'note' => 'Índices sintéticos para priorização territorial e planejamento.',
        'tone' => 'neutral',
    ],
    [
        'label' => 'Recorte consolidado',
        'value' => 'Ano, mês, região e município',
        'note' => 'Mesmo conjunto de filtros para gerar o relatório analítico completo.',
        'tone' => 'warning',
    ],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Análises Multirriscos</title>
<link rel="icon" type="image/x-icon" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/logo.cbmpa.ico">

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/painel.css">
<link rel="stylesheet" href="/assets/css/pages/alertas-form.css">
<link rel="stylesheet" href="/assets/css/pages/usuarios-listar.css">
<link rel="stylesheet" href="/assets/css/pages/analises-index.css?v=<?= htmlspecialchars($cssAnalisesIndexVersion, ENT_QUOTES, 'UTF-8') ?>">
</head>

<body>
<div class="layout">
    <?php include __DIR__ . '/../_sidebar.php'; ?>

    <main class="content">
        <?php include __DIR__ . '/../_topbar.php'; ?>

        <?php
        $breadcrumb = [
            'Painel' => '/pages/painel.php',
            'Análises multirriscos' => null,
        ];
        include __DIR__ . '/../_breadcrumb.php';
        ?>

        <section class="dashboard alerta-form-shell usuarios-shell analises-shell">
            <div class="usuarios-hero-grid analises-hero-grid">
                <div class="alerta-form-hero usuarios-hero-panel analises-hero-panel">
                    <div class="alerta-form-lead usuarios-hero-copy analises-hero-copy">
                        <span class="alerta-form-kicker">Inteligência analítica</span>
                        <h1 class="alerta-form-title">Central de análises multirriscos</h1>
                        <p class="alerta-form-description">
                            Reúna leituras temporal, severidade, tipologia e índices em um único comando analítico.
                            Configure um recorte global para o relatório consolidado e aprofunde a investigação nos
                            painéis especializados mantendo o layout unificado das telas de usuários e histórico.
                        </p>

                        <div class="usuarios-hero-chip-row analises-hero-chip-row">
                            <span class="usuarios-hero-chip"><?= $totalModulos ?> módulos ativos</span>
                            <span class="usuarios-hero-chip"><?= $totalOperacionais ?> frentes operacionais</span>
                            <span class="usuarios-hero-chip">Relatório consolidado com exportação em PDF</span>
                        </div>

                        <div class="usuarios-hero-actions analises-hero-actions">
                            <a href="#analises-filtros" class="btn btn-primary">Configurar recorte global</a>
                            <a href="#analises-modulos" class="btn btn-secondary">Ver módulos de análise</a>
                        </div>
                    </div>
                </div>

                <div class="usuarios-summary-grid analises-summary-grid">
                    <?php foreach ($resumoExecutivo as $cardResumo): ?>
                        <article class="usuarios-summary-card usuarios-summary-card-<?= htmlspecialchars((string) ($cardResumo['tone'] ?? 'primary'), ENT_QUOTES, 'UTF-8') ?>">
                            <span class="usuarios-summary-label"><?= htmlspecialchars((string) ($cardResumo['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            <strong class="usuarios-summary-value"><?= htmlspecialchars((string) ($cardResumo['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                            <span class="usuarios-summary-note"><?= htmlspecialchars((string) ($cardResumo['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>

                <aside class="usuarios-command-card analises-command-card">
                    <span class="usuarios-command-kicker">Comando analítico</span>
                    <h2>Coordenação da central multirriscos</h2>
                    <p>
                        Este painel conecta o operador da sessão, o recorte consolidado e a navegação entre os módulos
                        para acelerar a leitura estratégica e a tomada de decisão.
                    </p>

                    <div class="usuarios-command-grid analises-command-grid">
                        <article class="usuarios-command-item">
                            <span>Operador da sessão</span>
                            <strong><?= htmlspecialchars($operadorNome, ENT_QUOTES, 'UTF-8') ?></strong>
                            <small>Perfil atual: <?= htmlspecialchars($operadorPerfil, ENT_QUOTES, 'UTF-8') ?>.</small>
                        </article>

                        <article class="usuarios-command-item">
                            <span>Recorte consolidado</span>
                            <strong>Ano, mês, região e município</strong>
                            <small>Mesmo padrão de filtros para gerar o relatório analítico integrado.</small>
                        </article>

                        <article class="usuarios-command-item">
                            <span>Prioridade sugerida</span>
                            <strong>Do geral ao específico</strong>
                            <small>Comece pelo relatório global e avance para os painéis especializados.</small>
                        </article>
                    </div>
                </aside>
            </div>

            <div class="alerta-form-panel usuarios-control-panel analises-control-panel">
                <div class="usuarios-control-grid analises-overview-grid">
                    <section id="analises-filtros" class="alerta-form-section usuarios-filter-panel analises-filter-panel">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Consulta consolidada</span>
                            <h2 class="alerta-section-title">Recorte global do relatório analítico</h2>
                            <p class="alerta-section-text">
                                Configure o recorte desejado para abrir o relatório consolidado do sistema com a mesma base de
                                ano, mês, região de integração e município.
                            </p>
                        </header>

                        <form class="usuarios-filters analises-filters" onsubmit="return false;">
                            <div class="usuarios-filter-grid analises-filter-grid">
                                <div class="form-group">
                                    <label for="filtroAno">Ano</label>
                                    <select id="filtroAno"></select>
                                </div>

                                <div class="form-group">
                                    <label for="filtroMes">Mês</label>
                                    <select id="filtroMes"></select>
                                </div>

                                <div class="form-group">
                                    <label for="filtroRegiao">Região de integração</label>
                                    <select id="filtroRegiao"></select>
                                </div>

                                <div class="form-group">
                                    <label for="filtroMunicipio">Município</label>
                                    <select id="filtroMunicipio">
                                        <option value="">Todos</option>
                                    </select>
                                </div>
                            </div>

                            <div class="usuarios-filter-meta analises-filter-meta">
                                <span class="usuarios-filter-meta-label">Recorte consolidado</span>
                                <div class="usuarios-filter-pill-row">
                                    <span class="usuarios-filter-pill">Ano, mês, região e município</span>
                                    <span class="usuarios-filter-pill is-neutral">
                                        O relatório integra temporal, severidade, tipologia e índices.
                                    </span>
                                </div>
                            </div>

                            <div class="alerta-callout analises-filter-callout">
                                <strong>Relatório consolidado</strong>
                                O botão abaixo usa este recorte para montar a síntese analítica integrada e liberar a exportação em PDF.
                            </div>

                            <div class="alerta-form-actions usuarios-filter-actions analises-filter-actions">
                                <div class="alerta-form-actions-left">
                                    <span class="alerta-inline-note">
                                        Use a seleção territorial para gerar um panorama consolidado antes de entrar nos painéis detalhados.
                                    </span>
                                </div>

                                <div class="alerta-form-actions-right">
                                    <button id="btnGerarRelatorio" type="button" class="btn btn-primary">
                                        Gerar relatório analítico
                                    </button>
                                </div>
                            </div>
                        </form>
                    </section>

                    <section class="alerta-form-section usuarios-governance-panel analises-governance-panel">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Leitura executiva</span>
                            <h2 class="alerta-section-title">Como usar esta central</h2>
                            <p class="alerta-section-text">
                                Combine o relatório integrado com os painéis especializados para aprofundar a leitura
                                conforme a necessidade operacional.
                            </p>
                        </header>

                        <div class="usuarios-insight-grid analises-insight-grid">
                            <article class="usuarios-insight-card usuarios-insight-card-emphasis">
                                <span class="usuarios-insight-kicker">Visão integrada</span>
                                <strong>Relatório único</strong>
                                <p>Resume temporal, severidade, tipologia, municípios impactados e índices no mesmo fluxo.</p>
                            </article>

                            <article class="usuarios-insight-card">
                                <span class="usuarios-insight-kicker">Aprofundamento</span>
                                <strong><?= $totalModulos ?> paineis especializados</strong>
                                <p>Cada módulo segue o layout novo para leitura rápida, consistente e responsiva.</p>
                            </article>

                            <article class="usuarios-insight-card">
                                <span class="usuarios-insight-kicker">Filtros priorizados</span>
                                <strong><?= htmlspecialchars($catalogoFiltros, ENT_QUOTES, 'UTF-8') ?></strong>
                                <p>Padrão único de recorte para manter comparabilidade entre os módulos.</p>
                            </article>

                            <article class="usuarios-insight-card analises-insight-card-action">
                                <span class="usuarios-insight-kicker">Navegação rápida</span>
                                <strong>Do geral ao específico</strong>
                                <p>Comece pelo consolidado e aprofunde nos painéis mais aderentes ao contexto territorial.</p>
                                <div class="analises-action-buttons">
                                    <a href="/pages/analises/temporal.php" class="btn btn-secondary">Abrir temporal</a>
                                    <a href="/pages/analises/indice_risco.php" class="btn btn-secondary">Abrir índices</a>
                                </div>
                            </article>
                        </div>

                        <div class="alerta-callout analises-info-callout">
                            <strong>Leitura orientada</strong>
                            Use a análise temporal para identificar janelas críticas, a severidade para medir carga operacional,
                            a tipologia para reconhecer padrões de evento e os índices para priorizar territórios.
                        </div>
                    </section>
                </div>
            </div>

            <section id="analises-modulos" class="alerta-form-panel usuarios-table-panel analises-modules-panel">
                <header class="usuarios-table-head analises-section-head">
                    <div class="alerta-section-header">
                        <span class="alerta-section-kicker">Catálogo analítico</span>
                        <h2 class="alerta-section-title">Módulos de análise disponíveis</h2>
                        <p class="alerta-section-text">
                            Navegue pelos painéis especializados mantendo o mesmo padrão de layout,
                            responsividade e leitura operacional.
                        </p>
                    </div>

                    <div class="usuarios-table-head-actions">
                        <span class="usuarios-result-chip"><?= $totalModulos ?> módulos prontos</span>
                        <a href="/pages/mapas/mapa_multirriscos.php" class="btn btn-secondary">Abrir mapa multirriscos</a>
                    </div>
                </header>

                <div class="usuarios-table-toolbar analises-table-toolbar">
                    <div class="usuarios-table-toolbar-copy">
                        <strong>Recorte recomendado:</strong>
                        <span>Gere o consolidado e avance para o módulo de maior aderência ao cenário atual.</span>
                    </div>

                    <div class="usuarios-table-toolbar-pills">
                        <span class="usuarios-toolbar-pill"><?= $totalOperacionais ?> operacionais</span>
                        <span class="usuarios-toolbar-pill"><?= $totalEstrategicos ?> estratégico(s)</span>
                        <span class="usuarios-toolbar-pill">Layout unificado</span>
                    </div>
                </div>

                <div class="analises-grid">
                    <?php foreach ($analises as $analise): ?>
                        <article class="analises-card">
                            <span class="analises-card-kicker"><?= htmlspecialchars($analise['kicker'], ENT_QUOTES, 'UTF-8') ?></span>
                            <h3 class="analises-card-title"><?= htmlspecialchars($analise['titulo'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <p class="analises-card-text"><?= htmlspecialchars($analise['descricao'], ENT_QUOTES, 'UTF-8') ?></p>

                            <div class="analises-card-meta">
                                <span class="analises-chip">Nível: <?= htmlspecialchars($analise['nivel'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="analises-chip">Filtros: <?= htmlspecialchars($analise['filtros'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>

                            <div class="analises-card-actions">
                                <a href="<?= htmlspecialchars($analise['href'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">Acessar análise</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </section>

        <?php include __DIR__ . '/../_footer.php'; ?>
    </main>
</div>

<div id="modalRelatorio" class="modal" style="display:none;">
    <div class="modal-content analises-modal-content">
        <div class="modal-header analises-modal-header">
            <div>
                <span class="analises-modal-kicker">Relatório consolidado</span>
                <h2>Relatório analítico multirriscos</h2>
            </div>
            <button id="fecharModal" type="button" class="analises-modal-close" aria-label="Fechar relatório">&times;</button>
        </div>

        <div id="conteudoRelatorio" class="analises-modal-body"></div>

        <div class="modal-footer analises-modal-footer">
            <button id="btnBaixarPDF" type="button" class="btn btn-primary">
                Baixar em PDF
            </button>
        </div>
    </div>
</div>

<script src="/assets/js/analise-global.js?v=<?= htmlspecialchars($jsAnaliseGlobalVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
