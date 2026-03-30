<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Core/AppConfig.php';
require_once __DIR__ . '/../../app/Helpers/AlertaFormHelper.php';
require_once __DIR__ . '/../../app/Helpers/PaginationHelper.php';
require_once __DIR__ . '/../../app/Services/HistoricoService.php';

Protect::check(['ADMIN','GESTOR','ANALISTA','OPERACOES']);

$usuario = $_SESSION['usuario'];
$db = Database::getConnection();
$appConfig = AppConfig::get();

$versionBase = rawurlencode((string) ($appConfig['version'] ?? '1.0.0'));
$cssAlertasListarPath = __DIR__ . '/../../assets/css/pages/alertas-listar.css';
$jsAlertasListarPath = __DIR__ . '/../../assets/js/pages/alertas-listar.js';

$versionCssAlertasListar = $versionBase
    . '.' . ((int) @filemtime($cssAlertasListarPath))
    . '.' . substr((string) @md5_file($cssAlertasListarPath), 0, 8);
$versionJsAlertasListar = $versionBase
    . '.' . ((int) @filemtime($jsAlertasListarPath))
    . '.' . substr((string) @md5_file($jsAlertasListarPath), 0, 8);

/* Define menu ativo */
$menuAtivo = 'alertas';





/* =========================
   FILTROS
========================= */
$eventosDisponiveis = AlertaFormHelper::eventos();
$somenteVigentes = isset($_GET['vigentes']) && $_GET['vigentes'] == 1;
$filtroGravidade = trim((string) ($_GET['gravidade'] ?? ''));
$filtroEvento = AlertaFormHelper::normalizeExistingOption($_GET['evento'] ?? null, $eventosDisponiveis) ?? '';
$dataInicio = trim((string) ($_GET['data_inicio'] ?? ''));
$dataFim    = trim((string) ($_GET['data_fim'] ?? ''));




/* =========================
   WHERE DINÂMICO
========================= */
$where  = [];
$params = [];

if ($somenteVigentes) {
    $where[] = "a.status = 'ATIVO'";
}

if ($filtroGravidade) {
    $where[] = 'a.nivel_gravidade = :gravidade';
    $params[':gravidade'] = $filtroGravidade;
}

if ($filtroEvento !== '') {
    $where[] = 'a.tipo_evento = :evento';
    $params[':evento'] = $filtroEvento;
}

/* Filtro de data com logica correta de vigencia */
if (!empty($dataInicio)) {
    $where[] = 'DATE(a.inicio_alerta) <= :dataInicio';
    $params[':dataInicio'] = $dataInicio;
}

