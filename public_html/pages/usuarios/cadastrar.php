<?php
require_once __DIR__ . '/../../app/Core/Protect.php';

Protect::check(['ADMIN']);

$usuario = $_SESSION['usuario'] ?? [];
$perfis = ['ADMIN', 'GESTOR', 'ANALISTA', 'OPERACOES'];
$statusDisponiveis = ['ATIVO', 'INATIVO'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Cadastrar Usuario</title>
<link rel="icon" type="image/x-icon" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/logo.cbmpa.ico">

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/painel.css">
<link rel="stylesheet" href="/assets/css/pages/alertas-form.css">
<link rel="stylesheet" href="/assets/css/pages/usuarios-form.css">
</head>

<body>
<div class="layout">

<?php include __DIR__ . '/../_sidebar.php'; ?>

<main class="content">

<?php include __DIR__ . '/../_topbar.php'; ?>

<?php
$breadcrumb = [
    'Painel' => '/pages/painel.php',
    'Usuarios' => '/pages/usuarios/listar.php',
    'Cadastrar usuario' => null,
];
include __DIR__ . '/../_breadcrumb.php';
?>

<section class="dashboard alerta-form-shell usuarios-form-shell">
    <div class="alerta-form-hero">
        <div class="alerta-form-lead">
            <span class="alerta-form-kicker">Cadastro administrativo</span>
            <h1 class="alerta-form-title">Novo usuario do sistema</h1>
            <p class="alerta-form-description">
                Cadastre um novo acesso administrativo no mesmo fluxo visual das telas operacionais do sistema.
                Defina os dados principais, configure o perfil e estabeleca a situacao inicial da conta antes de salvar.
            </p>
        </div>

        <div class="alerta-form-summary">
            <div class="alerta-summary-card">
                <span class="alerta-summary-label">Estrutura</span>
                <span class="alerta-summary-value">2 secoes organizadas</span>
                <span class="alerta-summary-note">Dados da conta de um lado e regras de acesso do outro.</span>
            </div>

            <div class="alerta-summary-card">
                <span class="alerta-summary-label">Perfis permitidos</span>
                <span class="alerta-summary-value"><?= count($perfis) ?> perfis</span>
                <span class="alerta-summary-note">ADMIN, GESTOR, ANALISTA e OPERACOES disponiveis para configuracao.</span>
            </div>

            <div class="alerta-summary-card">
                <span class="alerta-summary-label">Criacao</span>
                <span class="alerta-summary-value"><?= htmlspecialchars((string) ($usuario['nome'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="alerta-summary-note">O cadastro sera registrado com rastreabilidade no historico administrativo.</span>
            </div>
        </div>
    </div>

    <form class="alerta-form-panel" method="post" action="salvar.php">
        <?= Csrf::inputField() ?>

        <div class="alerta-form-grid usuarios-form-grid">
            <section class="alerta-form-section">
                <header class="alerta-section-header">
                    <span class="alerta-section-kicker">Secao 1</span>
                    <h2 class="alerta-section-title">Dados da conta</h2>
                    <p class="alerta-section-text">
                        Preencha as informacoes essenciais do usuario. Esses dados serao usados para autenticacao e identificacao na operacao.
                    </p>
                </header>

                <div class="alerta-fields-grid">
                    <div class="form-group field-span-2">
                        <label for="nome">Nome completo</label>
                        <input type="text" id="nome" name="nome" autocomplete="name" required>
                        <span class="field-helper">Informe o nome completo do operador ou gestor que utilizara a conta.</span>
                    </div>

                    <div class="form-group field-span-2">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" autocomplete="email" required>
                        <span class="field-helper">O e-mail sera usado como identificador da conta e para futuras comunicacoes administrativas.</span>
                    </div>

                    <div class="form-group field-span-2">
                        <label for="senha">Senha inicial</label>
                        <input type="password" id="senha" name="senha" autocomplete="new-password" required>
                        <div class="field-footer">
                            <span class="field-helper">Defina uma senha inicial segura. O usuario podera altera-la posteriormente pela gestao interna.</span>
                            <span class="usuarios-inline-chip">Cadastro interno</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="alerta-form-section">
                <header class="alerta-section-header">
                    <span class="alerta-section-kicker">Secao 2</span>
                    <h2 class="alerta-section-title">Perfil e liberacao de acesso</h2>
                    <p class="alerta-section-text">
                        Configure o perfil operacional e o status inicial da conta para que o acesso entre em producao conforme a necessidade da equipe.
                    </p>
                </header>

                <div class="alerta-fields-grid usuarios-access-grid">
                    <div class="form-group">
                        <label for="perfil">Perfil</label>
                        <select id="perfil" name="perfil" required>
                            <option value="">Selecione</option>
                            <?php foreach ($perfis as $perfil): ?>
                                <option value="<?= htmlspecialchars($perfil, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($perfil, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="field-helper">Escolha o nivel de permissao mais adequado para a funcao operacional da conta.</span>
                    </div>

                    <div class="form-group">
                        <label for="status">Status inicial</label>
                        <select id="status" name="status">
                            <?php foreach ($statusDisponiveis as $status): ?>
                                <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="field-helper">Defina se a conta ja nasce pronta para uso ou se fica bloqueada ate liberacao posterior.</span>
                    </div>
                </div>

                <div class="usuarios-note-stack">
                    <article class="usuarios-note-card">
                        <strong>Orientacao de seguranca</strong>
                        <span>Evite compartilhar a senha inicial por canais inseguros. Sempre que possivel, realize a troca no primeiro acesso do usuario.</span>
                    </article>

                    <article class="usuarios-note-card">
                        <strong>Perfis operacionais</strong>
                        <div class="usuarios-role-list">
                            <?php foreach ($perfis as $perfil): ?>
                                <span class="usuarios-role-pill"><?= htmlspecialchars($perfil, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="usuarios-note-card">
                        <strong>Controle administrativo</strong>
                        <span>Depois do cadastro, a conta podera ser editada, ter senha redefinida e status alterado pela tela de usuarios.</span>
                    </article>
                </div>
            </section>
        </div>

        <div class="alerta-form-actions">
            <div class="alerta-form-actions-left">
                <span class="alerta-inline-note">Revise os dados antes de salvar para evitar retrabalho no controle de acessos.</span>
            </div>

            <div class="alerta-form-actions-right">
                <a href="/pages/usuarios/listar.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Cadastrar usuario</button>
            </div>
        </div>
    </form>
</section>

<?php include __DIR__ . '/../_footer.php'; ?>

</main>
</div>
</body>
</html>
