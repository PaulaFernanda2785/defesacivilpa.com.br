<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Helpers/AlertaFormHelper.php';

Protect::check(['ADMIN', 'GESTOR', 'ANALISTA']);

$eventos = AlertaFormHelper::eventos();
$niveis = AlertaFormHelper::niveis();
$fontes = AlertaFormHelper::fontes();
$usuario = $_SESSION['usuario'] ?? [];
$cssCadastroPath = __DIR__ . '/../../assets/css/pages/alertas-cadastrar.css';
$cssCadastroVersion = (string) ((int) @filemtime($cssCadastroPath));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Cadastrar Alerta</title>
<link rel="icon" type="image/x-icon" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/logo.cbmpa.ico">

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/painel.css">
<link rel="stylesheet" href="/assets/css/alertas-lista.css">
<link rel="stylesheet" href="/assets/css/pages/alertas-form.css">
<link rel="stylesheet" href="/assets/css/pages/usuarios-listar.css">
<link rel="stylesheet" href="/assets/css/pages/alertas-cadastrar.css?v=<?= htmlspecialchars($cssCadastroVersion, ENT_QUOTES, 'UTF-8') ?>">

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
    'Cadastrar alerta' => null,
];
include __DIR__ . '/../_breadcrumb.php';
?>

<section class="dashboard alerta-form-shell usuarios-shell alerta-cadastro-shell">
    <div class="usuarios-hero-grid alerta-cadastro-hero-grid">
        <div class="alerta-form-hero usuarios-hero-panel alerta-cadastro-hero-panel">
            <div class="alerta-form-lead usuarios-hero-copy alerta-cadastro-hero-copy">
                <span class="alerta-form-kicker">Cadastro operacional</span>
                <h1 class="alerta-form-title">Novo alerta multirriscos</h1>
                <p class="alerta-form-description">
                    Registre o evento, defina a vigencia, anexe a imagem informativa e monte a area afetada com desenho manual
                    ou com arquivo KML. O numero do alerta sera gerado automaticamente quando o cadastro for concluido.
                </p>

                <div class="usuarios-hero-chip-row alerta-cadastro-hero-chip-row">
                    <span class="usuarios-hero-chip"><?= count($eventos) ?> tipos de evento</span>
                    <span class="usuarios-hero-chip"><?= count($niveis) ?> niveis de gravidade</span>
                    <span class="usuarios-hero-chip"><?= count($fontes) ?> fontes habilitadas</span>
                </div>

                <div class="usuarios-hero-actions alerta-cadastro-hero-actions">
                    <a href="#cadastro-dados" class="btn btn-primary">Preencher dados</a>
                    <a href="#cadastro-anexos" class="btn btn-secondary">Anexos e mapa</a>
                </div>
            </div>
        </div>

        <div class="usuarios-summary-grid alerta-cadastro-summary-grid">
            <article class="usuarios-summary-card usuarios-summary-card-primary">
                <span class="usuarios-summary-label">Estrutura</span>
                <strong class="usuarios-summary-value">2 secoes organizadas</strong>
                <span class="usuarios-summary-note">Informacoes do alerta de um lado, anexos e mapa do outro.</span>
            </article>

            <article class="usuarios-summary-card usuarios-summary-card-success">
                <span class="usuarios-summary-label">Uploads</span>
                <strong class="usuarios-summary-value">Imagem e KML</strong>
                <span class="usuarios-summary-note">Arraste, solte, cole ou selecione arquivos com validacao imediata.</span>
            </article>

            <article class="usuarios-summary-card usuarios-summary-card-neutral">
                <span class="usuarios-summary-label">Mapa</span>
                <strong class="usuarios-summary-value">Area obrigatoria</strong>
                <span class="usuarios-summary-note">O alerta so sera salvo quando houver uma geometria valida no mapa.</span>
            </article>

            <article class="usuarios-summary-card usuarios-summary-card-warning">
                <span class="usuarios-summary-label">Operador</span>
                <strong class="usuarios-summary-value"><?= htmlspecialchars((string) ($usuario['nome'] ?? 'Nao identificado'), ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="usuarios-summary-note">Perfil atual: <?= htmlspecialchars((string) ($usuario['perfil'] ?? 'Nao informado'), ENT_QUOTES, 'UTF-8') ?>.</span>
            </article>
        </div>

        <aside class="usuarios-command-card alerta-cadastro-command-card">
            <span class="usuarios-command-kicker">Comando de cadastro</span>
            <h2>Fluxo recomendado</h2>
            <p>
                Preencha os dados tecnicos, valide a cobertura territorial e finalize o envio somente apos revisar riscos,
                recomendacoes e geometria no mapa.
            </p>

            <div class="usuarios-command-grid alerta-cadastro-command-grid">
                <article class="usuarios-command-item">
                    <span>Etapa 1</span>
                    <strong>Dados tecnicos</strong>
                    <small>Defina data, vigencia, tipo de evento, gravidade e texto operacional.</small>
                </article>

                <article class="usuarios-command-item">
                    <span>Etapa 2</span>
                    <strong>Anexos e mapa</strong>
                    <small>Carregue imagem e KML ou desenhe manualmente a area afetada.</small>
                </article>

                <article class="usuarios-command-item">
                    <span>Etapa 3</span>
                    <strong>Salvar alerta</strong>
                    <small>O numero do alerta sera gerado automaticamente no fechamento do cadastro.</small>
                </article>
            </div>
        </aside>
    </div>

    <form id="alertaForm" class="alerta-form-panel usuarios-control-panel alerta-cadastro-form-panel" method="post" action="salvar.php" enctype="multipart/form-data">
        <?= Csrf::inputField() ?>
        <input type="hidden" name="area_geojson" id="area_geojson">
        <input type="hidden" name="area_origem" id="area_origem" value="DESENHO">

        <div class="alerta-form-grid alerta-cadastro-form-grid">
            <section id="cadastro-dados" class="alerta-form-section">
                <header class="alerta-section-header">
                    <span class="alerta-section-kicker">Secao 1</span>
                    <h2 class="alerta-section-title">Informacoes do alerta</h2>
                    <p class="alerta-section-text">
                        Preencha os dados tecnicos do evento com atencao. Os campos abaixo seguem as regras operacionais do sistema.
                    </p>
                </header>

                <div class="alerta-fields-grid">
                    <div class="form-group">
                        <label for="data_alerta">Data do alerta</label>
                        <input type="date" id="data_alerta" name="data_alerta" required>
                    </div>

                    <div class="form-group">
                        <label for="fonte">Fonte do alerta</label>
                        <select id="fonte" name="fonte" required>
                            <option value="">Selecione</option>
                            <?php foreach ($fontes as $fonte): ?>
                                <option value="<?= htmlspecialchars($fonte) ?>">
                                    <?= htmlspecialchars($fonte) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="inicio_alerta">Inicio da vigencia</label>
                        <input type="datetime-local" id="inicio_alerta" name="inicio_alerta" required>
                    </div>

                    <div class="form-group">
                        <label for="fim_alerta">Fim da vigencia</label>
                        <input type="datetime-local" id="fim_alerta" name="fim_alerta" required>
                    </div>

                    <div class="form-group">
                        <label for="tipo_evento">Tipo de evento</label>
                        <select id="tipo_evento" name="tipo_evento" required>
                            <option value="">Selecione</option>
                            <?php foreach ($eventos as $evento): ?>
                                <option value="<?= htmlspecialchars($evento) ?>"><?= htmlspecialchars($evento) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="nivel_gravidade">Nivel de gravidade</label>
                        <select id="nivel_gravidade" name="nivel_gravidade" required>
                            <option value="">Selecione</option>
                            <?php foreach ($niveis as $nivel): ?>
                                <option value="<?= htmlspecialchars($nivel) ?>"><?= htmlspecialchars($nivel) ?></option>
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
                        ></textarea>
                        <div class="field-footer">
                            <span class="field-helper">Descreva os principais riscos operacionais e territoriais associados ao alerta.</span>
                            <span class="char-counter" data-char-target="riscos"></span>
                        </div>
                    </div>

                    <div class="form-group field-span-2">
                        <label for="recomendacoes">Recomendacoes</label>
                        <textarea
                            id="recomendacoes"
                            name="recomendacoes"
                            maxlength="<?= AlertaFormHelper::RECOMENDACOES_MAX ?>"
                            required
                        ></textarea>
                        <div class="field-footer">
                            <span class="field-helper">Informe orientacoes praticas e objetivas para resposta e protecao.</span>
                            <span class="char-counter" data-char-target="recomendacoes"></span>
                        </div>
                    </div>

                    <div class="territorio-preview-card field-span-2" id="territorioPreviewCard">
                        <div class="territorio-preview-header">
                            <div>
                                <strong>Regioes de integracao e municipios afetados</strong>
                                <span>Atualizado automaticamente quando houver desenho no mapa ou KML carregado.</span>
                            </div>
                            <div class="territorio-preview-summary" id="territorioResumo">Aguardando area valida.</div>
                        </div>
                        <div class="territorio-preview-list" id="territorioLista">
                            <div class="territorio-preview-empty">Desenhe no mapa ou carregue um KML para identificar as regioes afetadas.</div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="cadastro-anexos" class="alerta-form-section">
                <header class="alerta-section-header">
                    <span class="alerta-section-kicker">Secao 2</span>
                    <h2 class="alerta-section-title">Anexos, imagem, mapa e KML</h2>
                    <p class="alerta-section-text">
                        Envie materiais de apoio, visualize a imagem antes de confirmar e use KML com geometrias de area para popular o mapa.
                    </p>
                </header>

                <div class="upload-stack">
                    <div class="alerta-callout">
                        <strong>Fluxo recomendado</strong>
                        Carregue a imagem informativa se houver, depois envie um KML ou desenhe manualmente a area afetada. O sistema grava apenas geometrias de area.
                    </div>

                    <div class="form-group">
                        <label for="informacoesInput">Imagem informativa</label>
                        <div class="upload-dropzone" id="informacoesDropzone" tabindex="0">
                            <input type="file" id="informacoesInput" name="informacoes" accept="image/jpeg,image/png,image/webp">
                            <span class="upload-dropzone-title">Arraste, solte, cole ou selecione a imagem</span>
                            <span class="upload-dropzone-text">Formatos aceitos: JPG, PNG e WEBP, com limite de 5 MB.</span>
                        </div>
                    </div>

                    <div class="upload-preview-card">
                        <div class="upload-preview" id="informacoesPreview"></div>
                        <div class="upload-meta">
                            <strong id="informacoesTitle">Imagem informativa</strong>
                            <span id="informacoesDetails">Arraste, cole ou selecione uma imagem JPG, PNG ou WEBP com ate 5 MB.</span>
                        </div>
                        <div class="upload-actions">
                            <button type="button" class="btn btn-secondary" id="informacoesClear" hidden>Descartar nova imagem</button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="kmlInput">Arquivo KML</label>
                        <div class="upload-dropzone" id="kmlDropzone" tabindex="0">
                            <input type="file" id="kmlInput" name="kml" accept=".kml,.xml,application/vnd.google-earth.kml+xml,text/xml,application/xml">
                            <span class="upload-dropzone-title">Carregue o KML da area afetada</span>
                            <span class="upload-dropzone-text">O mapa aceita KML com multiplas geometrias de area, incluindo poligonos e multipoligonos.</span>
                        </div>
                    </div>

                    <div class="kml-status-card">
                        <div class="kml-status-row">
                            <strong class="kml-status-title" id="kmlTitle">KML opcional</strong>
                            <button type="button" class="btn btn-secondary" id="kmlClear" hidden>Remover KML</button>
                        </div>
                        <div class="kml-status-text" id="kmlDetails">Arraste ou selecione um arquivo KML para carregar a area automaticamente.</div>
                        <div class="kml-status-list" id="kmlPills"></div>
                    </div>

                    <div class="map-card">
                        <div class="map-card-header">
                            <div>
                                <div class="map-card-title">Area afetada</div>
                                <div class="map-card-text">Use desenho manual ou edite a area carregada via KML antes de salvar.</div>
                            </div>
                            <span class="area-source-chip" id="areaOrigemBadge">Origem atual: desenho/manual</span>
                        </div>

                        <div id="mapa" class="alerta-map"></div>
                    </div>
                </div>
            </section>
        </div>

        <div class="alerta-form-actions">
            <div class="alerta-form-actions-left">
                <span class="alerta-inline-note">Ao salvar, o numero do alerta sera criado automaticamente pelo sistema.</span>
            </div>

            <div class="alerta-form-actions-right">
                <a href="/pages/alertas/listar.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Salvar alerta</button>
            </div>
        </div>
    </form>
</section>

<?php include __DIR__ . '/../_footer.php'; ?>

</main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
<script src="/assets/js/pages/alertas-form.js"></script>
<script src="/assets/js/pages/alertas-cadastrar.js"></script>
</body>
</html>
