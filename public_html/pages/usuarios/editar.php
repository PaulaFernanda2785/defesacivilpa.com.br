<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Database.php';

Protect::check(['ADMIN']);

$db = Database::getConnection();
$usuario = $_SESSION['usuario'] ?? [];
$perfis = ['ADMIN', 'GESTOR', 'ANALISTA', 'OPERACOES'];
$statusDisponiveis = ['ATIVO', 'INATIVO'];

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: listar.php');
    exit;
}

$stmt = $db->prepare('SELECT * FROM usuarios WHERE id = ?');
$stmt->execute([$id]);
$usuarioEdit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuarioEdit) {
    header('Location: listar.php');
    exit;
}

$statusAtual = (string) ($usuarioEdit['status'] ?? '');
$classeStatusAtual = $statusAtual === 'ATIVO' ? 'usuarios-status-pill-ativo' : 'usuarios-status-pill-inativo';
$cssUsuariosListarPath = __DIR__ . '/../../assets/css/pages/usuarios-listar.css';
$cssUsuariosFormPath = __DIR__ . '/../../assets/css/pages/usuarios-form.css';
$cssUsuariosEditarPath = __DIR__ . '/../../assets/css/pages/usuarios-editar.css';
$cssUsuariosListarVersion = (string) ((int) @filemtime($cssUsuariosListarPath));
$cssUsuariosFormVersion = (string) ((int) @filemtime($cssUsuariosFormPath));
$cssUsuariosEditarVersion = (string) ((int) @filemtime($cssUsuariosEditarPath));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Editar Usuário</title>
<link rel="icon" type="image/x-icon" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/logo.cbmpa.ico">

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/painel.css">
<link rel="stylesheet" href="/assets/css/pages/alertas-form.css">
<link rel="stylesheet" href="/assets/css/pages/usuarios-listar.css?v=<?= htmlspecialchars($cssUsuariosListarVersion, ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="/assets/css/pages/usuarios-form.css?v=<?= htmlspecialchars($cssUsuariosFormVersion, ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="/assets/css/pages/usuarios-editar.css?v=<?= htmlspecialchars($cssUsuariosEditarVersion, ENT_QUOTES, 'UTF-8') ?>">
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
    'Editar usuário' => null,
];
include __DIR__ . '/../_breadcrumb.php';
?>

