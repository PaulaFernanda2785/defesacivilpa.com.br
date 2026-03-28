<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Database.php';

Protect::check(['ADMIN', 'GESTOR', 'ANALISTA', 'OPERACOES']);

$db = Database::getConnection();
$usuarioLogado = $_SESSION['usuario'] ?? [];
$rotaVolta = ($usuarioLogado['perfil'] ?? '') === 'ADMIN'
    ? '/pages/usuarios/listar.php'
    : '/pages/painel.php';

$id = (int) ($_GET['id'] ?? ($usuarioLogado['id'] ?? 0));
if ($id <= 0) {
    header('Location: ' . $rotaVolta);
    exit;
}

$stmt = $db->prepare('SELECT id, nome, email, perfil, status, criado_em FROM usuarios WHERE id = ?');
$stmt->execute([$id]);
$usuarioEdit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuarioEdit) {
    header('Location: ' . $rotaVolta);
    exit;
}

if (($usuarioLogado['perfil'] ?? '') !== 'ADMIN' && (int) ($usuarioLogado['id'] ?? 0) !== (int) $usuarioEdit['id']) {
    die('Acesso negado.');
}

$statusAtual = (string) ($usuarioEdit['status'] ?? '');
$classeStatusAtual = $statusAtual === 'ATIVO' ? 'usuarios-status-pill-ativo' : 'usuarios-status-pill-inativo';
$erroCodigo = trim((string) ($_GET['erro'] ?? ''));
$isEdicaoPropriaSenha = (int) ($usuarioLogado['id'] ?? 0) === (int) ($usuarioEdit['id'] ?? 0);
$tituloPaginaSenha = $isEdicaoPropriaSenha ? 'Alterar minha senha' : 'Redefinir senha do usuário';
$kickerPaginaSenha = $isEdicaoPropriaSenha ? 'Segurança da conta' : 'Segurança administrativa';
$descricaoPaginaSenha = $isEdicaoPropriaSenha
    ? 'Atualize sua credencial de acesso com segurança mantendo o mesmo padrão visual adotado nas telas de usuários.'
    : 'Atualize a senha do usuário selecionado mantendo o mesmo padrão visual das telas de cadastro e edição. Esta operação deve ser usada com cuidado para preservar o controle de acessos do sistema.';
$labelContaResumo = $isEdicaoPropriaSenha ? 'Minha conta' : 'Usuário alvo';
$descricaoOperacaoResumo = $isEdicaoPropriaSenha
    ? 'A troca da senha será registrada no histórico da sua conta para rastreabilidade operacional.'
    : 'A redefinição será registrada no histórico administrativo da plataforma.';
$tituloSecaoConta = $isEdicaoPropriaSenha ? 'Resumo da conta' : 'Contexto da conta';
$textoSecaoConta = $isEdicaoPropriaSenha
    ? 'Confira os dados da sua conta antes de atualizar a credencial de acesso. Os campos abaixo são exibidos apenas para referência.'
    : 'Confirme o usuário correto antes de redefinir a senha. Os dados abaixo são apenas para conferência e não serão alterados nesta tela.';
$textoPermissaoSenha = ($usuarioLogado['perfil'] ?? '') === 'ADMIN'
    ? 'Administradores podem redefinir senhas de outras contas. Perfis não administrativos só podem redefinir a própria senha.'
    : 'Você está autorizado a redefinir apenas a própria senha dentro deste módulo.';
$cssUsuariosListarPath = __DIR__ . '/../../assets/css/pages/usuarios-listar.css';
$cssUsuariosFormPath = __DIR__ . '/../../assets/css/pages/usuarios-form.css';
$cssUsuariosSenhaPath = __DIR__ . '/../../assets/css/pages/usuarios-senha.css';
$cssUsuariosListarVersion = (string) ((int) @filemtime($cssUsuariosListarPath));
$cssUsuariosFormVersion = (string) ((int) @filemtime($cssUsuariosFormPath));
$cssUsuariosSenhaVersion = (string) ((int) @filemtime($cssUsuariosSenhaPath));

$mensagensErro = [
    'confirmacao' => 'A confirmação da nova senha não confere com a senha digitada. Revise os dois campos e tente novamente.',
    'senha_curta' => 'A nova senha precisa ter pelo menos 8 caracteres para atender à regra de segurança do sistema.',
    'dados_invalidos' => 'Não foi possível processar a redefinição de senha com os dados enviados. Revise o formulário e tente novamente.',
];