if (!empty($dataFim)) {
    $where[] = 'DATE(COALESCE(a.fim_alerta, :agoraLocalFiltro)) >= :dataFim';
    $params[':agoraLocalFiltro'] = TimeHelper::now('Y-m-d H:i:s');
    $params[':dataFim'] = $dataFim;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* =========================
   QUERY BASE PARA PAGINAÇÃO
========================= */
$queryBase = [
    'vigentes'    => $somenteVigentes ? 1 : null,
    'gravidade'   => $filtroGravidade ?: null,
    'evento'      => $filtroEvento ?: null,
    'data_inicio' => $dataInicio ?: null,
    'data_fim'    => $dataFim ?: null
];


/* =========================
   PAGINAÇÃO
========================= */
$limite = 20;
$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$offset = ($pagina - 1) * $limite;



/* =========================
   CONTADOR VIGENTES
========================= */
$totalVigentes = $db->query("
    SELECT COUNT(*) FROM alertas WHERE status = 'ATIVO'
")->fetchColumn();

/* =========================
   EXPORTAÇÃO CSV
========================= */
/* =========================
   EXPORTAÇÃO CSV (CORRIGIDA)
========================= */
if (isset($_GET['exportar']) && $_GET['exportar'] === 'csv') {
    $db->exec("SET SESSION group_concat_max_len = 8192");

    $stmtCsv = $db->prepare("
    SELECT 
        a.numero,
        a.tipo_evento,
        a.nivel_gravidade,
        a.status,
        a.data_alerta,
        a.inicio_alerta,
        a.fim_alerta,
        a.riscos AS riscos_potenciais,
        a.recomendacoes,
        (
            SELECT GROUP_CONCAT(DISTINCT ar.regiao_integracao ORDER BY ar.regiao_integracao SEPARATOR ', ')
            FROM alerta_regioes ar
            WHERE ar.alerta_id = a.id
        ) AS regioes_integracao,
        (
            SELECT GROUP_CONCAT(DISTINCT am.municipio_nome ORDER BY am.municipio_nome SEPARATOR ', ')
            FROM alerta_municipios am
            WHERE am.alerta_id = a.id
        ) AS municipios,
        a.informacoes AS imagem_anexada,
        a.kml_arquivo AS kml_anexado,
        a.imagem_mapa AS imagem_mapa_gerada
    FROM alertas a
    " . ($whereSql
        ? $whereSql . " AND a.status <> 'CANCELADO'"
        : "WHERE a.status <> 'CANCELADO'") . "
    ORDER BY a.criado_em DESC
");

    foreach ($params as $k => $v) {
        $stmtCsv->bindValue($k, $v);
    }

    $stmtCsv->execute();
    
    /*=========================
       REGISTRA HISTÓRICO (CSV)
    ========================= */
    $filtrosRef = [];
    
    if ($somenteVigentes) {
        $filtrosRef[] = 'Somente ativos';
    }
    if ($filtroGravidade) {
        $filtrosRef[] = "Gravidade: $filtroGravidade";
    }
    if ($filtroEvento) {
        $filtrosRef[] = "Evento: $filtroEvento";
    }
    if ($dataInicio) {
        $filtrosRef[] = "Data início: $dataInicio";
    }
    if ($dataFim) {
        $filtrosRef[] = "Data fim: $dataFim";
    }
    
    HistoricoService::registrar(
        $usuario['id'],
        $usuario['nome'],
        'BAIXAR_CSV',
        'Baixou listagem de alertas em CSV',
        $filtrosRef ? implode(' | ', $filtrosRef) : 'Sem filtros'
    );


    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename=alertas.csv');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');

    // BOM para Excel
    fprintf($out, "\xEF\xBB\xBF");

    fputcsv($out, [
        'Número',
        'Evento',
        'Gravidade',
        'Status',
        'Data do Alerta',
        'Início da Vigência',
        'Fim da Vigência',
        'Riscos Potenciais',
        'Recomendações',
        'Regiões de Integração',
        'Municípios',
        'Imagem Anexada',
        'KML Anexado',
        'Imagem do Mapa Gerada'
    ]);

    while ($row = $stmtCsv->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [
            $row['numero'] ?? '',
            $row['tipo_evento'] ?? '',
            $row['nivel_gravidade'] ?? '',
            $row['status'] ?? '',
            TimeHelper::formatDate($row['data_alerta'] ?? null, ''),
            TimeHelper::formatDateTime($row['inicio_alerta'] ?? null, ''),
            TimeHelper::formatDateTime($row['fim_alerta'] ?? null, ''),
            $row['riscos_potenciais'] ?? '',
            $row['recomendacoes'] ?? '',
            $row['regioes_integracao'] ?? '',
            $row['municipios'] ?? '',
            $row['imagem_anexada'] ?? '',
            $row['kml_anexado'] ?? '',
            $row['imagem_mapa_gerada'] ?? '',
        ]);
    }

    fclose($out);
    exit;
}


/* =========================
   TOTAL DE ALERTAS
========================= */
$stmtTotal = $db->prepare("SELECT COUNT(*) FROM alertas a $whereSql");
$stmtTotal->execute($params);
$totalAlertas = $stmtTotal->fetchColumn();
$totalPaginas = ceil($totalAlertas / $limite);

