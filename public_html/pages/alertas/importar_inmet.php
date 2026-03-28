<?php
require_once __DIR__ . '/../../app/Core/Protect.php';

Protect::check(['ADMIN', 'GESTOR', 'ANALISTA']);

$erroImportacao = trim((string) ($_GET['erro'] ?? ''));
$usuario = $_SESSION['usuario'] ?? [];
$cssImportarPath = __DIR__ . '/../../assets/css/pages/alertas-importar.css';
$cssImportarVersion = (string) ((int) @filemtime($cssImportarPath));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Importar Alerta do INMET</title>
<link rel="icon" type="image/x-icon" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/logo.cbmpa.ico">

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/painel.css">
<link rel="stylesheet" href="/assets/css/alertas-lista.css">
<link rel="stylesheet" href="/assets/css/pages/alertas-form.css">
<link rel="stylesheet" href="/assets/css/pages/usuarios-listar.css">
<link rel="stylesheet" href="/assets/css/pages/alertas-importar.css?v=<?= htmlspecialchars($cssImportarVersion, ENT_QUOTES, 'UTF-8') ?>">
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
    'Importar INMET' => null,
];
include __DIR__ . '/../_breadcrumb.php';
?>

<section class="dashboard alerta-form-shell usuarios-shell alerta-importar-shell">
    <div class="usuarios-hero-grid alerta-importar-hero-grid">
        <div class="alerta-form-hero usuarios-hero-panel alerta-importar-hero-panel">
            <div class="alerta-form-lead usuarios-hero-copy alerta-importar-hero-copy">
                <span class="alerta-form-kicker">Importação oficial</span>
                <h1 class="alerta-form-title">Importar alerta do INMET</h1>
                <p class="alerta-form-description">
                    Cole a URL oficial do alerta, confira a prévia e só depois confirme a entrada no sistema.
                    O fluxo foi alinhado com o novo padrão visual das páginas de alerta.
                </p>

                <div class="usuarios-hero-chip-row alerta-importar-hero-chip-row">
                    <span class="usuarios-hero-chip">Origem: INMET oficial</span>
                    <span class="usuarios-hero-chip">Entrada: URL do alerta</span>
                    <span class="usuarios-hero-chip">Saída: prévia obrigatória</span>
                </div>

                <div class="usuarios-hero-actions alerta-importar-hero-actions">
                    <a href="#importar-url" class="btn btn-primary">Inserir URL</a>
                    <a href="#importar-orientacoes" class="btn btn-secondary">Ver orientações</a>
                </div>
            </div>
        </div>

        <div class="usuarios-summary-grid alerta-importar-summary-grid">
            <article class="usuarios-summary-card usuarios-summary-card-primary">
                <span class="usuarios-summary-label">Origem</span>
                <strong class="usuarios-summary-value">INMET oficial</strong>
                <span class="usuarios-summary-note">A importação considera somente URLs oficiais do Instituto Nacional de Meteorologia.</span>
            </article>

            <article class="usuarios-summary-card usuarios-summary-card-success">
                <span class="usuarios-summary-label">Entrada</span>
                <strong class="usuarios-summary-value">URL do alerta</strong>
                <span class="usuarios-summary-note">Basta colar a URL do aviso para o sistema montar a prévia antes de salvar.</span>
            </article>

            <article class="usuarios-summary-card usuarios-summary-card-neutral">
                <span class="usuarios-summary-label">Resultado</span>
                <strong class="usuarios-summary-value">Prévia obrigatória</strong>
                <span class="usuarios-summary-note">Evento, gravidade, vigência e área geográfica são revisados antes da confirmação.</span>
            </article>

            <article class="usuarios-summary-card usuarios-summary-card-warning">
                <span class="usuarios-summary-label">Operador</span>
                <strong class="usuarios-summary-value"><?= htmlspecialchars((string) ($usuario['nome'] ?? 'Não identificado'), ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="usuarios-summary-note">Perfil atual: <?= htmlspecialchars((string) ($usuario['perfil'] ?? 'Não informado'), ENT_QUOTES, 'UTF-8') ?>.</span>
            </article>
        </div>

        <aside class="usuarios-command-card alerta-importar-command-card">
            <span class="usuarios-command-kicker">Comando de importação</span>
            <h2>Roteiro de validação</h2>
            <p>
                Cole a URL oficial, abra a prévia e confirme a importação somente após revisar
                vigência, gravidade e cobertura territorial do aviso.
            </p>

            <div class="usuarios-command-grid alerta-importar-command-grid">
                <article class="usuarios-command-item">
                    <span>Etapa 1</span>
                    <strong>URL oficial</strong>
                    <small>Cole um link válido do domínio do INMET.</small>
                </article>

                <article class="usuarios-command-item">
                    <span>Etapa 2</span>
                    <strong>Prévia técnica</strong>
                    <small>Revise campos extraídos automaticamente antes de salvar.</small>
                </article>

                <article class="usuarios-command-item">
                    <span>Etapa 3</span>
                    <strong>Confirmação</strong>
                    <small>Finalize apenas quando dados e território estiverem corretos.</small>
                </article>
            </div>
        </aside>
    </div>

    <?php if ($erroImportacao !== ''): ?>
        <div class="alerta-callout alerta-importar-callout-error">
            <strong>Falha ao importar o alerta</strong>
            <?= htmlspecialchars($erroImportacao, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form
        id="importarInmetForm"
        class="alerta-form-panel usuarios-control-panel alerta-importar-form-panel"
        method="post"
        action="preview_inmet.php"
        onsubmit="return validarURLInmet();"
    >
        <?= Csrf::inputField() ?>

        <div class="alerta-form-grid alerta-importar-form-grid">
            <section id="importar-url" class="alerta-form-section">
                <header class="alerta-section-header">
                    <span class="alerta-section-kicker">Seção 1</span>
                    <h2 class="alerta-section-title">URL do alerta oficial</h2>
                    <p class="alerta-section-text">
                        Informe o link oficial do alerta do INMET. A etapa seguinte sempre mostra a prévia antes da importação definitiva.
                    </p>
                </header>

                <div class="alerta-fields-grid alerta-importar-grid">
                    <div class="form-group field-span-2">
                        <label for="inmet_url">URL do alerta INMET</label>
                        <input
                            type="url"
                            id="inmet_url"
                            name="inmet_url"
                            placeholder="https://avisos.inmet.gov.br/53718"
                            required
                        >
                        <div class="field-footer">
                            <span class="field-helper">
                                Use a URL completa do alerta oficial. O sistema valida o domínio antes de consultar o XML do INMET.
                            </span>
                        </div>
                        <div class="alerta-importar-url-actions">
                            <button type="button" class="btn btn-secondary" id="limparInmetUrl">Limpar URL</button>
                            <span class="alerta-importar-status-note" id="loadingImportacaoHint">
                                Cole a URL oficial do INMET para abrir a prévia de confirmação.
                            </span>
                        </div>
                    </div>

                    <div class="alerta-callout field-span-2">
                        <strong>Atenção técnica</strong>
                        A data do alerta, a vigência, a numeração gerada internamente e a área geográfica serão baseadas na publicação oficial do INMET, e não no momento da importação.
                    </div>

                    <div id="loadingImportacao" class="alerta-importar-loading field-span-2" hidden aria-live="polite">
                        Aguardando o início da importação.
                    </div>
                </div>
            </section>

            <section id="importar-orientacoes" class="alerta-form-section">
                <header class="alerta-section-header">
                    <span class="alerta-section-kicker">Seção 2</span>
                    <h2 class="alerta-section-title">Orientações operacionais</h2>
                    <p class="alerta-section-text">
                        Confira o passo a passo e o que o sistema reaproveita automaticamente antes de iniciar a importação.
                    </p>
                </header>

                <div class="upload-stack">
                    <div class="alerta-importar-card">
                        <h3>Passo a passo</h3>
                        <ol class="alerta-importar-steps">
                            <li>Abra o alerta no portal oficial do INMET.</li>
                            <li>Copie a URL completa do aviso.</li>
                            <li>Cole a URL no campo ao lado.</li>
                            <li>Avance para a prévia e confirme somente após revisar os dados.</li>
                        </ol>
                    </div>

                    <div class="alerta-importar-card">
                        <h3>O que entra automaticamente</h3>
                        <ul class="alerta-importar-list">
                            <li>Tipo de evento, gravidade e vigência oficiais.</li>
                            <li>Descrição de riscos e recomendações publicadas pelo INMET.</li>
                            <li>Polígono geográfico do aviso, quando disponível no CAP.</li>
                            <li>Identificação territorial para municípios e regiões afetadas.</li>
                        </ul>
                    </div>

                    <div class="alerta-importar-card">
                        <h3>Exemplos de URL aceitas</h3>
                        <div class="alerta-importar-examples">
                            <code>https://avisos.inmet.gov.br/53718</code>
                            <code>https://alertas2.inmet.gov.br/53718</code>
                            <code>https://apiprevmet3.inmet.gov.br/avisos/rss/53718</code>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="alerta-form-actions">
            <div class="alerta-form-actions-left">
                <span class="alerta-inline-note">A importação não grava o alerta imediatamente. Primeiro o sistema abre a prévia para validação.</span>
            </div>

            <div class="alerta-form-actions-right">
                <a href="/pages/alertas/listar.php" class="btn btn-secondary">Voltar</a>
                <button type="submit" class="btn btn-primary">Importar alerta</button>
            </div>
        </div>
    </form>
</section>

<?php include __DIR__ . '/../_footer.php'; ?>

</main>
</div>

<script src="/assets/js/pages/alertas-importar_inmet.js"></script>
</body>
</html>
