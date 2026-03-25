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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Editar Usuario</title>
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
    'Editar usuario' => null,
];
include __DIR__ . '/../_breadcrumb.php';
?>

<section class="dashboard alerta-form-shell usuarios-form-shell">
    <div class="alerta-form-hero">
        <div class="alerta-form-lead">
            <span class="alerta-form-kicker">Edicao administrativa</span>
            <h1 class="alerta-form-title">Editar usuario do sistema</h1>
            <p class="alerta-form-description">
                Atualize os dados cadastrais, revise o perfil operacional e mantenha a situacao da conta alinhada
                com a necessidade atual da equipe. A tela segue o mesmo padrao visual do cadastro de usuario.
            </p>
        </div>

        <div class="alerta-form-summary">
            <div class="alerta-summary-card">
                <span class="alerta-summary-label">Usuario alvo</span>
                <span class="alerta-summary-value"><?= htmlspecialchars((string) ($usuarioEdit['nome'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="alerta-summary-note"><?= htmlspecialchars((string) ($usuarioEdit['email'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <div class="alerta-summary-card">
                <span class="alerta-summary-label">Acesso atual</span>
                <span class="alerta-summary-value"><?= htmlspecialchars((string) ($usuarioEdit['perfil'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="alerta-summary-note">Status atual da conta: <?= htmlspecialchars($statusAtual !== '' ? $statusAtual : '-', ENT_QUOTES, 'UTF-8') ?>.</span>
            </div>

            <div class="alerta-summary-card">
                <span class="alerta-summary-label">Cadastro original</span>
                <span class="alerta-summary-value"><?= htmlspecialchars(TimeHelper::formatUtcDateTime($usuarioEdit['criado_em'] ?? null, 'Sem dados'), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="alerta-summary-note">Edicao realizada por <?= htmlspecialchars((string) ($usuario['nome'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>.</span>
            </div>
        </div>
    </div>

    <form class="alerta-form-panel" method="post" action="atualizar.php">
        <?= Csrf::inputField() ?>
        <input type="hidden" name="id" value="<?= (int) $usuarioEdit['id'] ?>">

        <div class="alerta-form-grid usuarios-form-grid">
            <section class="alerta-form-section">
                <header class="alerta-section-header">
                    <span class="alerta-section-kicker">Secao 1</span>
                    <h2 class="alerta-section-title">Dados da conta</h2>
                    <p class="alerta-section-text">
                        Revise as informacoes principais do usuario e mantenha os dados de identificacao atualizados para a operacao.
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
                        <span class="field-helper">Atualize o nome sempre que houver necessidade de correção cadastral ou mudanca de identificacao operacional.</span>
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
                        <span class="field-helper">O e-mail continua sendo o identificador principal da conta para autenticacao e administracao.</span>
                    </div>
                </div>
            </section>

            <section class="alerta-form-section">
                <header class="alerta-section-header">
                    <span class="alerta-section-kicker">Secao 2</span>
                    <h2 class="alerta-section-title">Perfil e manutencao de acesso</h2>
                    <p class="alerta-section-text">
                        Ajuste o perfil operacional, revise o status da conta e use os atalhos de manutencao para manter o acesso sob controle.
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
                        <span class="field-helper">Altere o perfil somente quando houver mudanca real de responsabilidade ou escopo de acesso.</span>
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
                        <strong>Situacao atual da conta</strong>
                        <div class="usuarios-role-list">
                            <span class="usuarios-role-pill"><?= htmlspecialchars((string) ($usuarioEdit['perfil'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="usuarios-status-pill <?= $classeStatusAtual ?>"><?= htmlspecialchars($statusAtual !== '' ? $statusAtual : '-', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </article>

                    <article class="usuarios-note-card">
                        <strong>Manutencao de senha</strong>
                        <span>Se houver necessidade de redefinicao, utilize o atalho de alterar senha sem interferir nos demais dados cadastrais da conta.</span>
                    </article>

                    <article class="usuarios-note-card">
                        <strong>Rastreabilidade administrativa</strong>
                        <span>Toda alteracao de perfil, status e dados principais permanece registrada no historico administrativo do sistema.</span>
                    </article>
                </div>
            </section>
        </div>

        <div class="alerta-form-actions">
            <div class="alerta-form-actions-left">
                <span class="alerta-inline-note">Revise as alteracoes antes de salvar para manter coerencia no controle de acessos.</span>
            </div>

            <div class="alerta-form-actions-right">
                <a href="/pages/usuarios/listar.php" class="btn btn-secondary">Voltar</a>
                <a href="/pages/usuarios/senha.php?id=<?= (int) $usuarioEdit['id'] ?>" class="btn btn-secondary">Alterar senha</a>
                <button type="submit" class="btn btn-primary">Salvar alteracoes</button>
            </div>
        </div>
    </form>
</section>

<?php include __DIR__ . '/../_footer.php'; ?>

</main>
</div>
</body>
</html>