$mensagemErro = $mensagensErro[$erroCodigo] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Alterar Senha</title>
<link rel="icon" type="image/x-icon" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/logo.cbmpa.ico">

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/painel.css">
<link rel="stylesheet" href="/assets/css/pages/alertas-form.css">
<link rel="stylesheet" href="/assets/css/pages/usuarios-listar.css?v=<?= htmlspecialchars($cssUsuariosListarVersion, ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="/assets/css/pages/usuarios-form.css?v=<?= htmlspecialchars($cssUsuariosFormVersion, ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="/assets/css/pages/usuarios-senha.css?v=<?= htmlspecialchars($cssUsuariosSenhaVersion, ENT_QUOTES, 'UTF-8') ?>">
</head>

<body>
<div class="layout">

<?php include __DIR__ . '/../_sidebar.php'; ?>

<main class="content">

<?php include __DIR__ . '/../_topbar.php'; ?>

<?php
$breadcrumb = [
    'Painel' => '/pages/painel.php',
];

if (($usuarioLogado['perfil'] ?? '') === 'ADMIN') {
    $breadcrumb['Usuários'] = '/pages/usuarios/listar.php';
}

$breadcrumb['Alterar senha'] = null;
include __DIR__ . '/../_breadcrumb.php';
?>

<section class="dashboard alerta-form-shell usuarios-shell usuarios-form-shell usuarios-senha-shell">
    <div class="usuarios-hero-grid usuarios-senha-hero-grid">
        <div class="alerta-form-hero usuarios-hero-panel usuarios-senha-hero-panel">
            <div class="alerta-form-lead usuarios-hero-copy usuarios-senha-hero-copy">
                <span class="alerta-form-kicker"><?= htmlspecialchars($kickerPaginaSenha, ENT_QUOTES, 'UTF-8') ?></span>
                <h1 class="alerta-form-title"><?= htmlspecialchars($tituloPaginaSenha, ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="alerta-form-description">
                    <?= htmlspecialchars($descricaoPaginaSenha, ENT_QUOTES, 'UTF-8') ?>
                </p>

                <div class="usuarios-hero-chip-row usuarios-senha-hero-chip-row">
                    <span class="usuarios-hero-chip"><?= htmlspecialchars($labelContaResumo, ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars((string) ($usuarioEdit['nome'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="usuarios-hero-chip">Perfil: <?= htmlspecialchars((string) ($usuarioEdit['perfil'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="usuarios-hero-chip">Status: <?= htmlspecialchars($statusAtual !== '' ? $statusAtual : '-', ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <div class="usuarios-hero-actions usuarios-senha-hero-actions">
                    <a href="#senha-conta" class="btn btn-primary">Resumo da conta</a>
                    <a href="#senha-redefinicao" class="btn btn-secondary">Nova senha</a>
                </div>
            </div>
        </div>

        <div class="usuarios-summary-grid usuarios-senha-summary-grid">
            <article class="usuarios-summary-card usuarios-summary-card-primary">
                <span class="usuarios-summary-label"><?= htmlspecialchars($labelContaResumo, ENT_QUOTES, 'UTF-8') ?></span>
                <strong class="usuarios-summary-value"><?= htmlspecialchars((string) ($usuarioEdit['nome'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="usuarios-summary-note"><?= htmlspecialchars((string) ($usuarioEdit['email'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
            </article>

            <article class="usuarios-summary-card usuarios-summary-card-success">
                <span class="usuarios-summary-label">Perfil e status</span>
                <strong class="usuarios-summary-value"><?= htmlspecialchars((string) ($usuarioEdit['perfil'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="usuarios-summary-note">Conta atualmente marcada como <?= htmlspecialchars($statusAtual !== '' ? $statusAtual : '-', ENT_QUOTES, 'UTF-8') ?>.</span>
            </article>

            <article class="usuarios-summary-card usuarios-summary-card-neutral">
                <span class="usuarios-summary-label">Operação</span>
                <strong class="usuarios-summary-value"><?= htmlspecialchars((string) ($usuarioLogado['nome'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="usuarios-summary-note"><?= htmlspecialchars($descricaoOperacaoResumo, ENT_QUOTES, 'UTF-8') ?></span>
            </article>

            <article class="usuarios-summary-card usuarios-summary-card-warning">
                <span class="usuarios-summary-label">Permissão aplicada</span>
                <strong class="usuarios-summary-value"><?= htmlspecialchars((string) ($usuarioLogado['perfil'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="usuarios-summary-note"><?= htmlspecialchars($textoPermissaoSenha, ENT_QUOTES, 'UTF-8') ?></span>
            </article>
        </div>

        <aside class="usuarios-command-card usuarios-senha-command-card">
            <span class="usuarios-command-kicker">Comando de segurança</span>
            <h2>Fluxo de redefinição</h2>
            <p>
                Confira a conta alvo, defina a nova senha e confirme os campos antes de salvar.
                A operação é registrada no histórico para rastreabilidade.
            </p>

            <div class="usuarios-command-grid usuarios-senha-command-grid">
                <article class="usuarios-command-item">
                    <span>Etapa 1</span>
                    <strong>Validar conta</strong>
                    <small>Confirme nome, e-mail, perfil e status do usuário antes da redefinição.</small>
                </article>

                <article class="usuarios-command-item">
                    <span>Etapa 2</span>
                    <strong>Aplicar nova senha</strong>
                    <small>Preencha os dois campos com a mesma senha e no mínimo 8 caracteres.</small>
                </article>

                <article class="usuarios-command-item">
                    <span>Etapa 3</span>
                    <strong>Salvar com segurança</strong>
                    <small>Finalize apenas após revisar a conta correta para evitar alteração indevida.</small>
                </article>
            </div>
        </aside>
    </div>

    <form class="alerta-form-panel usuarios-control-panel usuarios-senha-form-panel" method="post" action="salvar_senha.php">
        <?= Csrf::inputField() ?>
        <input type="hidden" name="id" value="<?= (int) $usuarioEdit['id'] ?>">

        <div class="alerta-form-grid usuarios-form-grid usuarios-senha-form-grid">
            <section id="senha-conta" class="alerta-form-section">
                <header class="alerta-section-header">
                    <span class="alerta-section-kicker">Seção 1</span>
                    <h2 class="alerta-section-title"><?= htmlspecialchars($tituloSecaoConta, ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="alerta-section-text">
                        <?= htmlspecialchars($textoSecaoConta, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </header>

                <div class="alerta-fields-grid">
                    <div class="form-group field-span-2">
                        <label for="usuario_nome">Usuário</label>
                        <input
                            type="text"
                            id="usuario_nome"
                            value="<?= htmlspecialchars((string) ($usuarioEdit['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            disabled
                        >
                        <span class="field-helper">Identificação principal da conta selecionada para a redefinição de senha.</span>
                    </div>

                    <div class="form-group field-span-2">
                        <label for="usuario_email">E-mail</label>
                        <input
                            type="text"
                            id="usuario_email"
                            value="<?= htmlspecialchars((string) ($usuarioEdit['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            disabled
                        >
                        <span class="field-helper">E-mail de autenticação atualmente vinculado a esta conta.</span>
                    </div>
                </div>
            </section>

            <section id="senha-redefinicao" class="alerta-form-section">
                <header class="alerta-section-header">
                    <span class="alerta-section-kicker">Seção 2</span>
                    <h2 class="alerta-section-title">Nova senha e confirmação</h2>
                    <p class="alerta-section-text">
                        Defina uma nova senha segura e repita a informação no campo de confirmação para evitar erros de digitação.
                    </p>
                </header>

                <div class="alerta-fields-grid usuarios-access-grid">
                    <div class="form-group">
                        <label for="senha">Nova senha</label>
                        <input
                            type="password"
                            id="senha"
                            name="senha"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >
                        <span class="field-helper">A senha deve ter no mínimo 8 caracteres e ser compartilhada apenas com o usuário autorizado.</span>
                    </div>

                    <div class="form-group">
                        <label for="confirmacao">Confirmar nova senha</label>
                        <input
                            type="password"
                            id="confirmacao"
                            name="confirmacao"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >
                        <span class="field-helper">Repita exatamente a mesma senha para validar a redefinição antes do envio.</span>
                    </div>
                </div>

                <div class="usuarios-note-stack">
                    <article class="usuarios-note-card">
                        <strong>Situação da conta</strong>
                        <div class="usuarios-role-list">
                            <span class="usuarios-role-pill"><?= htmlspecialchars((string) ($usuarioEdit['perfil'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="usuarios-status-pill <?= $classeStatusAtual ?>"><?= htmlspecialchars($statusAtual !== '' ? $statusAtual : '-', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </article>

                    <article class="usuarios-note-card">
                        <strong>Boa prática de segurança</strong>
                        <span>Depois da redefinição, oriente o usuário a trocar a senha no próximo ciclo administrativo sempre que houver suspeita de compartilhamento indevido.</span>
                    </article>

                    <article class="usuarios-note-card">
                        <strong>Permissão aplicada</strong>
                        <span><?= htmlspecialchars($textoPermissaoSenha, ENT_QUOTES, 'UTF-8') ?></span>
                    </article>
                </div>
            </section>
        </div>

        <div class="alerta-form-actions">
            <div class="alerta-form-actions-left">
                <span class="alerta-inline-note">Confirme o usuário alvo antes de salvar para evitar redefinição indevida de credenciais.</span>
            </div>

            <div class="alerta-form-actions-right">
                <a href="<?= htmlspecialchars($rotaVolta, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary">Voltar</a>
                <button type="submit" class="btn btn-primary">Atualizar senha</button>
            </div>
        </div>
    </form>
</section>

<?php include __DIR__ . '/../_footer.php'; ?>

<div
    id="modalSenhaErro"
    class="modal-ajuda usuarios-feedback-modal"
    data-auto-open="<?= $mensagemErro !== '' ? '1' : '0' ?>"
    data-error-message="<?= htmlspecialchars($mensagemErro, ENT_QUOTES, 'UTF-8') ?>"
>
    <div class="modal-ajuda-conteudo usuarios-feedback-modal-content">
        <div class="modal-ajuda-header usuarios-feedback-modal-header">
            <h3>Erro na confirmação da senha</h3>
            <button type="button" aria-label="Fechar" onclick="fecharModalSenhaErro()">X</button>
        </div>

        <div class="modal-ajuda-body" id="modalSenhaErroBody">
            Revise os campos de senha e confirmação antes de tentar novamente.
        </div>

        <div class="modal-ajuda-footer">
            <button type="button" onclick="fecharModalSenhaErro()">Entendi</button>
        </div>
    </div>
</div>

<script src="/assets/js/pages/usuarios-senha.js"></script>

</main>
</div>
</body>
</html>