/* =========================
   ALERTAS DA PÁGINA
========================= */
$stmt = $db->prepare("
    SELECT 
    a.id,
    a.numero,
    a.tipo_evento,
    a.nivel_gravidade,
    a.status,
    a.data_alerta,
    a.inicio_alerta,
    a.fim_alerta,
    a.imagem_mapa,
    a.alerta_enviado_compdec,
    a.data_envio_compdec,
    a.motivo_cancelamento,
    a.data_cancelamento,
    a.usuario_cancelamento,
    u.nome AS usuario_cancelou
    FROM alertas a
    LEFT JOIN usuarios u ON u.id = a.usuario_cancelamento
    $whereSql
    ORDER BY a.criado_em DESC
    LIMIT :limite OFFSET :offset
");



foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalEmTela = count($alertas);
$inicioRegistro = $totalAlertas > 0 ? $offset + 1 : 0;
$fimRegistro = $totalAlertas > 0 ? $offset + $totalEmTela : 0;
$totalPaginasExibicao = max(1, (int) $totalPaginas);
$podeCriarEditar = in_array($usuario['perfil'], ['ADMIN', 'GESTOR', 'ANALISTA'], true);
$podeEncerrarCancelar = in_array($usuario['perfil'], ['ADMIN', 'GESTOR'], true);
$podeAtivarEncerrado = ($usuario['perfil'] ?? '') === 'ADMIN';

$filtrosAplicados = [];

if ($somenteVigentes) {
    $filtrosAplicados[] = 'Somente ativos';
}

if ($filtroGravidade !== '') {
    $filtrosAplicados[] = 'Gravidade: ' . $filtroGravidade;
}

if ($filtroEvento !== '') {
    $filtrosAplicados[] = 'Evento: ' . $filtroEvento;
}

if ($dataInicio !== '') {
    $filtrosAplicados[] = 'A partir de: ' . $dataInicio;
}

if ($dataFim !== '') {
    $filtrosAplicados[] = 'Até: ' . $dataFim;
}

$filtrosResumo = $filtrosAplicados !== []
    ? implode(' | ', $filtrosAplicados)
    : 'Sem filtros adicionais';
$operadorNome = trim((string) ($usuario['nome'] ?? 'Não identificado'));
$operadorPerfil = trim((string) ($usuario['perfil'] ?? 'Não informado'));
$resumoFaixa = $totalAlertas > 0
    ? "Exibindo {$inicioRegistro} a {$fimRegistro} nesta página."
    : 'Nenhum alerta localizado com o filtro atual.';

$csvQueryString = http_build_query(array_filter(
    array_merge($queryBase, ['exportar' => 'csv']),
    static fn ($value) => $value !== null && $value !== ''
));

/* =========================
   STATUS
========================= */
function statusAlerta(array $a): array
{
    if (!isset($a['status'])) {
        return ['texto' => '—', 'classe' => ''];
    }

    return match ($a['status']) {
        'ATIVO'     => ['texto' => 'ATIVO',     'classe' => 'status-vigente'],
        'ENCERRADO' => ['texto' => 'ENCERRADO', 'classe' => 'status-encerrado'],
        'CANCELADO' => ['texto' => 'CANCELADO', 'classe' => 'status-cancelado'],
        default     => ['texto' => '—', 'classe' => ''],
    };
}
/* =========================
   STATUS VIGENTE
========================= */
function vigenciaAlerta(array $a): string
{
    if (empty($a['inicio_alerta']) && empty($a['fim_alerta'])) {
        return '—';
    }

    $inicio = TimeHelper::formatDateTime($a['inicio_alerta'] ?? null);
    $fim = TimeHelper::formatDateTime($a['fim_alerta'] ?? null);

    $expirado = (
        $a['status'] === 'ATIVO' &&
        TimeHelper::isPastLocal($a['fim_alerta'] ?? null)
    );

    if ($expirado) {
       return "<span class='vigencia-expirada'>{$inicio} até {$fim}</span>";
    }

    return "{$inicio} até {$fim}";
}

function paginacaoProfissional(int $paginaAtual, int $totalPaginas): array
{
    return PaginationHelper::marcadoresCompactos($paginaAtual, $totalPaginas);
}


?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Alertas</title>
<link rel="icon" type="image/x-icon" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/logo.cbmpa.ico">

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/painel.css">
<link rel="stylesheet" href="/assets/css/alertas-lista.css">
<link rel="stylesheet" href="/assets/css/pages/alertas-form.css">
<link rel="stylesheet" href="/assets/css/pages/usuarios-listar.css">
<link rel="stylesheet" href="/assets/css/pages/alertas-listar.css?v=<?= htmlspecialchars($versionCssAlertasListar, ENT_QUOTES, 'UTF-8') ?>">

</head>

<body>
<div class="layout">

 <!-- MENU LATERAL -->
<?php include __DIR__ . '/../_sidebar.php'; ?> 
   

    <!-- CONTEÚDO -->
    <main class="content">

        <!-- CABEÇALHO -->
        <?php include __DIR__ . '/../_topbar.php'; ?>
        <?php
        $breadcrumb = [
            'Painel' => '/pages/painel.php',
            'Alertas cadastrados' => null,
        ];
        include __DIR__ . '/../_breadcrumb.php';
        ?>

        <section class="dashboard alerta-form-shell usuarios-shell alerta-lista-shell">
            <div class="usuarios-hero-grid alerta-lista-hero-grid">
                <div class="alerta-form-hero usuarios-hero-panel alerta-lista-hero-panel">
                    <div class="alerta-form-lead usuarios-hero-copy alerta-lista-hero-copy">
                        <span class="alerta-form-kicker">Monitoramento operacional</span>
                        <h1 class="alerta-form-title">Alertas cadastrados</h1>
                        <p class="alerta-form-description">
                            Consulte a base operacional de alertas, aplique filtros por gravidade e vigência e siga para as
                            ações de detalhe, edição, encerramento, cancelamento, envio e exportação no mesmo padrão visual das telas mais recentes.
                        </p>

                        <div class="usuarios-hero-chip-row alerta-lista-hero-chip-row">
                            <span class="usuarios-hero-chip"><?= (int) $totalAlertas ?> alertas no recorte</span>
                            <span class="usuarios-hero-chip"><?= (int) $totalVigentes ?> alertas ativos</span>
                            <span class="usuarios-hero-chip"><?= htmlspecialchars($filtrosResumo, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>

                        <div class="usuarios-hero-actions alerta-lista-hero-actions">
                            <a href="#alerta-lista-filtros" class="btn btn-primary">Aplicar filtros</a>
                            <a href="#alerta-lista-tabela" class="btn btn-secondary">Ver lista</a>
                        </div>
                    </div>
                </div>

                <div class="usuarios-summary-grid alerta-lista-summary-grid">
                    <article class="usuarios-summary-card usuarios-summary-card-primary">
                        <span class="usuarios-summary-label">Resultado atual</span>
                        <strong class="usuarios-summary-value"><?= (int) $totalAlertas ?> alertas</strong>
                        <span class="usuarios-summary-note"><?= htmlspecialchars($resumoFaixa, ENT_QUOTES, 'UTF-8') ?></span>
                    </article>

                    <article class="usuarios-summary-card usuarios-summary-card-success">
                        <span class="usuarios-summary-label">Monitoramento ativo</span>
                        <strong class="usuarios-summary-value"><?= (int) $totalVigentes ?> alertas ativos</strong>
                        <span class="usuarios-summary-note">Indicador geral da base local usada nesta homologação do Wamp.</span>
                    </article>

                    <article class="usuarios-summary-card usuarios-summary-card-neutral">
                        <span class="usuarios-summary-label">Contexto</span>
                        <strong class="usuarios-summary-value">Página <?= (int) $pagina ?> de <?= (int) $totalPaginasExibicao ?></strong>
                        <span class="usuarios-summary-note"><?= htmlspecialchars($filtrosResumo, ENT_QUOTES, 'UTF-8') ?></span>
                    </article>

                    <article class="usuarios-summary-card usuarios-summary-card-warning">
                        <span class="usuarios-summary-label">Cobertura da consulta</span>
                        <strong class="usuarios-summary-value"><?= (int) $totalEmTela ?> alerta(s) em tela</strong>
                        <span class="usuarios-summary-note">Resultados paginados com filtros reaproveitados na exportação CSV.</span>
                    </article>
                </div>

                <aside class="usuarios-command-card alerta-lista-command-card">
                    <span class="usuarios-command-kicker">Comando de alertas</span>
                    <h2>Coordenação da fila operacional</h2>
                    <p>
                        Use este painel para validar operador, recorte ativo e fluxo recomendado antes de executar as
                        ações de envio, edição, encerramento, cancelamento e exportação.
                    </p>

                    <div class="usuarios-command-grid alerta-lista-command-grid">
                        <article class="usuarios-command-item">
                            <span>Operador da sessão</span>
                            <strong><?= htmlspecialchars($operadorNome, ENT_QUOTES, 'UTF-8') ?></strong>
                            <small>Perfil atual: <?= htmlspecialchars($operadorPerfil, ENT_QUOTES, 'UTF-8') ?>.</small>
                        </article>

                        <article class="usuarios-command-item">
                            <span>Recorte ativo</span>
                            <strong><?= htmlspecialchars($filtrosResumo, ENT_QUOTES, 'UTF-8') ?></strong>
                            <small>Filtros aplicados para tabela, ações e exportação.</small>
                        </article>

                        <article class="usuarios-command-item">
                            <span>Prioridade sugerida</span>
                            <strong>Filtrar, agir e registrar</strong>
                            <small>Filtre a base, execute as ações no registro e exporte o consolidado quando necessário.</small>
                        </article>
                    </div>
                </aside>
            </div>

            <?php if (isset($_GET['erro']) && $_GET['erro'] === 'inmet_duplicado'): ?>
                <div class="alerta-callout alerta-lista-callout-alert">
                    <strong>Alerta do INMET já importado</strong>
                    O aviso oficial informado já existe nesta base local.
                    <?php if (!empty($_GET['numero'])): ?>
                        Número relacionado: <?= htmlspecialchars((string) $_GET['numero'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="alerta-form-panel usuarios-control-panel alerta-lista-control-panel">
                <div class="usuarios-control-grid alerta-lista-overview-grid">
                    <section id="alerta-lista-filtros" class="alerta-form-section usuarios-filter-panel alerta-lista-filter-panel">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Seção 1</span>
                            <h2 class="alerta-section-title">Filtros e localização</h2>
                            <p class="alerta-section-text">
                                Refine a consulta por gravidade e vigência. Os filtros abaixo permanecem compatíveis com a exportação e com a navegação paginada.
                            </p>
                        </header>

                        <form method="get" class="usuarios-filters alerta-lista-filters">
                            <div class="alerta-lista-filter-grid">
                                <div class="form-group field-span-2">
                                    <label for="vigentes" class="alerta-lista-checkbox">
                                        <input type="checkbox" id="vigentes" name="vigentes" value="1" <?= $somenteVigentes ? 'checked' : '' ?>>
                                        <span>Mostrar somente alertas ativos</span>
                                    </label>
                                    <span class="field-helper">Use este atalho para exibir apenas alertas ainda vigentes na operação.</span>
                                </div>

                                <div class="form-group">
                                    <label for="gravidade">Nível de gravidade</label>
                                    <select id="gravidade" name="gravidade">
                                        <option value="">Todas as gravidades</option>
                                        <?php foreach (['BAIXO', 'MODERADO', 'ALTO', 'MUITO ALTO', 'EXTREMO'] as $g): ?>
                                            <option value="<?= $g ?>" <?= $filtroGravidade === $g ? 'selected' : '' ?>>
                                                <?= $g ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="evento">Tipo de evento</label>
                                    <select id="evento" name="evento">
                                        <option value="">Todos os eventos</option>
                                        <?php foreach ($eventosDisponiveis as $evento): ?>
                                            <option value="<?= htmlspecialchars($evento, ENT_QUOTES, 'UTF-8') ?>" <?= $filtroEvento === $evento ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($evento, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="data_inicio">Vigência a partir de</label>
                                    <input
                                        type="date"
                                        id="data_inicio"
                                        name="data_inicio"
                                        value="<?= htmlspecialchars($dataInicio, ENT_QUOTES, 'UTF-8') ?>"
                                        title="Data inicial para consulta"
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="data_fim">Vigência até</label>
                                    <input
                                        type="date"
                                        id="data_fim"
                                        name="data_fim"
                                        value="<?= htmlspecialchars($dataFim, ENT_QUOTES, 'UTF-8') ?>"
                                        title="Data final para consulta"
                                    >
                                </div>
                            </div>

                            <div class="usuarios-filter-meta alerta-lista-filter-meta field-span-2">
                                <span class="usuarios-filter-meta-label">Recorte ativo</span>
                                <div class="usuarios-filter-pill-row">
                                    <?php if ($filtrosAplicados !== []): ?>
                                        <?php foreach ($filtrosAplicados as $filtroAtivo): ?>
                                            <span class="usuarios-filter-pill"><?= htmlspecialchars((string) $filtroAtivo, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="usuarios-filter-pill is-neutral">Sem filtros adicionais</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="alerta-form-actions usuarios-filter-actions alerta-lista-filter-actions">
                                <div class="alerta-form-actions-left">
                                    <span class="alerta-inline-note">Filtros aplicados: <?= htmlspecialchars($filtrosResumo, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>

                                <div class="alerta-form-actions-right">
                                    <button type="button" class="btn btn-secondary" onclick="limparFiltros()">Limpar filtros</button>
                                    <button type="submit" class="btn btn-primary">Aplicar filtros</button>
                                </div>
                            </div>
                        </form>
                    </section>

                    <section class="alerta-form-section usuarios-governance-panel alerta-lista-governance-panel">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Seção 2</span>
                            <h2 class="alerta-section-title">Ações operacionais</h2>
                            <p class="alerta-section-text">
                                Inicie novas entradas, importe avisos oficiais do INMET e exporte a listagem atual sem sair deste painel.
                            </p>
                        </header>

                        <div class="usuarios-insight-grid alerta-lista-insight-grid">
                            <article class="usuarios-insight-card usuarios-insight-card-emphasis alerta-lista-mini-card">
                                <span class="usuarios-insight-kicker">Perfil atual</span>
                                <strong><?= htmlspecialchars((string) $usuario['perfil'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <p>As ações abaixo respeitam as permissões configuradas para este usuário.</p>
                            </article>

                            <article class="usuarios-insight-card alerta-lista-mini-card">
                                <span class="usuarios-insight-kicker">Exportação</span>
                                <strong>CSV filtrado</strong>
                                <p>O arquivo considera a combinação de filtros atualmente aplicada na página.</p>
                            </article>
                        </div>

                        <div class="alerta-callout alerta-lista-actions-note">
                            <strong>Fluxo recomendado</strong>
                            Use a importação do INMET para avisos oficiais e o cadastro manual para cenários operacionais internos ou integrados.
                        </div>

                        <div class="alerta-form-actions usuarios-filter-actions alerta-lista-action-buttons">
                            <div class="alerta-form-actions-left">
                                <span class="alerta-inline-note">A exportação fica disponível mesmo quando as ações de criação estiverem bloqueadas por perfil.</span>
                            </div>

                            <div class="alerta-form-actions-right alerta-lista-action-buttons-group">
                                <a class="btn btn-secondary alerta-lista-export-btn" href="?<?= htmlspecialchars($csvQueryString, ENT_QUOTES, 'UTF-8') ?>">
                                    Exportar CSV
                                </a>

                                <?php if ($podeCriarEditar): ?>
                                    <a href="/pages/alertas/importar_inmet.php" class="btn btn-primary">Importar alerta do INMET</a>
                                    <a href="/pages/alertas/cadastrar.php" class="btn btn-secondary">Cadastrar alerta</a>
                                <?php else: ?>
                                    <button type="button" class="btn btn-primary btn-disabled" onclick="abrirModalPerfil()">Importar alerta do INMET</button>
                                    <button type="button" class="btn btn-secondary btn-disabled" onclick="abrirModalPerfil()">Cadastrar alerta</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <section id="alerta-lista-tabela" class="alerta-form-panel usuarios-table-panel alerta-lista-table-panel">
                <header class="usuarios-table-head alerta-lista-table-head">
                    <div class="alerta-section-header">
                        <span class="alerta-section-kicker">Seção 3</span>
                        <h2 class="alerta-section-title">Lista operacional de alertas</h2>
                        <p class="alerta-section-text">
                            A tabela abaixo consolida gravidade, status, vigência, comunicação e atalhos de ação para cada alerta cadastrado.
                        </p>
                    </div>

                    <div class="usuarios-result-chip alerta-lista-result-chip">
                        <?= $totalAlertas > 0 ? "Exibindo {$inicioRegistro} a {$fimRegistro} de {$totalAlertas}" : 'Nenhum resultado no filtro atual' ?>
                    </div>
                </header>

                <div class="alerta-lista-table-wrap">
                    <table class="tabela-alertas">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Evento</th>
                                <th>Gravidade</th>
                                <th>Status</th>
                                <th>Vigência</th>
                                <th>Comunicação</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$alertas): ?>
                                <tr>
                                    <td colspan="7" class="alerta-lista-empty-row">
                                        Nenhum alerta encontrado.
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($alertas as $a):
                                $gravidadeExibicao = (string) ($a['nivel_gravidade'] ?? '');

                                $classeGravidade = match ($gravidadeExibicao) {
                                    'BAIXO' => 'gravidade-baixo',
                                    'MODERADO' => 'gravidade-moderado',
                                    'ALTO' => 'gravidade-alto',
                                    'MUITO ALTO' => 'gravidade-muito-alto',
                                    'EXTREMO' => 'gravidade-extremo',
                                    default => '',
                                };

                                $status = statusAlerta($a);
                                $cancelado = $a['status'] === 'CANCELADO';
                                $encerrado = $a['status'] === 'ENCERRADO';
                                $ativo = $a['status'] === 'ATIVO';
                            ?>
                                <tr>
                                    <td data-label="Número"><?= htmlspecialchars($a['numero'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Evento"><?= htmlspecialchars($a['tipo_evento'], ENT_QUOTES, 'UTF-8') ?></td>

                                    <td data-label="Gravidade">
                                        <span class="gravidade <?= $classeGravidade ?>">
                                            <?= htmlspecialchars($gravidadeExibicao, ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>

                                    <td data-label="Status">
                                        <span class="status <?= $status['classe'] ?>">
                                            <?= $status['texto'] ?>
                                        </span>
                                    </td>

                                    <td data-label="Vigência"><?= vigenciaAlerta($a) ?></td>

                                    <td data-label="Comunicação">
                                        <?php if ($a['status'] !== 'ATIVO'): ?>
                                            <span class="tag tag-inativa">Indisponível</span>
                                        <?php elseif (empty($a['imagem_mapa'])): ?>
                                            <span
                                                class="tag tag-alerta"
                                                title="O mapa do alerta ainda não foi gerado. Acesse o detalhe do alerta para gerar o mapa antes de enviar."
                                            >
                                                Mapa não gerado
                                            </span>
                                        <?php elseif ((int) $a['alerta_enviado_compdec'] === 1):
                                            $dataEnvioLocal = TimeHelper::formatDateTime($a['data_envio_compdec'] ?? null, '');
                                        ?>
                                            <span
                                                class="tag tag-sucesso"
                                                title="Alerta enviado em <?= htmlspecialchars($dataEnvioLocal, ENT_QUOTES, 'UTF-8') ?>. Para reenviar, edite e salve o alerta novamente."
                                            >
                                                Enviado
                                                <br>
                                                <small><?= htmlspecialchars($dataEnvioLocal, ENT_QUOTES, 'UTF-8') ?></small>
                                            </span>
                                        <?php else: ?>
                                            <?php if ($podeCriarEditar): ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-warning btn-enviar-alerta"
                                                    data-alerta-id="<?= $a['id'] ?>"
                                                    title="Enviar alerta para as COMPDEC dos municípios afetados"
                                                >
                                                    Enviar alerta
                                                </button>
                                            <?php else: ?>
                                                <span class="tag tag-inativa">Sem permissão de envio</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>

                                    <td data-label="Ações">
                                        <div class="acoes">
                                            <a href="/pages/alertas/detalhe.php?id=<?= $a['id'] ?>" class="btn-acao btn-detalhe">Detalhes</a>

                                            <?php if ($podeCriarEditar): ?>
                                                <?php if (!$cancelado): ?>
                                                    <a href="/pages/alertas/editar.php?id=<?= $a['id'] ?>" class="btn-acao btn-editar">Editar</a>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <?php if ($podeEncerrarCancelar): ?>
                                                <?php if ($encerrado): ?>
                                                    <?php if ($podeAtivarEncerrado): ?>
                                                        <form method="post" action="/pages/alertas/encerrar_alerta.php" class="alerta-lista-inline-form">
                                                            <?= Csrf::inputField() ?>
                                                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                                            <input type="hidden" name="acao" value="ativar">
                                                            <button
                                                                class="btn-acao btn-ativar"
                                                                onclick="return confirm('Deseja reativar este alerta encerrado?')"
                                                            >
                                                                Ativar
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php elseif ($ativo): ?>
                                                    <form method="post" action="/pages/alertas/encerrar_alerta.php" class="alerta-lista-inline-form">
                                                        <?= Csrf::inputField() ?>
                                                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                                        <input type="hidden" name="acao" value="encerrar">
                                                        <button
                                                            class="btn-acao btn-encerrar"
                                                            onclick="return confirm('Deseja encerrar este alerta? Ao encerrar, a vigência será finalizada.')"
                                                        >
                                                            Encerrar
                                                        </button>
                                                    </form>

                                                    <button type="button" class="btn-acao btn-cancelar" onclick="abrirModalCancelar(<?= $a['id'] ?>)">
                                                        Cancelar
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <?php if (!empty($a['imagem_mapa']) && !$cancelado): ?>
                                                <a href="/pages/alertas/baixar_pdf.php?id=<?= $a['id'] ?>" target="_blank" class="btn-acao btn-pdf">PDF</a>
                                            <?php elseif (!$cancelado): ?>
                                                <button
                                                    type="button"
                                                    class="btn-acao btn-pdf btn-disabled"
                                                    disabled
                                                    title="PDF não disponível. Para gerar o PDF do alerta, acesse o detalhe do alerta."
                                                >
                                                    PDF não disponível
                                                </button>
                                            <?php endif; ?>

                                            <?php if (!$cancelado): ?>
                                                <a href="/pages/alertas/kml.php?id=<?= $a['id'] ?>" class="btn-acao btn-kml">KML</a>
                                            <?php endif; ?>

                                            <?php if (empty($a['imagem_mapa']) && !$cancelado): ?>
                                                <span class="acao-aviso-pdf">
                                                    Para gerar o PDF do alerta, acesse o detalhe do alerta.
                                                </span>
                                            <?php endif; ?>

                                            <?php if ($cancelado): ?>
                                                <button
                                                    type="button"
                                                    class="btn-acao btn-detalhe"
                                                    data-motivo="<?= htmlspecialchars((string) $a['motivo_cancelamento'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-data="<?= htmlspecialchars(TimeHelper::formatDateTime($a['data_cancelamento'] ?? null, ''), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-usuario="<?= htmlspecialchars((string) ($a['usuario_cancelou'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                    onclick="abrirModalCancelamentoBotao(this)"
                                                >
                                                    Motivo
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPaginas > 1): ?>
                    <div class="paginacao">
                        <?php if ($pagina > 1): ?>
                            <a href="?<?= http_build_query(array_merge($queryBase, ['pagina' => $pagina - 1])) ?>" aria-label="Página anterior" title="Página anterior">
                                &laquo;
                            </a>
                        <?php endif; ?>

                        <?php foreach (paginacaoProfissional($pagina, (int) $totalPaginas) as $itemPaginacao): ?>
                            <?php if ($itemPaginacao === '...'): ?>
                                <span class="paginacao-ellipsis">...</span>
                            <?php else: ?>
                                <a
                                    href="?<?= http_build_query(array_merge($queryBase, ['pagina' => $itemPaginacao])) ?>"
                                    class="<?= $itemPaginacao === $pagina ? 'ativa' : '' ?>"
                                >
                                    <?= $itemPaginacao ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if ($pagina < $totalPaginas): ?>
                            <a href="?<?= http_build_query(array_merge($queryBase, ['pagina' => $pagina + 1])) ?>" aria-label="Próxima página" title="Próxima página">
                                &raquo;
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        
        <?php include __DIR__ . '/../_footer.php'; ?>
    
    </main>
</div>








<div id="modalPerfil" class="modal-ajuda">
    <div class="modal-ajuda-conteudo">
        <div class="modal-ajuda-header">
            <h3>Acesso não permitido</h3>
            <button onclick="fecharModalPerfil()">X</button>
        </div>

        <div class="modal-ajuda-body">
            <p>
                Seu perfil de usuário <strong><?= htmlspecialchars($usuario['perfil']) ?></strong>
                não possui permissão para executar esta ação.
            </p>

            <p>
                Caso necessite realizar esta operação, solicite autorização
                a um usuário com perfil <strong>ADMIN</strong> ou <strong>GESTOR</strong>.
            </p>
        </div>

        <div class="modal-ajuda-footer">
            <button onclick="fecharModalPerfil()">Entendi</button>
        </div>
    </div>
</div>

<script src="/assets/js/alertas-envio.js"></script>

<div id="modalCancelamento" class="modal-ajuda">
  <div class="modal-ajuda-conteudo">
    <div class="modal-ajuda-header">
      <h3>Alerta Cancelado</h3>
      <button onclick="fecharModalCancelamento()">X</button>
    </div>

    <div class="modal-ajuda-body">
        <p><strong>Data:</strong> <span id="dataCancelamento"></span></p>
        <p><strong>Usuário:</strong> <span id="usuarioCancelamento"></span></p>
        <p><strong>Motivo:</strong> <span id="motivoCancelamento"></span></p>
    </div>

    <div class="modal-ajuda-footer">
      <button onclick="fecharModalCancelamento()">Fechar</button>
    </div>
  </div>
</div>



<div id="modalCancelar" class="modal-ajuda">
 <div class="modal-ajuda-conteudo">
  <form method="post" action="/pages/alertas/encerrar_alerta.php">
   <?= Csrf::inputField() ?>
   <input type="hidden" name="id" id="cancelarId">
   <input type="hidden" name="acao" value="cancelar">

   <div class="modal-ajuda-header">
    <h3>Cancelar Alerta</h3>
    <button type="button" onclick="fecharModalCancelar()">X</button>
   </div>

   <div class="modal-ajuda-body">
    <p><strong>Usuário:</strong> <?= htmlspecialchars($usuario['nome']) ?></p>

    <label class="label-motivo">Motivo do cancelamento</label>
    <textarea name="motivo_cancelamento"
          class="textarea-motivo"
          maxlength="500"
          oninput="contarMotivo(this)"
          required></textarea>

   <small id="contadorMotivo">0 / 500</small>
   </div>

   <div class="modal-ajuda-footer">
    <button type="submit">Confirmar</button>
   </div>
  </form>
 </div>
</div>



<script src="/assets/js/pages/alertas-listar.js?v=<?= htmlspecialchars($versionJsAlertasListar, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>

</html>
