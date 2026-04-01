<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Helpers/AlertaFormHelper.php';

Protect::check(['ADMIN', 'GESTOR', 'ANALISTA']);

$db = Database::getConnection();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    die('Alerta inválido.');
}

$stmt = $db->prepare('SELECT * FROM alertas WHERE id = ?');
$stmt->execute([$id]);
$alerta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$alerta) {
    die('Alerta não encontrado.');
}

function formatarVigenciaAtual(array $alerta): string
{
    $inicio = TimeHelper::formatDateTime($alerta['inicio_alerta'] ?? null);
    $fim = TimeHelper::formatDateTime($alerta['fim_alerta'] ?? null);

    return $inicio . ' até ' . $fim;
}

$eventos = AlertaFormHelper::eventos();
$niveis = AlertaFormHelper::niveis();
$fontes = AlertaFormHelper::fontes();
$usuario = $_SESSION['usuario'] ?? [];

$tipoEventoAtual = AlertaFormHelper::normalizeExistingOption($alerta['tipo_evento'] ?? '', $eventos);
$fonteAtual = AlertaFormHelper::normalizeExistingOption($alerta['fonte'] ?? '', $fontes);
$nivelAtual = (string) ($alerta['nivel_gravidade'] ?? '');

if ($tipoEventoAtual !== null && !in_array($tipoEventoAtual, $eventos, true)) {
    $eventos[] = $tipoEventoAtual;
}

if ($fonteAtual !== null && !in_array($fonteAtual, $fontes, true)) {
    $fonteAtual = '';
}

$statusClass = match ($alerta['status'] ?? '') {
    'ENCERRADO' => 'status-encerrado',
    'CANCELADO' => 'status-cancelado',
    default => 'status-vigente',
};

$kmlAtualNome = !empty($alerta['kml_arquivo']) ? basename((string) $alerta['kml_arquivo']) : '';
$imagemAtualNome = !empty($alerta['informacoes']) ? basename((string) $alerta['informacoes']) : '';
$areaOrigemAtual = strtoupper((string) ($alerta['area_origem'] ?? '')) === 'KML' ? 'KML' : 'DESENHO';
$kmlAtualVisivel = $areaOrigemAtual === 'KML' ? $kmlAtualNome : '';
$erroFormulario = trim((string) ($_GET['erro'] ?? ''));
$cssEditarPath = __DIR__ . '/../../assets/css/pages/alertas-editar.css';
$cssEditarVersion = (string) ((int) @filemtime($cssEditarPath));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Editar Alerta</title>
<link rel="icon" type="image/x-icon" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/logo.cbmpa.ico">

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/painel.css">
<link rel="stylesheet" href="/assets/css/alertas-lista.css">
<link rel="stylesheet" href="/assets/css/pages/alertas-form.css">
<link rel="stylesheet" href="/assets/css/pages/usuarios-listar.css">
<link rel="stylesheet" href="/assets/css/pages/alertas-editar.css?v=<?= htmlspecialchars($cssEditarVersion, ENT_QUOTES, 'UTF-8') ?>">

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css">
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
    'Editar alerta' => null,
];
include __DIR__ . '/../_breadcrumb.php';
?>