<section class="dashboard alerta-form-shell usuarios-shell usuarios-form-shell usuarios-editar-shell">
    <div class="usuarios-hero-grid usuarios-editar-hero-grid">
        <div class="alerta-form-hero usuarios-hero-panel usuarios-editar-hero-panel">
            <div class="alerta-form-lead usuarios-hero-copy usuarios-editar-hero-copy">
                <span class="alerta-form-kicker">Edição administrativa</span>
                <h1 class="alerta-form-title">Editar usuário do sistema</h1>
                <p class="alerta-form-description">
                    Atualize os dados cadastrais, revise o perfil operacional e mantenha a situação da conta alinhada
                    com a necessidade atual da equipe.
                </p>

                <div class="usuarios-hero-chip-row">
                    <span class="usuarios-hero-chip">Usuário alvo: <?= htmlspecialchars((string) ($usuarioEdit['nome'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="usuarios-hero-chip">Perfil atual: <?= htmlspecialchars((string) ($usuarioEdit['perfil'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="usuarios-hero-chip">Status: <?= htmlspecialchars($statusAtual !== '' ? $statusAtual : '-', ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <div class="usuarios-hero-actions usuarios-editar-hero-actions">
                    <a href="#edicao-dados" class="btn btn-primary">Dados da conta</a>
                    <a href="#edicao-acesso" class="btn btn-secondary">Perfil e acesso</a>
                    <a href="/pages/usuarios/senha.php?id=<?= (int) $usuarioEdit['id'] ?>" class="btn btn-secondary">Alterar senha</a>
                </div>
            </div>
        </div>

        <div class="usuarios-summary-grid usuarios-editar-summary-grid">
            <article class="usuarios-summary-card usuarios-summary-card-primary">
                <span class="usuarios-summary-label">Usuário alvo</span>
                <strong class="usuarios-summary-value"><?= htmlspecialchars((string) ($usuarioEdit['nome'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="usuarios-summary-note"><?= htmlspecialchars((string) ($usuarioEdit['email'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
            </article>

            <article class="usuarios-summary-card usuarios-summary-card-success">
                <span class="usuarios-summary-label">Acesso atual</span>
                <strong class="usuarios-summary-value"><?= htmlspecialchars((string) ($usuarioEdit['perfil'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="usuarios-summary-note">Status atual da conta: <?= htmlspecialchars($statusAtual !== '' ? $statusAtual : '-', ENT_QUOTES, 'UTF-8') ?>.</span>
            </article>

            <article class="usuarios-summary-card usuarios-summary-card-neutral">
                <span class="usuarios-summary-label">Cadastro original</span>
                <strong class="usuarios-summary-value"><?= htmlspecialchars(TimeHelper::formatUtcDateTime($usuarioEdit['criado_em'] ?? null, 'Sem dados'), ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="usuarios-summary-note">Referência temporal da criação da conta.</span>
            </article>

            <article class="usuarios-summary-card usuarios-summary-card-warning">
                <span class="usuarios-summary-label">Operação</span>
                <strong class="usuarios-summary-value"><?= htmlspecialchars((string) ($usuario['nome'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="usuarios-summary-note">Edição com rastreabilidade no histórico administrativo.</span>
            </article>
        </div>

        <aside class="usuarios-command-card usuarios-editar-command-card">
            <span class="usuarios-command-kicker">Comando de edição</span>
            <h2>Fluxo de atualização da conta</h2>
            <p>
                Revise os dados principais, ajuste perfil e status e salve somente após validar a conta correta.
                As alterações ficam registradas para auditoria administrativa.
            </p>

            <div class="usuarios-command-grid usuarios-editar-command-grid">
                <article class="usuarios-command-item">
                    <span>Etapa 1</span>
                    <strong>Validar identidade</strong>
                    <small>Confirme nome e e-mail da conta antes de aplicar qualquer alteração.</small>
                </article>

                <article class="usuarios-command-item">
                    <span>Etapa 2</span>
                    <strong>Revisar permissão</strong>
                    <small>Ajuste perfil e status conforme o escopo operacional atual.</small>
                </article>

                <article class="usuarios-command-item">
                    <span>Etapa 3</span>
                    <strong>Salvar com segurança</strong>
                    <small>Finalize apenas após revisar os campos para evitar alterações indevidas.</small>
                </article>
            </div>
        </aside>
    </div>

    <form class="alerta-form-panel usuarios-control-panel usuarios-editar-form-panel" method="post" action="atualizar.php">
        <?= Csrf::inputField() ?>
        <input type="hidden" name="id" value="<?= (int) $usuarioEdit['id'] ?>">

        <div class="alerta-form-grid usuarios-form-grid usuarios-editar-form-grid">
            <section id="edicao-dados" class="alerta-form-section">
                <header class="alerta-section-header">
                    <span class="alerta-section-kicker">Seção 1</span>
                    <h2 class="alerta-section-title">Dados da conta</h2>
                    <p class="alerta-section-text">
                        Revise as informações principais do usuário e mantenha os dados de identificação atualizados para a operação.
                    </p>
                </header>

                <div class="alerta-fields-grid">
                    <div class="form-group field-span-2">
                        <label for="nome">Nome completo</label>
                        <input
                            type="text"
                            id="nome"
                            name="nome"
                            autocomplete="name"
                            value="<?= htmlspecialchars((string) ($usuarioEdit['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            required
                        >
                        <span class="field-helper">Atualize o nome sempre que houver necessidade de correção cadastral ou mudança de identificação operacional.</span>
                    </div>

                    <div class="form-group field-span-2">
                        <label for="email">E-mail</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            autocomplete="email"
                            value="<?= htmlspecialchars((string) ($usuarioEdit['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            required
                        >
                        <span class="field-helper">O e-mail continua sendo o identificador principal da conta para autenticação e administração.</span>
                    </div>
                </div>
            </section>

            <section id="edicao-acesso" class="alerta-form-section">
                <header class="alerta-section-header">
                    <span class="alerta-section-kicker">Seção 2</span>
                    <h2 class="alerta-section-title">Perfil e manutenção de acesso</h2>
                    <p class="alerta-section-text">
                        Ajuste o perfil operacional, revise o status da conta e use os atalhos de manutenção para manter o acesso sob controle.
                    </p>
                </header>

                <div class="alerta-fields-grid usuarios-access-grid">
                    <div class="form-group">
                        <label for="perfil">Perfil</label>
                        <select id="perfil" name="perfil" required>
                            <?php foreach ($perfis as $perfil): ?>
                                <option value="<?= htmlspecialchars($perfil, ENT_QUOTES, 'UTF-8') ?>" <?= ($usuarioEdit['perfil'] ?? '') === $perfil ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($perfil, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="field-helper">Altere o perfil somente quando houver mudança real de responsabilidade ou escopo de acesso.</span>
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" required>
                            <?php foreach ($statusDisponiveis as $status): ?>
                                <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" <?= ($usuarioEdit['status'] ?? '') === $status ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="field-helper">Desative contas sem uso imediato para reduzir risco operacional e manter o controle de acessos.</span>
                    </div>
                </div>

                <div class="usuarios-note-stack">
                    <article class="usuarios-note-card">
                        <strong>Situação atual da conta</strong>
                        <div class="usuarios-role-list">
                            <span class="usuarios-role-pill"><?= htmlspecialchars((string) ($usuarioEdit['perfil'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="usuarios-status-pill <?= $classeStatusAtual ?>"><?= htmlspecialchars($statusAtual !== '' ? $statusAtual : '-', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </article>

                    <article class="usuarios-note-card">
                        <strong>Manutenção de senha</strong>
                        <span>Se houver necessidade de redefinição, utilize o atalho de alterar senha sem interferir nos demais dados cadastrais da conta.</span>
                    </article>

                    <article class="usuarios-note-card">
                        <strong>Rastreabilidade administrativa</strong>
                        <span>Toda alteração de perfil, status e dados principais permanece registrada no histórico administrativo do sistema.</span>
                    </article>
                </div>
            </section>
        </div>

        <div class="alerta-form-actions">
            <div class="alerta-form-actions-left">
                <span class="alerta-inline-note">Revise as alterações antes de salvar para manter coerência no controle de acessos.</span>
            </div>

            <div class="alerta-form-actions-right">
                <a href="/pages/usuarios/listar.php" class="btn btn-secondary">Voltar</a>
                <a href="/pages/usuarios/senha.php?id=<?= (int) $usuarioEdit['id'] ?>" class="btn btn-secondary">Alterar senha</a>
                <button type="submit" class="btn btn-primary">Salvar alterações</button>
            </div>
        </div>
    </form>
</section>

<?php include __DIR__ . '/../_footer.php'; ?>

</main>
</div>
</body>
</html>
