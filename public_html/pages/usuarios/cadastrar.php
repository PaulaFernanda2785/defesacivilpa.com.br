<?php
require_once __DIR__ . '/../../app/Core/Protect.php';

Protect::check(['ADMIN']);

$usuario = $_SESSION['usuario'] ?? [];
$perfis = ['ADMIN', 'GESTOR', 'ANALISTA', 'OPERACOES'];
$statusDisponiveis = ['ATIVO', 'INATIVO'];
$totalPerfis = count($perfis);
$statusInicialPadrao = (string) ($statusDisponiveis[0] ?? 'ATIVO');
$cssUsuariosListarPath = __DIR__ . '/../../assets/css/pages/usuarios-listar.css';
$cssUsuariosFormPath = __DIR__ . '/../../assets/css/pages/usuarios-form.css';
$cssUsuariosCadastrarPath = __DIR__ . '/../../assets/css/pages/usuarios-cadastrar.css';
$cssUsuariosListarVersion = (string) ((int) @filemtime($cssUsuariosListarPath));
$cssUsuariosFormVersion = (string) ((int) @filemtime($cssUsuariosFormPath));
$cssUsuariosCadastrarVersion = (string) ((int) @filemtime($cssUsuariosCadastrarPath));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Cadastrar Usuário</title>
<link rel="icon" type="image/x-icon" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/logo.cbmpa.ico">

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/painel.css">
<link rel="stylesheet" href="/assets/css/pages/alertas-form.css">
<link rel="stylesheet" href="/assets/css/pages/usuarios-listar.css?v=<?= htmlspecialchars($cssUsuariosListarVersion, ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="/assets/css/pages/usuarios-form.css?v=<?= htmlspecialchars($cssUsuariosFormVersion, ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="/assets/css/pages/usuarios-cadastrar.css?v=<?= htmlspecialchars($cssUsuariosCadastrarVersion, ENT_QUOTES, 'UTF-8') ?>">
</head>

<body>
<div class="layout">

<?php include __DIR__ . '/../_sidebar.php'; ?>

<main class="content">

<?php include __DIR__ . '/../_topbar.php'; ?>

<?php
$breadcrumb = [
    'Painel' => '/pages/painel.php',
    'Usuários' => '/pages/usuarios/listar.php',
    'Cadastrar usuário' => null,
];
include __DIR__ . '/../_breadcrumb.php';
?>

<section class="dashboard alerta-form-shell usuarios-shell usuarios-form-shell usuarios-cadastrar-shell">
    <div class="usuarios-hero-grid usuarios-cadastrar-hero-grid">
        <div class="alerta-form-hero usuarios-hero-panel usuarios-cadastrar-hero-panel">
            <div class="alerta-form-lead usuarios-hero-copy usuarios-cadastrar-hero-copy">
                <span class="alerta-form-kicker">Cadastro administrativo</span>
                <h1 class="alerta-form-title">Novo usuário do sistema</h1>
                <p class="alerta-form-description">
                    Cadastre um novo acesso administrativo com o mesmo padrão visual e operacional
                    das telas de edição de usuário e alteração de senha.
                </p>

                <div class="usuarios-hero-chip-row usuarios-cadastrar-hero-chip-row">
                    <span class="usuarios-hero-chip">Operador: <?= htmlspecialchars((string) ($usuario['nome'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="usuarios-hero-chip"><?= $totalPerfis ?> perfis disponíveis</span>
                    <span class="usuarios-hero-chip">Status inicial sugerido: <?= htmlspecialchars($statusInicialPadrao, ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <div class="usuarios-hero-actions usuarios-cadastrar-hero-actions">
                    <a href="#cadastro-dados" class="btn btn-primary">Dados da conta</a>
                    <a href="#cadastro-acesso" class="btn btn-secondary">Perfil e acesso</a>
                    <a href="/pages/usuarios/listar.php" class="btn btn-secondary">Voltar para usuários</a>
                </div>
            </div>
        </div>

        <div class="usuarios-summary-grid usuarios-cadastrar-summary-grid">
            <article class="usuarios-summary-card usuarios-summary-card-primary">
                <span class="usuarios-summary-label">Criação</span>
                <strong class="usuarios-summary-value"><?= htmlspecialchars((string) ($usuario['nome'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="usuarios-summary-note">Operação registrada com rastreabilidade no histórico administrativo.</span>
            </article>

            <article class="usuarios-summary-card usuarios-summary-card-success">
                <span class="usuarios-summary-label">Perfis permitidos</span>
                <strong class="usuarios-summary-value"><?= $totalPerfis ?> perfis</strong>
                <span class="usuarios-summary-note">ADMIN, GESTOR, ANALISTA e OPERACOES disponíveis para configuração.</span>
            </article>

            <article class="usuarios-summary-card usuarios-summary-card-neutral">
                <span class="usuarios-summary-label">Status inicial</span>
                <strong class="usuarios-summary-value"><?= htmlspecialchars($statusInicialPadrao, ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="usuarios-summary-note">A conta pode ser ajustada para ATIVO ou INATIVO durante o cadastro.</span>
            </article>

            <article class="usuarios-summary-card usuarios-summary-card-warning">
                <span class="usuarios-summary-label">Estrutura</span>
                <strong class="usuarios-summary-value">2 seções</strong>
                <span class="usuarios-summary-note">Dados da conta e parâmetros de acesso no mesmo fluxo guiado.</span>
            </article>
        </div>

        <aside class="usuarios-command-card usuarios-cadastrar-command-card">
            <span class="usuarios-command-kicker">Comando de cadastro</span>
            <h2>Fluxo de criação da conta</h2>
            <p>
                Preencha os dados essenciais, configure perfil e status e finalize o cadastro com revisão completa.
                O processo segue o mesmo padrão visual das telas de edição e redefinição de senha.
            </p>

            <div class="usuarios-command-grid usuarios-cadastrar-command-grid">
                <article class="usuarios-command-item">
                    <span>Etapa 1</span>
                    <strong>Identificar usuário</strong>
                    <small>Informe nome completo e e-mail institucional para garantir identificação correta.</small>
                </article>

                <article class="usuarios-command-item">
                    <span>Etapa 2</span>
                    <strong>Definir acesso</strong>
                    <small>Selecione perfil operacional e status inicial conforme necessidade da equipe.</small>
                </article>

                <article class="usuarios-command-item">
                    <span>Etapa 3</span>
                    <strong>Salvar com segurança</strong>
                    <small>Revise os campos antes de concluir para evitar retrabalho administrativo.</small>
                </article>
            </div>
        </aside>
    </div>

    <form class="alerta-form-panel usuarios-control-panel usuarios-cadastrar-form-panel" method="post" action="salvar.php">
        <?= Csrf::inputField() ?>

        <div class="alerta-form-grid usuarios-form-grid usuarios-cadastrar-form-grid">
            <section id="cadastro-dados" class="alerta-form-section">
                <header class="alerta-section-header">
                    <span class="alerta-section-kicker">Seção 1</span>
                    <h2 class="alerta-section-title">Dados da conta</h2>
                    <p class="alerta-section-text">
                        Preencha as informações essenciais do usuário. Esses dados serão usados para autenticação e identificação na operação.
                    </p>
                </header>

                <div class="alerta-fields-grid">
                    <div class="form-group field-span-2">
                        <label for="nome">Nome completo</label>
                        <input type="text" id="nome" name="nome" autocomplete="name" required>
                        <span class="field-helper">Informe o nome completo do operador ou gestor que utilizará a conta.</span>
                    </div>

                    <div class="form-group field-span-2">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" autocomplete="email" required>
                        <span class="field-helper">O e-mail será usado como identificador da conta e para futuras comunicações administrativas.</span>
                    </div>

                    <div class="form-group field-span-2">
                        <label for="senha">Senha inicial</label>
                        <input type="password" id="senha" name="senha" autocomplete="new-password" required>
                        <div class="field-footer">
                            <span class="field-helper">Defina uma senha inicial segura. O usuário poderá alterá-la posteriormente pela gestão interna.</span>
                            <span class="usuarios-inline-chip">Cadastro interno</span>
                        </div>
                    </div>
                </div>
            </section>

            <section id="cadastro-acesso" class="alerta-form-section">
                <header class="alerta-section-header">
                    <span class="alerta-section-kicker">Seção 2</span>
                    <h2 class="alerta-section-title">Perfil e liberação de acesso</h2>
                    <p class="alerta-section-text">
                        Configure o perfil operacional e o status inicial da conta para que o acesso entre em produção conforme a necessidade da equipe.
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
                        <span class="field-helper">Escolha o nível de permissão mais adequado para a função operacional da conta.</span>
                    </div>

                    <div class="form-group">
                        <label for="status">Status inicial</label>
                        <select id="status" name="status">
                            <?php foreach ($statusDisponiveis as $status): ?>
                                <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="field-helper">Defina se a conta já nasce pronta para uso ou se fica bloqueada até liberação posterior.</span>
                    </div>
                </div>

                <div class="usuarios-note-stack">
                    <article class="usuarios-note-card">
                        <strong>Orientação de segurança</strong>
                        <span>Evite compartilhar a senha inicial por canais inseguros. Sempre que possível, realize a troca no primeiro acesso do usuário.</span>
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
                        <span>Depois do cadastro, a conta poderá ser editada, ter senha redefinida e status alterado pela tela de usuários.</span>
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
                <button type="submit" class="btn btn-primary">Cadastrar usuário</button>
            </div>
        </div>
    </form>
</section>

<?php include __DIR__ . '/../_footer.php'; ?>

</main>
</div>
</body>
</html>
