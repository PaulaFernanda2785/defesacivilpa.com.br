<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Database.php';

Protect::check(['ADMIN', 'GESTOR', 'ANALISTA', 'OPERACOES']);

$db = Database::getConnection();
$usuario = $_SESSION['usuario'];

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: listar.php');
    exit;
}

$stmt = $db->prepare("
    SELECT
        a.*,
        u.nome AS usuario_cancelou
    FROM alertas a
    LEFT JOIN usuarios u
        ON u.id = a.usuario_cancelamento
    WHERE a.id = ?
");
$stmt->execute([$id]);
$alerta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$alerta) {
    header('Location: listar.php');
    exit;
}

$cancelado = ($alerta['status'] ?? '') === 'CANCELADO';
$imagemGerada = !empty($alerta['imagem_mapa']);
$alerta['imagem_pendente'] = !$imagemGerada;

$stmt = $db->prepare("
    SELECT
        am.municipio_nome,
        mr.regiao_integracao
    FROM alerta_municipios am
    JOIN municipios_regioes_pa mr
        ON mr.cod_ibge = am.municipio_codigo
    WHERE am.alerta_id = ?
    ORDER BY mr.regiao_integracao, am.municipio_nome
");
$stmt->execute([$id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$municipiosPorRegiao = [];
foreach ($rows as $row) {
    $municipiosPorRegiao[$row['regiao_integracao']][] = $row['municipio_nome'];
}

$gravidadeExibicao = (string) ($alerta['nivel_gravidade'] ?? '');
$classeGravidade = match ($gravidadeExibicao) {
    'BAIXO' => 'gravidade-baixo',
    'MODERADO' => 'gravidade-moderado',
    'ALTO' => 'gravidade-alto',
    'MUITO ALTO' => 'gravidade-muito-alto',
    'EXTREMO' => 'gravidade-extremo',
    default => ''
};

$statusClass = match ($alerta['status'] ?? '') {
    'ENCERRADO' => 'status-encerrado',
    'CANCELADO' => 'status-cancelado',
    default => 'status-vigente',
};

$origemArea = strtoupper((string) ($alerta['area_origem'] ?? '')) === 'KML'
    ? 'KML'
    : 'Desenho/manual';

$totalRegioes = count($municipiosPorRegiao);
$totalMunicipios = array_sum(array_map('count', $municipiosPorRegiao));

$statusPdf = $cancelado
    ? 'Indisponível'
    : ($imagemGerada ? 'Pronto' : 'Gerando');

$notaPdf = $cancelado
    ? 'O alerta foi cancelado, por isso o PDF não fica disponível para download.'
    : ($imagemGerada
        ? 'A imagem do mapa já foi gerada e o PDF pode ser baixado imediatamente.'
        : 'A imagem do mapa ainda está sendo gerada. O PDF será liberado assim que o processo terminar.');

$cssDetalhePath = __DIR__ . '/../../assets/css/pages/alertas-detalhe.css';
$cssDetalheVersion = (string) ((int) @filemtime($cssDetalhePath));

function formatarDataSimples(?string $data): string
{
    return TimeHelper::formatDate($data);
}

function formatarDataHora(?string $data): string
{
    return TimeHelper::formatDateTime($data);
}

function formatarVigenciaResumo(array $alerta): string
{
    $inicio = formatarDataHora($alerta['inicio_alerta'] ?? null);
    $fim = formatarDataHora($alerta['fim_alerta'] ?? null);

    return $inicio . ' até ' . $fim;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Detalhe do Alerta</title>
<link rel="icon" type="image/x-icon" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/logo.cbmpa.ico">

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/painel.css">
<link rel="stylesheet" href="/assets/css/alertas-lista.css">
<link rel="stylesheet" href="/assets/css/pages/alertas-form.css">
<link rel="stylesheet" href="/assets/css/pages/usuarios-listar.css">
<link rel="stylesheet" href="/assets/css/pages/alertas-detalhe.css?v=<?= htmlspecialchars($cssDetalheVersion, ENT_QUOTES, 'UTF-8') ?>">
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
    'Alertas' => '/pages/alertas/listar.php',
    'Detalhes do alerta' => null,
];
include __DIR__ . '/../_breadcrumb.php';
?>

<section class="dashboard alerta-form-shell usuarios-shell alerta-detalhe-shell">
    <div class="usuarios-hero-grid alerta-detalhe-hero-grid">
        <div class="alerta-form-hero usuarios-hero-panel alerta-detalhe-hero-panel">
            <div class="alerta-form-lead usuarios-hero-copy alerta-detalhe-hero-copy">
                <span class="alerta-form-kicker">Consulta operacional</span>
                <h1 class="alerta-form-title">Detalhes do alerta <?= htmlspecialchars($alerta['numero']) ?></h1>
                <p class="alerta-form-description">
                    Consulte o conteúdo técnico do alerta, acompanhe a abrangência territorial e baixe o PDF assim que a imagem
                    do mapa estiver disponível.
                </p>

                <div class="usuarios-hero-chip-row alerta-detalhe-hero-chip-row">
                    <span class="usuarios-hero-chip">Status: <?= htmlspecialchars((string) ($alerta['status'] ?? 'ATIVO'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="usuarios-hero-chip"><?= $totalRegioes ?> regiões / <?= $totalMunicipios ?> municípios</span>
                    <span class="usuarios-hero-chip">Origem da área: <?= htmlspecialchars($origemArea, ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <div class="usuarios-hero-actions alerta-detalhe-hero-actions">
                    <a href="#detalhe-informacoes" class="btn btn-primary">Dados do alerta</a>
                    <a href="#detalhe-mapa" class="btn btn-secondary">Mapa e territórios</a>
                </div>
            </div>
        </div>

        <div class="usuarios-summary-grid alerta-detalhe-summary-grid">
            <article class="usuarios-summary-card usuarios-summary-card-primary">
                <span class="usuarios-summary-label">Número</span>
                <strong class="usuarios-summary-value"><?= htmlspecialchars((string) ($alerta['numero'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="usuarios-summary-note">Identificador oficial do alerta no sistema.</span>
            </article>

            <article class="usuarios-summary-card usuarios-summary-card-success">
                <span class="usuarios-summary-label">Status</span>
                <strong class="usuarios-summary-value">
                    <span class="status-chip <?= $statusClass ?>"><?= htmlspecialchars((string) ($alerta['status'] ?? 'ATIVO'), ENT_QUOTES, 'UTF-8') ?></span>
                </strong>
                <span class="usuarios-summary-note">Situação atual do alerta e disponibilidade operacional.</span>
            </article>

            <article class="usuarios-summary-card usuarios-summary-card-neutral">
                <span class="usuarios-summary-label">Vigência</span>
                <strong class="usuarios-summary-value"><?= htmlspecialchars(formatarVigenciaResumo($alerta), ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="usuarios-summary-note">Período considerado para monitoramento e resposta.</span>
            </article>

            <article class="usuarios-summary-card usuarios-summary-card-warning">
                <span class="usuarios-summary-label">Abrangência</span>
                <strong class="usuarios-summary-value"><?= $totalRegioes ?> regiões / <?= $totalMunicipios ?> municípios</strong>
                <span class="usuarios-summary-note">Origem da geometria: <?= htmlspecialchars($origemArea, ENT_QUOTES, 'UTF-8') ?>.</span>
            </article>
        </div>

        <aside class="usuarios-command-card alerta-detalhe-command-card">
            <span class="usuarios-command-kicker">Comando de consulta</span>
            <h2>Leitura recomendada</h2>
            <p>
                Revise metadados, vigência e gravidade, depois valide a área afetada no mapa e finalize com a leitura
                territorial por região de integração.
            </p>

            <div class="usuarios-command-grid alerta-detalhe-command-grid">
                <article class="usuarios-command-item">
                    <span>Etapa 1</span>
                    <strong>Metadados</strong>
                    <small>Confirme fonte, evento, nível de gravidade e status do PDF.</small>
                </article>

                <article class="usuarios-command-item">
                    <span>Etapa 2</span>
                    <strong>Geometria</strong>
                    <small>Valide a área no mapa e a origem da geometria usada no alerta.</small>
                </article>

                <article class="usuarios-command-item">
                    <span>Etapa 3</span>
                    <strong>Território</strong>
                    <small>Confira os municípios por região e use o PDF quando liberado.</small>
                </article>
            </div>
        </aside>
    </div>

    <?php if ($cancelado): ?>
        <div class="alerta-callout alerta-detail-callout-danger">
            <strong>Alerta cancelado</strong>
            Usuario: <?= htmlspecialchars((string) ($alerta['usuario_cancelou'] ?? '-')) ?><br>
            Data: <?= htmlspecialchars(formatarDataHora($alerta['data_cancelamento'] ?? null)) ?><br>
            Motivo: <?= nl2br(htmlspecialchars((string) ($alerta['motivo_cancelamento'] ?? '-'))) ?>
        </div>
    <?php elseif (!$imagemGerada): ?>
        <div class="alerta-callout alerta-detail-callout-info">
            <strong>Imagem do mapa em processamento</strong>
            O PDF ainda não foi liberado porque a imagem do mapa está sendo preparada automaticamente nesta página.
        </div>
    <?php endif; ?>

    <div class="alerta-form-panel usuarios-control-panel alerta-detalhe-action-panel">
        <div class="alerta-form-actions alerta-detail-actions">
            <div class="alerta-form-actions-left">
                <span class="alerta-inline-note"><?= htmlspecialchars($notaPdf) ?></span>
            </div>

            <div class="alerta-form-actions-right">
                <a href="listar.php?reload=1" class="btn btn-secondary">Voltar</a>

                <?php if (in_array($usuario['perfil'], ['ADMIN', 'GESTOR', 'ANALISTA'], true)): ?>
                    <?php if ($cancelado): ?>
                        <button class="btn btn-secondary btn-disabled" title="Alerta cancelado" disabled>Editar alerta</button>
                    <?php else: ?>
                        <a href="editar.php?id=<?= (int) $alerta['id'] ?>" class="btn btn-primary">Editar alerta</a>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($imagemGerada && !$cancelado): ?>
                    <a
                        href="/pages/alertas/baixar_pdf.php?id=<?= (int) $alerta['id'] ?>&v=<?= time() ?>"
                        class="btn btn-primary"
                        target="_blank"
                    >
                        Baixar PDF do alerta
                    </a>
                <?php elseif ($cancelado): ?>
                    <button class="btn btn-secondary btn-disabled" disabled>Alerta cancelado</button>
                <?php else: ?>
                    <button class="btn btn-secondary" disabled>Gerando imagem do mapa...</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="alerta-form-panel usuarios-control-panel alerta-detalhe-content-panel">
        <div class="alerta-form-grid">
            <section id="detalhe-informacoes" class="alerta-form-section">
                <header class="alerta-section-header">
                    <span class="alerta-section-kicker">Seção 1</span>
                    <h2 class="alerta-section-title">Informações do alerta</h2>
                    <p class="alerta-section-text">
                        Visão consolidada dos dados técnicos, classificação de gravidade, vigência e conteúdo operacional do alerta.
                    </p>
                </header>

                <div class="alerta-detail-meta-grid">
                    <article class="alerta-detail-item">
                        <span class="alerta-detail-label">Fonte</span>
                        <span class="alerta-detail-value"><?= htmlspecialchars((string) ($alerta['fonte'] ?? '-')) ?></span>
                    </article>

                    <article class="alerta-detail-item">
                        <span class="alerta-detail-label">Evento</span>
                        <span class="alerta-detail-value"><?= htmlspecialchars((string) ($alerta['tipo_evento'] ?? '-')) ?></span>
                    </article>

                    <article class="alerta-detail-item">
                        <span class="alerta-detail-label">Gravidade</span>
                        <span class="alerta-detail-value">
                            <span class="gravidade <?= $classeGravidade ?>"><?= htmlspecialchars($gravidadeExibicao !== '' ? $gravidadeExibicao : '-') ?></span>
                        </span>
                    </article>

                    <article class="alerta-detail-item">
                        <span class="alerta-detail-label">Status do PDF</span>
                        <span class="alerta-detail-value"><?= htmlspecialchars($statusPdf) ?></span>
                    </article>

                    <article class="alerta-detail-item">
                        <span class="alerta-detail-label">Data do alerta</span>
                        <span class="alerta-detail-value"><?= htmlspecialchars(formatarDataSimples($alerta['data_alerta'] ?? null)) ?></span>
                    </article>

                    <article class="alerta-detail-item">
                        <span class="alerta-detail-label">Origem da área</span>
                        <span class="alerta-detail-value"><?= htmlspecialchars($origemArea) ?></span>
                    </article>

                    <article class="alerta-detail-item">
                        <span class="alerta-detail-label">Início da vigência</span>
                        <span class="alerta-detail-value"><?= htmlspecialchars(formatarDataHora($alerta['inicio_alerta'] ?? null)) ?></span>
                    </article>

                    <article class="alerta-detail-item">
                        <span class="alerta-detail-label">Fim da vigência</span>
                        <span class="alerta-detail-value"><?= htmlspecialchars(formatarDataHora($alerta['fim_alerta'] ?? null)) ?></span>
                    </article>
                </div>

                <article class="alerta-detail-text-card">
                    <h3>Riscos potenciais</h3>
                    <p><?= nl2br(htmlspecialchars((string) ($alerta['riscos'] ?? '-'))) ?></p>
                </article>

                <article class="alerta-detail-text-card">
                    <h3>Recomendações</h3>
                    <p><?= nl2br(htmlspecialchars((string) ($alerta['recomendacoes'] ?? '-'))) ?></p>
                </article>
            </section>

            <section id="detalhe-mapa" class="alerta-form-section">
                <header class="alerta-section-header">
                    <span class="alerta-section-kicker">Seção 2</span>
                    <h2 class="alerta-section-title">Mapa, territórios e anexos</h2>
                    <p class="alerta-section-text">
                        Acompanhe a área afetada no mapa, confira os municípios agrupados por região de integração e consulte a imagem informativa vinculada ao alerta.
                    </p>
                </header>

                <div class="upload-stack">
                    <div class="kml-status-card">
                        <div class="kml-status-row">
                            <strong class="kml-status-title">Preparação do PDF</strong>
                            <span class="area-source-chip"><?= htmlspecialchars($statusPdf) ?></span>
                        </div>
                        <div class="kml-status-text"><?= htmlspecialchars($notaPdf) ?></div>
                    </div>

                    <div class="map-card">
                        <div class="map-card-header">
                            <div>
                                <div class="map-card-title">Área afetada</div>
                                <div class="map-card-text">A geometria usada no PDF é a mesma exibida abaixo nesta visualização de detalhe.</div>
                            </div>
                            <span class="area-source-chip">Origem: <?= htmlspecialchars($origemArea) ?></span>
                        </div>

                        <div id="mapa" class="alerta-map mapa-detalhe"></div>
                    </div>

                    <div id="loading-imagem" class="alerta-detail-loading" style="display:none;"></div>

                    <div class="territorio-preview-card">
                        <div class="territorio-preview-header">
                            <div>
                                <strong>Municípios por região</strong>
                                <span>Distribuição territorial reconhecida para este alerta a partir da geometria salva.</span>
                            </div>
                            <div class="territorio-preview-summary"><?= $totalRegioes ?> regiões / <?= $totalMunicipios ?> municípios</div>
                        </div>

                        <div class="territorio-preview-list">
                            <?php if ($municipiosPorRegiao === []): ?>
                                <div class="territorio-preview-empty">Nenhum município associado ao alerta foi encontrado.</div>
                            <?php else: ?>
                                <?php foreach ($municipiosPorRegiao as $regiao => $lista): ?>
                                    <article class="territorio-region-block">
                                        <div class="territorio-region-title"><?= htmlspecialchars($regiao) ?></div>
                                        <div class="territorio-region-meta"><?= count($lista) ?> municípios identificados</div>
                                        <div class="territorio-region-municipios"><?= htmlspecialchars(implode(', ', $lista)) ?></div>
                                    </article>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="upload-preview-card alerta-detail-image-card">
                        <div class="upload-preview">
                            <?php if (!empty($alerta['informacoes'])): ?>
                                <img src="<?= htmlspecialchars((string) $alerta['informacoes']) ?>" alt="Imagem de informações do alerta">
                            <?php else: ?>
                                <div class="upload-preview-empty">
                                    Nenhuma imagem informativa foi vinculada a este alerta.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="upload-meta">
                            <strong>Imagem informativa</strong>
                            <span>
                                <?php if (!empty($alerta['informacoes'])): ?>
                                    Arquivo vinculado ao alerta para apoio visual e comunicação.
                                <?php else: ?>
                                    O alerta não possui imagem adicional cadastrada neste momento.
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</section>

<?php include __DIR__ . '/../_footer.php'; ?>

</main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-image/leaflet-image.js"></script>
<script id="alerta-detalhe-data" type="application/json"><?= json_encode([
    'geojson' => !empty($alerta['area_geojson']) ? json_decode($alerta['area_geojson'], true) : null,
    'alertaId' => (int) $alerta['id'],
    'nivel' => $alerta['nivel_gravidade'],
    'shouldGenerateImage' => !$imagemGerada && !$cancelado,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="/assets/js/pages/alertas-detalhe.js"></script>

</body>
</html>
