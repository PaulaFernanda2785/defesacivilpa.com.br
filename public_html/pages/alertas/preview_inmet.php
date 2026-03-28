<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Services/InmetService.php';
require_once __DIR__ . '/../../app/Services/HistoricoService.php';
require_once __DIR__ . '/../../app/Services/TerritorioService.php';

Protect::check(['ADMIN', 'GESTOR', 'ANALISTA']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: importar_inmet.php');
    exit;
}

$url = trim((string) ($_POST['inmet_url'] ?? ''));
if ($url === '') {
    die('URL do INMET não informada.');
}

try {
    $dados = InmetService::importarPorUrl($url);
} catch (Throwable $e) {
    http_response_code(422);
    die(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$dataAlerta = $dados['data_alerta'] ?? $dados['inicio_alerta'] ?? null;
if (!$dataAlerta) {
    die('Não foi possível identificar a data oficial do alerta INMET.');
}

$dataAlerta = (string) $dataAlerta;
$inicioAlerta = !empty($dados['inicio_alerta'])
    ? (string) $dados['inicio_alerta']
    : null;
$fimAlerta = !empty($dados['fim_alerta'])
    ? (string) $dados['fim_alerta']
    : null;

$municipios = [];
$municipiosPorRegiao = [];

if (!empty($dados['area_geojson'])) {
    $municipios = TerritorioService::municipiosAfetados($dados['area_geojson']);
    $municipiosPorRegiao = TerritorioService::municipiosPorRegiao($municipios);
}

$geojsonAlerta = json_encode($dados['area_geojson'], JSON_UNESCAPED_UNICODE);

$corAlerta = match (strtoupper((string) $dados['nivel_gravidade'])) {
    'BAIXO' => '#CCC9C7',
    'MODERADO' => '#FFE000',
    'ALTO' => '#FF7B00',
    'EXTREMO' => '#7A28C6',
    'MUITO ALTO' => '#FF1D08',
    default => '#7A28C6',
};

$classeGravidade = match (strtoupper((string) $dados['nivel_gravidade'])) {
    'BAIXO' => 'gravidade-baixo',
    'MODERADO' => 'gravidade-moderado',
    'ALTO' => 'gravidade-alto',
    'MUITO ALTO' => 'gravidade-muito-alto',
    'EXTREMO' => 'gravidade-extremo',
    default => '',
};

$totalMunicipios = count($municipios);
$totalRegioes = count($municipiosPorRegiao);

$usuario = $_SESSION['usuario'] ?? null;
$cssPreviewPath = __DIR__ . '/../../assets/css/pages/alertas-preview_inmet.css';
$cssPreviewVersion = (string) ((int) @filemtime($cssPreviewPath));

if (is_array($usuario) && !empty($usuario['id']) && !empty($usuario['nome'])) {
    HistoricoService::registrar(
        (int) $usuario['id'],
        (string) $usuario['nome'],
        'PREPARAR_IMPORTACAO_INMET',
        'Gerou a prévia de confirmação do alerta do INMET',
        sprintf(
            'URL: %s | Evento: %s | Gravidade: %s | Regiões: %d | Municípios: %d',
            $url,
            (string) ($dados['tipo_evento'] ?? '-'),
            (string) ($dados['nivel_gravidade'] ?? '-'),
            $totalRegioes,
            $totalMunicipios
        )
    );
}

function formatarDataHoraPreview(?string $data): string
{
    return TimeHelper::formatDateTime($data);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Confirmar Importação do Alerta INMET</title>
<link rel="icon" type="image/x-icon" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/logo.cbmpa.ico">

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/painel.css">
<link rel="stylesheet" href="/assets/css/alertas-lista.css">
<link rel="stylesheet" href="/assets/css/pages/alertas-form.css">
<link rel="stylesheet" href="/assets/css/pages/usuarios-listar.css">
<link rel="stylesheet" href="/assets/css/pages/alertas-preview_inmet.css?v=<?= htmlspecialchars($cssPreviewVersion, ENT_QUOTES, 'UTF-8') ?>">
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
    'Confirmar importação INMET' => null,
];
include __DIR__ . '/../_breadcrumb.php';
?>

<section class="dashboard alerta-form-shell usuarios-shell alerta-preview-shell">
    <div class="usuarios-hero-grid alerta-preview-hero-grid">
        <div class="alerta-form-hero usuarios-hero-panel alerta-preview-hero-panel">
            <div class="alerta-form-lead usuarios-hero-copy alerta-preview-hero-copy">
                <span class="alerta-form-kicker">Prévia obrigatória</span>
                <h1 class="alerta-form-title">Confirmar importação do alerta INMET</h1>
                <p class="alerta-form-description">
                    Revise os dados oficiais antes de confirmar a entrada no sistema.
                    Esta etapa consolida evento, gravidade, vigência e abrangência territorial.
                </p>

                <div class="usuarios-hero-chip-row alerta-preview-hero-chip-row">
                    <span class="usuarios-hero-chip">Fonte: INMET oficial</span>
                    <span class="usuarios-hero-chip">Evento: <?= htmlspecialchars((string) $dados['tipo_evento'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="usuarios-hero-chip">Gravidade: <?= htmlspecialchars((string) $dados['nivel_gravidade'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <div class="usuarios-hero-actions alerta-preview-hero-actions">
                    <a href="#preview-dados" class="btn btn-primary">Dados oficiais</a>
                    <a href="#preview-mapa" class="btn btn-secondary">Mapa e território</a>
                </div>
            </div>
        </div>

        <div class="usuarios-summary-grid alerta-preview-summary-grid">
            <article class="usuarios-summary-card usuarios-summary-card-primary">
                <span class="usuarios-summary-label">Fonte</span>
                <strong class="usuarios-summary-value">INMET oficial</strong>
                <span class="usuarios-summary-note">URL analisada: <?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?></span>
            </article>

            <article class="usuarios-summary-card usuarios-summary-card-success">
                <span class="usuarios-summary-label">Classificação</span>
                <strong class="usuarios-summary-value"><?= htmlspecialchars((string) $dados['tipo_evento'], ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="usuarios-summary-note">Gravidade: <?= htmlspecialchars((string) $dados['nivel_gravidade'], ENT_QUOTES, 'UTF-8') ?></span>
            </article>

            <article class="usuarios-summary-card usuarios-summary-card-neutral">
                <span class="usuarios-summary-label">Abrangência</span>
                <strong class="usuarios-summary-value"><?= $totalRegioes ?> regiões / <?= $totalMunicipios ?> municípios</strong>
                <span class="usuarios-summary-note">Calculado pela geometria oficial retornada no aviso.</span>
            </article>

            <article class="usuarios-summary-card usuarios-summary-card-warning">
                <span class="usuarios-summary-label">Operador</span>
                <strong class="usuarios-summary-value"><?= htmlspecialchars((string) ($usuario['nome'] ?? 'Não identificado'), ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="usuarios-summary-note">Perfil atual: <?= htmlspecialchars((string) ($usuario['perfil'] ?? 'Não informado'), ENT_QUOTES, 'UTF-8') ?>.</span>
            </article>
        </div>

        <aside class="usuarios-command-card alerta-preview-command-card">
            <span class="usuarios-command-kicker">Comando da prévia</span>
            <h2>Checklist de confirmação</h2>
            <p>
                Valide metadados oficiais, confira vigência e risco, depois revise área afetada e municípios por região
                antes de concluir a importação.
            </p>

            <div class="usuarios-command-grid alerta-preview-command-grid">
                <article class="usuarios-command-item">
                    <span>Etapa 1</span>
                    <strong>Metadados</strong>
                    <small>Evento, gravidade, data oficial e vigência do alerta.</small>
                </article>

                <article class="usuarios-command-item">
                    <span>Etapa 2</span>
                    <strong>Território</strong>
                    <small>Mapa com polígono e municípios identificados por região.</small>
                </article>

                <article class="usuarios-command-item">
                    <span>Etapa 3</span>
                    <strong>Confirmação</strong>
                    <small>Finalize para criar o alerta local com base nos dados oficiais.</small>
                </article>
            </div>
        </aside>
    </div>

    <div class="alerta-callout">
        <strong>Atenção técnica</strong>
        A numeração interna do alerta será criada na confirmação final, mas a data do alerta e a vigência serão preservadas conforme a publicação oficial do INMET.
    </div>

    <form method="post" action="salvar_inmet.php" class="alerta-form-panel usuarios-control-panel alerta-preview-form-panel">
        <?= Csrf::inputField() ?>
        <input type="hidden" name="inmet_url" value="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="data_alerta" value="<?= htmlspecialchars($dataAlerta, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="inicio_alerta" value="<?= htmlspecialchars((string) $inicioAlerta, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="fim_alerta" value="<?= htmlspecialchars((string) $fimAlerta, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="tipo_evento" value="<?= htmlspecialchars((string) $dados['tipo_evento'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="nivel_gravidade" value="<?= htmlspecialchars((string) $dados['nivel_gravidade'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="riscos" value="<?= htmlspecialchars((string) $dados['riscos'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="recomendacoes" value="<?= htmlspecialchars((string) $dados['recomendacoes'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="area_geojson" value='<?= htmlspecialchars((string) $geojsonAlerta, ENT_QUOTES, 'UTF-8') ?>'>

        <div class="alerta-form-grid alerta-preview-form-grid">
            <section id="preview-dados" class="alerta-form-section">
                <header class="alerta-section-header">
                    <span class="alerta-section-kicker">Seção 1</span>
                    <h2 class="alerta-section-title">Informações oficiais do alerta</h2>
                    <p class="alerta-section-text">
                        Confira os metadados principais, os riscos informados pelo INMET e a orientação oficial antes de confirmar a importação.
                    </p>
                </header>

                <div class="alerta-preview-official-card">
                    <div class="alerta-preview-official-head">
                        <div>
                            <strong>Leitura oficial do alerta</strong>
                            <span>Os dados abaixo vieram da consulta oficial ao INMET e já estão prontos para a confirmação final.</span>
                        </div>
                        <span class="area-source-chip">Prévia oficial</span>
                    </div>

                    <div class="alerta-preview-meta-grid">
                        <article class="alerta-preview-item">
                            <span class="alerta-preview-label">Evento</span>
                            <span class="alerta-preview-value"><?= htmlspecialchars((string) $dados['tipo_evento'], ENT_QUOTES, 'UTF-8') ?></span>
                        </article>

                        <article class="alerta-preview-item">
                            <span class="alerta-preview-label">Gravidade</span>
                            <span class="alerta-preview-value">
                                <span class="gravidade <?= $classeGravidade ?>">
                                    <?= htmlspecialchars((string) $dados['nivel_gravidade'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </span>
                        </article>

                        <article class="alerta-preview-item">
                            <span class="alerta-preview-label">Data oficial</span>
                            <span class="alerta-preview-value"><?= htmlspecialchars(formatarDataHoraPreview($dataAlerta), ENT_QUOTES, 'UTF-8') ?></span>
                        </article>

                        <article class="alerta-preview-item">
                            <span class="alerta-preview-label">Início da vigência</span>
                            <span class="alerta-preview-value"><?= htmlspecialchars(formatarDataHoraPreview($inicioAlerta), ENT_QUOTES, 'UTF-8') ?></span>
                        </article>

                        <article class="alerta-preview-item">
                            <span class="alerta-preview-label">Fim da vigência</span>
                            <span class="alerta-preview-value"><?= htmlspecialchars(formatarDataHoraPreview($fimAlerta), ENT_QUOTES, 'UTF-8') ?></span>
                        </article>

                        <article class="alerta-preview-item">
                            <span class="alerta-preview-label">Geometria recebida</span>
                            <span class="alerta-preview-value"><?= !empty($dados['area_geojson']) ? 'Sim' : 'Não' ?></span>
                        </article>
                    </div>
                </div>

                <article class="alerta-preview-text-card">
                    <h3>Riscos</h3>
                    <p><?= nl2br(htmlspecialchars((string) $dados['riscos'], ENT_QUOTES, 'UTF-8')) ?></p>
                </article>

                <article class="alerta-preview-text-card">
                    <h3>Recomendações</h3>
                    <p><?= nl2br(htmlspecialchars((string) $dados['recomendacoes'], ENT_QUOTES, 'UTF-8')) ?></p>
                </article>
            </section>

            <section id="preview-mapa" class="alerta-form-section">
                <header class="alerta-section-header">
                    <span class="alerta-section-kicker">Seção 2</span>
                    <h2 class="alerta-section-title">Mapa e abrangência territorial</h2>
                    <p class="alerta-section-text">
                        Visualize a área do alerta no mapa e confira os municípios e regiões de integração identificados a partir do polígono do aviso.
                    </p>
                </header>

                <div class="upload-stack">
                    <div class="map-card">
                        <div class="map-card-header">
                            <div>
                                <div class="map-card-title">Área do alerta</div>
                                <div class="map-card-text">A coloração do polígono segue o nível de gravidade retornado pelo INMET.</div>
                            </div>
                            <span class="area-source-chip">Cor ativa: <span class="alerta-preview-color" style="background: <?= htmlspecialchars($corAlerta, ENT_QUOTES, 'UTF-8') ?>;"></span></span>
                        </div>

                        <div id="mapaPreview" class="alerta-map alerta-preview-map"></div>
                    </div>

                    <div class="territorio-preview-card">
                        <div class="territorio-preview-header">
                            <div>
                                <strong>Municípios por região</strong>
                                <span>O agrupamento abaixo segue o mesmo padrão territorial da tela de detalhe do alerta.</span>
                            </div>
                            <div class="territorio-preview-summary"><?= $totalRegioes ?> regiões / <?= $totalMunicipios ?> municípios</div>
                        </div>
                        <div class="territorio-preview-list">
                            <?php if ($municipiosPorRegiao === []): ?>
                                <div class="territorio-preview-empty">Nenhuma região de integração foi identificada automaticamente.</div>
                            <?php else: ?>
                                <?php foreach ($municipiosPorRegiao as $regiao => $lista): ?>
                                    <article class="territorio-region-block">
                                        <div class="territorio-region-title"><?= htmlspecialchars((string) $regiao, ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="territorio-region-meta"><?= count($lista) ?> municípios vinculados</div>
                                        <div class="territorio-region-municipios"><?= htmlspecialchars(implode(', ', $lista), ENT_QUOTES, 'UTF-8') ?></div>
                                    </article>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="alerta-form-actions">
            <div class="alerta-form-actions-left">
                <span class="alerta-inline-note">Ao confirmar, o sistema cria o alerta local com base nos dados oficiais desta prévia.</span>
            </div>

            <div class="alerta-form-actions-right">
                <a href="importar_inmet.php" class="btn btn-secondary">Voltar</a>
                <button type="submit" class="btn btn-primary">Confirmar importação</button>
            </div>
        </div>
    </form>
</section>

</main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script id="preview-inmet-data" type="application/json"><?= json_encode([
    'geojson' => $dados['area_geojson'] ?? null,
    'color' => $corAlerta,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="/assets/js/pages/alertas-preview_inmet.js"></script>
<script src="/assets/js/app-shell.js"></script>

</body>
</html>