<section class="dashboard alerta-form-shell usuarios-shell alerta-edicao-shell">
    <div class="usuarios-hero-grid alerta-edicao-hero-grid">
        <div class="alerta-form-hero usuarios-hero-panel alerta-edicao-hero-panel">
            <div class="alerta-form-lead usuarios-hero-copy alerta-edicao-hero-copy">
                <span class="alerta-form-kicker">Atualização operacional</span>
                <h1 class="alerta-form-title">Editar alerta <?= htmlspecialchars($alerta['numero']) ?></h1>
                <p class="alerta-form-description">
                    Atualize as informações técnicas, substitua anexos quando necessário e revise a área afetada diretamente no mapa.
                    O número do alerta permanece imutável durante toda a edição.
                </p>

                <div class="usuarios-hero-chip-row alerta-edicao-hero-chip-row">
                    <span class="usuarios-hero-chip">Status: <?= htmlspecialchars((string) ($alerta['status'] ?? 'N/D'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="usuarios-hero-chip">Origem da área: <?= htmlspecialchars($areaOrigemAtual, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="usuarios-hero-chip"><?= count($eventos) ?> tipos de evento disponíveis</span>
                </div>

                <div class="usuarios-hero-actions alerta-edicao-hero-actions">
                    <a href="#edicao-dados" class="btn btn-primary">Revisar dados</a>
                    <a href="#edicao-anexos" class="btn btn-secondary">Anexos e mapa</a>
                </div>
            </div>
        </div>

        <div class="usuarios-summary-grid alerta-edicao-summary-grid">
            <article class="usuarios-summary-card usuarios-summary-card-primary">
                <span class="usuarios-summary-label">Número</span>
                <strong class="usuarios-summary-value"><?= htmlspecialchars($alerta['numero']) ?></strong>
                <span class="usuarios-summary-note">Identificador permanente do alerta.</span>
            </article>

            <article class="usuarios-summary-card usuarios-summary-card-success">
                <span class="usuarios-summary-label">Status atual</span>
                <strong class="usuarios-summary-value">
                    <span class="status-chip <?= $statusClass ?>"><?= htmlspecialchars((string) ($alerta['status'] ?? 'N/D'), ENT_QUOTES, 'UTF-8') ?></span>
                </strong>
                <span class="usuarios-summary-note">A edição redefine o mapa em PDF e zera o status de envio.</span>
            </article>

            <article class="usuarios-summary-card usuarios-summary-card-neutral">
                <span class="usuarios-summary-label">Vigência atual</span>
                <strong class="usuarios-summary-value"><?= htmlspecialchars(formatarVigenciaAtual($alerta), ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="usuarios-summary-note">Origem da área: <?= htmlspecialchars($areaOrigemAtual, ENT_QUOTES, 'UTF-8') ?>.</span>
            </article>

            <article class="usuarios-summary-card usuarios-summary-card-warning">
                <span class="usuarios-summary-label">Operador</span>
                <strong class="usuarios-summary-value"><?= htmlspecialchars((string) ($usuario['nome'] ?? 'Não identificado'), ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="usuarios-summary-note">Perfil atual: <?= htmlspecialchars((string) ($usuario['perfil'] ?? 'Não informado'), ENT_QUOTES, 'UTF-8') ?>.</span>
            </article>
        </div>

        <aside class="usuarios-command-card alerta-edicao-command-card">
            <span class="usuarios-command-kicker">Comando de edição</span>
            <h2>Ritmo de atualização</h2>
            <p>
                Revise dados técnicos, confirme anexos e valide a geometria final no mapa antes de salvar.
                A alteração impacta a geração de PDF e o fluxo de comunicação do alerta.
            </p>

            <div class="usuarios-command-grid alerta-edicao-command-grid">
                <article class="usuarios-command-item">
                    <span>Etapa 1</span>
                    <strong>Conferência</strong>
                    <small>Valide vigência, evento, gravidade e descrição operacional.</small>
                </article>

                <article class="usuarios-command-item">
                    <span>Etapa 2</span>
                    <strong>Mapa e anexos</strong>
                    <small>Troque imagem/KML somente quando necessário e revise a área final.</small>
                </article>

                <article class="usuarios-command-item">
                    <span>Etapa 3</span>
                    <strong>Salvar alterações</strong>
                    <small>Finalize com a geometria correta para atualizar os materiais operacionais.</small>
                </article>
            </div>
        </aside>
    </div>

    <?php if (($alerta['status'] ?? '') === 'ATIVO'): ?>
        <div class="alerta-callout alerta-edicao-status-callout">
            <strong>Alerta ativo</strong>
            Este alerta permanece ativo. Para encerrar ou cancelar, utilize os controles próprios da listagem de alertas.
        </div>
    <?php endif; ?>

    <?php if ($erroFormulario !== ''): ?>
        <div class="alerta-callout alerta-form-callout-error">
            <strong>Nao foi possivel atualizar o alerta</strong>
            <?= htmlspecialchars($erroFormulario, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form id="alertaForm" class="alerta-form-panel usuarios-control-panel alerta-edicao-form-panel" method="post" action="atualizar.php" enctype="multipart/form-data">
        <?= Csrf::inputField() ?>
        <input type="hidden" name="id" value="<?= (int) $alerta['id'] ?>">
        <input type="hidden" name="area_geojson" id="area_geojson">
        <input type="hidden" name="area_origem" id="area_origem" value="<?= htmlspecialchars($areaOrigemAtual) ?>">

        <div class="alerta-form-grid alerta-edicao-form-grid">
            <section id="edicao-dados" class="alerta-form-section">
                <header class="alerta-section-header">
                    <span class="alerta-section-kicker">Seção 1</span>
                    <h2 class="alerta-section-title">Informações do alerta</h2>
                    <p class="alerta-section-text">
                        Revise status, período, classificação e conteúdo operacional do alerta antes de confirmar a alteração.
                    </p>
                </header>

                <div class="alerta-fields-grid">
                    <div class="form-group">
                        <label for="numero_exibicao">Número do alerta</label>
                        <input
                            type="text"
                            id="numero_exibicao"
                            value="<?= htmlspecialchars($alerta['numero']) ?>"
                            class="campo-bloqueado"
                            disabled
                        >
                    </div>

                    <div class="form-group">
                        <label for="status_exibicao">Status atual</label>
                        <input
                            type="text"
                            id="status_exibicao"
                            value="<?= htmlspecialchars($alerta['status']) ?>"
                            class="campo-bloqueado"
                            disabled
                        >
                    </div>

                    <div class="form-group">
                        <label for="data_alerta">Data do alerta</label>
                        <input
                            type="date"
                            id="data_alerta"
                            name="data_alerta"
                            value="<?= htmlspecialchars((string) ($alerta['data_alerta'] ?? '')) ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="fonte">Fonte do alerta</label>
                        <select id="fonte" name="fonte" required>
                            <option value="" <?= $fonteAtual === '' ? 'selected' : '' ?>>Selecione</option>
                            <?php foreach ($fontes as $fonte): ?>
                                <option value="<?= htmlspecialchars($fonte) ?>" <?= $fonteAtual === $fonte ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($fonte) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="inicio_alerta">Início da vigência</label>
                        <input
                            type="datetime-local"
                            id="inicio_alerta"
                            name="inicio_alerta"
                            value="<?= htmlspecialchars(TimeHelper::toHtmlDateTimeLocal($alerta['inicio_alerta'] ?? null)) ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="fim_alerta">Fim da vigência</label>
                        <input
                            type="datetime-local"
                            id="fim_alerta"
                            name="fim_alerta"
                            value="<?= htmlspecialchars(TimeHelper::toHtmlDateTimeLocal($alerta['fim_alerta'] ?? null)) ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="tipo_evento">Tipo de evento</label>
                        <select id="tipo_evento" name="tipo_evento" required>
                            <?php foreach ($eventos as $evento): ?>
                                <option value="<?= htmlspecialchars($evento) ?>" <?= $tipoEventoAtual === $evento ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($evento) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="nivel_gravidade">Nível de gravidade</label>
                        <select id="nivel_gravidade" name="nivel_gravidade" required>
                            <?php foreach ($niveis as $nivel): ?>
                                <option value="<?= htmlspecialchars($nivel) ?>" <?= $nivelAtual === $nivel ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($nivel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group field-span-2">
                        <label for="riscos">Riscos</label>
                        <textarea
                            id="riscos"
                            name="riscos"
                            maxlength="<?= AlertaFormHelper::RISCOS_MAX ?>"
                            required
                        ><?= htmlspecialchars((string) ($alerta['riscos'] ?? '')) ?></textarea>
                        <div class="field-footer">
                            <span class="field-helper">Ajuste a descrição dos riscos sempre que houver mudança de área, gravidade ou vigência.</span>
                            <span class="char-counter" data-char-target="riscos"></span>
                        </div>
                    </div>

                    <div class="form-group field-span-2">
                        <label for="recomendacoes">Recomendações</label>
                        <textarea
                            id="recomendacoes"
                            name="recomendacoes"
                            maxlength="<?= AlertaFormHelper::RECOMENDACOES_MAX ?>"
                            required
                        ><?= htmlspecialchars((string) ($alerta['recomendacoes'] ?? '')) ?></textarea>
                        <div class="field-footer">
                            <span class="field-helper">As recomendações enviadas para PDF e comunicação serão regeneradas após salvar.</span>
                            <span class="char-counter" data-char-target="recomendacoes"></span>
                        </div>
                    </div>

                    <div class="territorio-preview-card field-span-2" id="territorioPreviewCard">
                        <div class="territorio-preview-header">
                            <div>
                                <strong>Regiões de integração e municípios afetados</strong>
                                <span>Esse quadro acompanha a geometria atual do mapa e ajuda a revisar o impacto territorial antes de salvar.</span>
                            </div>
                            <div class="territorio-preview-summary" id="territorioResumo">Carregando área atual.</div>
                        </div>
                        <div class="territorio-preview-list" id="territorioLista">
                            <div class="territorio-preview-empty">Nenhuma área territorial reconhecida ainda.</div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="edicao-anexos" class="alerta-form-section">
                <header class="alerta-section-header">
                    <span class="alerta-section-kicker">Seção 2</span>
                    <h2 class="alerta-section-title">Anexos, imagem, mapa e KML</h2>
                    <p class="alerta-section-text">
                        Selecione uma nova imagem somente quando realmente quiser substituir a atual. O KML pode ser trocado, mantido ou descartado.
                    </p>
                </header>

                <div class="upload-stack">
                    <div class="form-group">
                        <label for="informacoesInput">Imagem informativa</label>
                        <div class="upload-dropzone" id="informacoesDropzone" tabindex="0">
                            <input type="file" id="informacoesInput" name="informacoes" accept="image/jpeg,image/png,image/webp">
                            <span class="upload-dropzone-title">Atualize ou mantenha a imagem atual</span>
                            <span class="upload-dropzone-text">Arraste, solte, cole ou selecione uma nova imagem. Se desistir, descarte a seleção e a imagem atual permanece.</span>
                        </div>
                    </div>

                    <div class="upload-preview-card">
                        <div class="upload-preview" id="informacoesPreview"></div>
                        <div class="upload-meta">
                            <strong id="informacoesTitle">Imagem atual</strong>
                            <span id="informacoesDetails"><?= htmlspecialchars($imagemAtualNome !== '' ? $imagemAtualNome : 'Nenhuma imagem informativa cadastrada.') ?></span>
                        </div>
                        <div class="upload-actions">
                            <button type="button" class="btn btn-secondary" id="informacoesClear" hidden>Descartar nova imagem</button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="kmlInput">Arquivo KML</label>
                        <div class="upload-dropzone" id="kmlDropzone" tabindex="0">
                            <input type="file" id="kmlInput" name="kml" accept=".kml,.xml,application/vnd.google-earth.kml+xml,text/xml,application/xml">
                            <span class="upload-dropzone-title">Substitua ou mantenha o KML atual</span>
                            <span class="upload-dropzone-text">Arquivos com múltiplos polígonos ou multipolígonos são carregados no mapa e podem ser revisados antes do envio.</span>
                        </div>
                    </div>

                    <div class="kml-status-card">
                        <div class="kml-status-row">
                            <strong class="kml-status-title" id="kmlTitle">KML atual</strong>
                            <div class="upload-actions">
                                <button type="button" class="btn btn-secondary" id="kmlBrowse">Substituir KML</button>
                                <button type="button" class="btn btn-secondary" id="kmlClear" hidden>Remover KML</button>
                            </div>
                        </div>
                        <div class="kml-status-text" id="kmlDetails"><?= htmlspecialchars($kmlAtualVisivel !== '' ? $kmlAtualVisivel : 'Nenhum KML vinculado ao alerta.') ?></div>
                        <div class="kml-status-list" id="kmlPills"></div>
                    </div>

                    <div class="map-card">
                        <div class="map-card-header">
                            <div>
                                <div class="map-card-title">Área afetada</div>
                                <div class="map-card-text">A geometria salva no sistema será a que estiver visível no mapa no momento do envio.</div>
                            </div>
                            <span class="area-source-chip" id="areaOrigemBadge">Origem atual: <?= $areaOrigemAtual === 'KML' ? 'KML' : 'desenho/manual' ?></span>
                        </div>

                        <div id="mapa" class="alerta-map"></div>
                    </div>
                </div>
            </section>
        </div>

        <div class="alerta-form-actions">
            <div class="alerta-form-actions-left">
                <span class="alerta-inline-note">Salvar esta edição redefine a imagem do mapa em PDF e exige nova geração antes do envio para COMPDEC.</span>
            </div>

            <div class="alerta-form-actions-right">
                <a href="/pages/alertas/listar.php" class="btn btn-secondary">Voltar</a>
                <button type="submit" class="btn btn-primary">Salvar alterações</button>
            </div>
        </div>
    </form>
</section>

<?php include __DIR__ . '/../_footer.php'; ?>

</main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
<script id="alerta-editar-data" type="application/json"><?= json_encode([
    'initialGeojson' => !empty($alerta['area_geojson']) ? json_decode((string) $alerta['area_geojson'], true) : null,
    'initialAreaOrigem' => $areaOrigemAtual,
    'currentImageUrl' => $alerta['informacoes'] ?? '',
    'currentImageName' => $imagemAtualNome,
    'currentKmlName' => $kmlAtualVisivel,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="/assets/js/pages/alertas-form.js"></script>
<script src="/assets/js/pages/alertas-editar.js"></script>
</body>
</html>
