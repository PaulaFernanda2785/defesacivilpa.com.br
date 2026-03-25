<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Helpers/PaginationHelper.php';

Protect::check(['ADMIN']);

$usuario = $_SESSION['usuario'];
$db = Database::getConnection();

$filtroNome = trim((string) ($_GET['nome'] ?? ''));
$filtroPerfil = trim((string) ($_GET['perfil'] ?? ''));
$filtroStatus = trim((string) ($_GET['status'] ?? ''));

$limite = 10;
$pagina = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;

$where = [];
$params = [];

if ($filtroNome !== '') {
    $where[] = 'nome LIKE :nome';
    $params[':nome'] = '%' . $filtroNome . '%';
}

if ($filtroPerfil !== '') {
    $where[] = 'perfil = :perfil';
    $params[':perfil'] = $filtroPerfil;
}

if ($filtroStatus !== '') {
    $where[] = 'status = :status';
    $params[':status'] = $filtroStatus;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$perfisDisponiveis = $db->query("
    SELECT DISTINCT perfil
    FROM usuarios
    WHERE perfil IS NOT NULL
      AND perfil <> ''
    ORDER BY perfil
")->fetchAll(PDO::FETCH_COLUMN);

$stmtTotal = $db->prepare("
    SELECT COUNT(*)
    FROM usuarios
    $whereSql
");
$stmtTotal->execute($params);
$totalUsuarios = (int) $stmtTotal->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalUsuarios / $limite));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * $limite;

$stmtResumo = $db->prepare("
    SELECT
        COUNT(*) AS total_usuarios,
        SUM(CASE WHEN status = 'ATIVO' THEN 1 ELSE 0 END) AS total_ativos,
        SUM(CASE WHEN status = 'INATIVO' THEN 1 ELSE 0 END) AS total_inativos,
        COUNT(DISTINCT perfil) AS total_perfis,
        MAX(criado_em) AS ultimo_cadastro
    FROM usuarios
    $whereSql
");
$stmtResumo->execute($params);
$resumo = $stmtResumo->fetch(PDO::FETCH_ASSOC) ?: [];

$stmtPerfisResumo = $db->prepare("
    SELECT perfil, COUNT(*) AS total
    FROM usuarios
    $whereSql
    GROUP BY perfil
    ORDER BY perfil
");
$stmtPerfisResumo->execute($params);
$totaisPorPerfil = $stmtPerfisResumo->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("
    SELECT id, nome, email, perfil, status, criado_em
    FROM usuarios
    $whereSql
    ORDER BY nome
    LIMIT :limite OFFSET :offset
");

foreach ($params as $chave => $valor) {
    $stmt->bindValue($chave, $valor);
}

$stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$queryBase = array_filter([
    'nome' => $filtroNome !== '' ? $filtroNome : null,
    'perfil' => $filtroPerfil !== '' ? $filtroPerfil : null,
    'status' => $filtroStatus !== '' ? $filtroStatus : null,
], static fn ($value) => $value !== null && $value !== '');

$filtrosAplicados = [];

if ($filtroNome !== '') {
    $filtrosAplicados[] = 'Nome: ' . $filtroNome;
}

if ($filtroPerfil !== '') {
    $filtrosAplicados[] = 'Perfil: ' . $filtroPerfil;
}

if ($filtroStatus !== '') {
    $filtrosAplicados[] = 'Status: ' . $filtroStatus;
}

$filtrosResumo = $filtrosAplicados !== []
    ? implode(' | ', $filtrosAplicados)
    : 'Sem filtros adicionais';

$totalAtivos = (int) ($resumo['total_ativos'] ?? 0);
$totalInativos = (int) ($resumo['total_inativos'] ?? 0);
$totalPerfis = (int) ($resumo['total_perfis'] ?? 0);
$ultimoCadastro = TimeHelper::formatUtcDateTime($resumo['ultimo_cadastro'] ?? null, 'Sem dados');
$totalEmTela = count($usuarios);
$inicioRegistro = $totalUsuarios > 0 ? $offset + 1 : 0;
$fimRegistro = $totalUsuarios > 0 ? $offset + $totalEmTela : 0;
$percentualAtivos = $totalUsuarios > 0 ? (int) round(($totalAtivos / $totalUsuarios) * 100) : 0;
$percentualInativos = $totalUsuarios > 0 ? max(0, 100 - $percentualAtivos) : 0;

$perfilLider = 'Sem dados';
$perfilLiderTotal = 0;
$maiorPerfilTotal = 0;

foreach ($totaisPorPerfil as $perfilResumo) {
    $totalPerfilAtual = (int) ($perfilResumo['total'] ?? 0);

    if ($totalPerfilAtual > $perfilLiderTotal) {
        $perfilLider = (string) ($perfilResumo['perfil'] ?? 'Sem dados');
        $perfilLiderTotal = $totalPerfilAtual;
    }

    if ($totalPerfilAtual > $maiorPerfilTotal) {
        $maiorPerfilTotal = $totalPerfilAtual;
    }
}

$resumoExecutivo = [
    [
        'label' => 'Base monitorada',
        'value' => $totalUsuarios . ' usuarios',
        'note' => $totalUsuarios > 0
            ? "Exibindo {$inicioRegistro} a {$fimRegistro} no recorte atual."
            : 'Nenhum usuario localizado com os filtros atuais.',
        'tone' => 'primary',
    ],
    [
        'label' => 'Saude de acesso',
        'value' => $percentualAtivos . '% ativos',
        'note' => $totalAtivos . ' contas ativas e ' . $totalInativos . ' inativas no momento.',
        'tone' => 'success',
    ],
    [
        'label' => 'Perfis monitorados',
        'value' => $totalPerfis . ' perfis',
        'note' => $perfilLiderTotal > 0
            ? $perfilLider . ' lidera com ' . $perfilLiderTotal . ' usuarios.'
            : 'Sem distribuicao por perfil no recorte atual.',
        'tone' => 'neutral',
    ],
    [
        'label' => 'Ultimo cadastro',
        'value' => $ultimoCadastro,
        'note' => 'Leitura temporal da base administrativa atualmente filtrada.',
        'tone' => 'warning',
    ],
];

function paginacaoProfissionalUsuarios(int $paginaAtual, int $totalPaginas): array
{
    return PaginationHelper::marcadoresCompactos($paginaAtual, $totalPaginas);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Usuarios</title>
<link rel="icon" type="image/x-icon" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/logo.cbmpa.ico">

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/painel.css">
<link rel="stylesheet" href="/assets/css/pages/alertas-form.css">
<link rel="stylesheet" href="/assets/css/pages/usuarios-listar.css">
</head>

<body>
<div class="layout">

<?php include __DIR__ . '/../_sidebar.php'; ?>

<main class="content">

<?php include __DIR__ . '/../_topbar.php'; ?>

<?php
$breadcrumb = [
    'Painel' => '/pages/painel.php',
    'Usuarios' => null,
];
include __DIR__ . '/../_breadcrumb.php';
?>

<section class="dashboard alerta-form-shell usuarios-shell">
    <div class="usuarios-hero-grid">
        <div class="alerta-form-hero usuarios-hero-panel">
            <div class="alerta-form-lead usuarios-hero-copy">
                <span class="alerta-form-kicker">Governanca de acesso</span>
                <h1 class="alerta-form-title">Administracao institucional de usuarios</h1>
                <p class="alerta-form-description">
                    Controle contas, perfis e situacoes de acesso em um painel administrativo.
                </p>

                <div class="usuarios-hero-chip-row">
                    <span class="usuarios-hero-chip">Pagina <?= $pagina ?> de <?= $totalPaginas ?></span>
                    <span class="usuarios-hero-chip"><?= $percentualAtivos ?>% da base ativa</span>
                    <span class="usuarios-hero-chip"><?= htmlspecialchars($filtrosResumo, ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <div class="usuarios-hero-actions">
                    <a href="/pages/usuarios/cadastrar.php" class="btn btn-primary">Cadastrar usuario</a>
                    <a href="/pages/historico/index.php" class="btn btn-secondary">Abrir historico</a>
                </div>
            </div>
        </div>

        <div class="usuarios-summary-grid">
            <?php foreach ($resumoExecutivo as $cardResumo): ?>
                <article class="usuarios-summary-card usuarios-summary-card-<?= htmlspecialchars((string) ($cardResumo['tone'] ?? 'primary'), ENT_QUOTES, 'UTF-8') ?>">
                    <span class="usuarios-summary-label"><?= htmlspecialchars((string) ($cardResumo['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    <strong class="usuarios-summary-value"><?= htmlspecialchars((string) ($cardResumo['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                    <span class="usuarios-summary-note"><?= htmlspecialchars((string) ($cardResumo['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                </article>
            <?php endforeach; ?>
        </div>

        <aside class="usuarios-command-card">
            <span class="usuarios-command-kicker">Painel do administrador</span>
            <h2>Comando rapido da base de acessos</h2>
            <p>
                Esta area concentra o recorte atual da base, a pessoa responsavel pela sessao e a prioridade
                operacional mais recomendada para a gestao das contas.
            </p>

            <div class="usuarios-command-grid">
                <article class="usuarios-command-item">
                    <span>Administrador logado</span>
                    <strong><?= htmlspecialchars((string) ($usuario['nome'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
                    <small>Permissao integral para cadastro, edicao, senha e status.</small>
                </article>

                <article class="usuarios-command-item">
                    <span>Perfil com maior volume</span>
                    <strong><?= htmlspecialchars($perfilLider, ENT_QUOTES, 'UTF-8') ?></strong>
                    <small><?= $perfilLiderTotal ?> contas dentro do recorte atual.</small>
                </article>

                <article class="usuarios-command-item">
                    <span>Prioridade sugerida</span>
                    <strong><?= $totalInativos > 0 ? 'Revisar acessos inativos' : 'Manter base atualizada' ?></strong>
                    <small>
                        <?= $totalInativos > 0
                            ? $totalInativos . ' conta(s) inativa(s) merecem revisao administrativa.'
                            : 'A base atual esta sem registros inativos no recorte aplicado.' ?>
                    </small>
                </article>
            </div>
        </aside>
    </div>

    <div class="alerta-form-panel usuarios-control-panel">
        <div class="usuarios-control-grid">
            <section class="alerta-form-section usuarios-filter-panel">
                <header class="alerta-section-header">
                    <span class="alerta-section-kicker">Consulta administrativa</span>
                    <h2 class="alerta-section-title">Filtrar, localizar e reduzir o recorte</h2>
                    <p class="alerta-section-text">
                        Busque rapidamente por nome, perfil e status para chegar ao grupo certo de usuarios antes de editar,
                        redefinir senha ou alterar o estado da conta.
                    </p>
                </header>

                <form method="get" class="usuarios-filters">
                    <div class="usuarios-filter-grid">
                        <div class="form-group usuarios-field-span-2">
                            <label for="nome">Nome do usuario</label>
                            <input
                                id="nome"
                                type="search"
                                name="nome"
                                placeholder="Digite o nome ou parte do nome"
                                value="<?= htmlspecialchars($filtroNome, ENT_QUOTES, 'UTF-8') ?>"
                            >
                        </div>

                        <div class="form-group">
                            <label for="perfil">Perfil</label>
                            <select id="perfil" name="perfil">
                                <option value="">Todos os perfis</option>
                                <?php foreach ($perfisDisponiveis as $perfil): ?>
                                    <option value="<?= htmlspecialchars((string) $perfil, ENT_QUOTES, 'UTF-8') ?>" <?= $filtroPerfil === $perfil ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string) $perfil, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="">Todos os status</option>
                                <option value="ATIVO" <?= $filtroStatus === 'ATIVO' ? 'selected' : '' ?>>ATIVO</option>
                                <option value="INATIVO" <?= $filtroStatus === 'INATIVO' ? 'selected' : '' ?>>INATIVO</option>
                            </select>
                        </div>
                    </div>

                    <div class="usuarios-filter-meta">
                        <span class="usuarios-filter-meta-label">Recorte ativo</span>
                        <div class="usuarios-filter-pill-row">
                            <?php if ($filtrosAplicados === []): ?>
                                <span class="usuarios-filter-pill is-neutral">Sem filtros adicionais</span>
                            <?php else: ?>
                                <?php foreach ($filtrosAplicados as $filtroAplicado): ?>
                                    <span class="usuarios-filter-pill"><?= htmlspecialchars($filtroAplicado, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="alerta-form-actions usuarios-filter-actions">
                        <div class="alerta-form-actions-left">
                            <span class="alerta-inline-note">A consulta preserva paginacao e leituras executivas do painel.</span>
                        </div>

                        <div class="alerta-form-actions-right usuarios-filter-actions-group">
                            <a href="/pages/usuarios/listar.php" class="btn btn-secondary">Limpar filtros</a>
                            <button type="submit" class="btn btn-primary">Atualizar painel</button>
                        </div>
                    </div>
                </form>
            </section>

            <section class="alerta-form-section usuarios-governance-panel">
                <header class="alerta-section-header">
                    <span class="alerta-section-kicker">Leitura executiva</span>
                    <h2 class="alerta-section-title">Saude, distribuicao e governanca</h2>
                    <p class="alerta-section-text">
                        Acompanhe o equilibrio da base de acessos, a distribuicao por perfil e os pontos que merecem atencao
                        administrativa antes de partir para as acoes da tabela.
                    </p>
                </header>

                <div class="usuarios-insight-grid">
                    <article class="usuarios-insight-card usuarios-insight-card-emphasis">
                        <span class="usuarios-insight-kicker">Saude da base</span>
                        <strong><?= $percentualAtivos ?>% ativos</strong>
                        <p><?= $totalAtivos ?> contas ativas e <?= $totalInativos ?> inativas no recorte consultado.</p>

                        <div class="usuarios-status-meter" aria-label="Distribuicao entre contas ativas e inativas">
                            <span class="usuarios-status-meter-active" style="width: <?= $percentualAtivos ?>%"></span>
                            <span class="usuarios-status-meter-inactive" style="width: <?= $percentualInativos ?>%"></span>
                        </div>

                        <div class="usuarios-status-legend">
                            <span>Ativos: <?= $totalAtivos ?></span>
                            <span>Inativos: <?= $totalInativos ?></span>
                        </div>
                    </article>

                    <article class="usuarios-insight-card usuarios-profile-card">
                        <span class="usuarios-insight-kicker">Distribuicao por perfil</span>
                        <strong><?= $totalPerfis ?> perfis ativos na consulta</strong>
                        <p>Veja rapidamente onde a base esta mais concentrada para orientar revisoes administrativas.</p>

                        <div class="usuarios-profile-list">
                            <?php if ($totaisPorPerfil === []): ?>
                                <div class="usuarios-profile-empty">Nenhum perfil encontrado no recorte atual.</div>
                            <?php else: ?>
                                <?php foreach ($totaisPorPerfil as $perfilResumo): ?>
                                    <?php
                                    $perfilTotalAtual = (int) ($perfilResumo['total'] ?? 0);
                                    $larguraPerfil = $maiorPerfilTotal > 0 ? max(10, (int) round(($perfilTotalAtual / $maiorPerfilTotal) * 100)) : 0;
                                    ?>
                                    <div class="usuarios-profile-item">
                                        <div class="usuarios-profile-copy">
                                            <span class="usuarios-profile-name"><?= htmlspecialchars((string) ($perfilResumo['perfil'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="usuarios-profile-bar"><span style="width: <?= $larguraPerfil ?>%"></span></span>
                                        </div>
                                        <span class="usuarios-profile-total"><?= $perfilTotalAtual ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </article>

                    <article class="usuarios-insight-card">
                        <span class="usuarios-insight-kicker">Ultimo movimento de base</span>
                        <strong><?= htmlspecialchars($ultimoCadastro, ENT_QUOTES, 'UTF-8') ?></strong>
                        <p>Ultimo cadastro registrado no recorte atual. Use essa referencia para acompanhar renovacao da base.</p>
                    </article>

                    <article class="usuarios-insight-card">
                        <span class="usuarios-insight-kicker">Rastreabilidade</span>
                        <strong>Gestao protegida por historico</strong>
                        <p>Toda alteracao de cadastro, senha e status deve ser acompanhada no historico para reforcar governanca e auditoria institucional.</p>
                    </article>
                </div>
            </section>
        </div>
    </div>

    <section class="alerta-form-panel usuarios-table-panel">
        <header class="usuarios-table-head">
            <div class="alerta-section-header">
                <span class="alerta-section-kicker">Base administrativa</span>
                <h2 class="alerta-section-title">Contas, perfis e acoes da operacao</h2>
                <p class="alerta-section-text">
                    A listagem abaixo concentra identificacao, contato, perfil, status e atalhos administrativos.
                </p>
            </div>

            <div class="usuarios-table-head-actions">
                <span class="usuarios-result-chip">
                    <?= $totalUsuarios > 0 ? "Exibindo {$inicioRegistro} a {$fimRegistro} de {$totalUsuarios}" : 'Nenhum resultado no filtro atual' ?>
                </span>
                <a href="/pages/usuarios/cadastrar.php" class="btn btn-primary">Novo usuario</a>
            </div>
        </header>

        <div class="usuarios-table-toolbar">
            <div class="usuarios-table-toolbar-copy">
                <strong>Recorte administrativo:</strong>
                <span><?= htmlspecialchars($filtrosResumo, ENT_QUOTES, 'UTF-8') ?></span>
            </div>

            <div class="usuarios-table-toolbar-pills">
                <span class="usuarios-toolbar-pill"><?= $totalAtivos ?> ativos</span>
                <span class="usuarios-toolbar-pill"><?= $totalInativos ?> inativos</span>
                <span class="usuarios-toolbar-pill"><?= htmlspecialchars($perfilLider, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>

        <div class="usuarios-table-wrap">
            <table class="tabela-usuarios">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Contato</th>
                        <th>Perfil</th>
                        <th>Status</th>
                        <th>Criado em</th>
                        <th>Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($usuarios === []): ?>
                        <tr>
                            <td colspan="6" class="usuarios-empty-row">Nenhum usuario encontrado para os filtros informados.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($usuarios as $item): ?>
                        <?php $classeStatus = $item['status'] === 'ATIVO' ? 'status-ativo' : 'status-inativo'; ?>
                        <tr>
                            <td data-label="Usuario">
                                <div class="usuarios-user-cell">
                                    <strong><?= htmlspecialchars((string) $item['nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                                </div>
                            </td>
                            <td data-label="Contato">
                                <a class="usuarios-email-link" href="mailto:<?= htmlspecialchars((string) $item['email'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars((string) $item['email'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </td>
                            <td data-label="Perfil">
                                <span class="usuarios-perfil-badge"><?= htmlspecialchars((string) $item['perfil'], ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td data-label="Status">
                                <span class="usuario-status <?= $classeStatus ?>"><?= htmlspecialchars((string) $item['status'], ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td data-label="Criado em">
                                <div class="usuarios-date-cell">
                                    <strong><?= htmlspecialchars(TimeHelper::formatUtcDate($item['criado_em'] ?? null), ENT_QUOTES, 'UTF-8') ?></strong>
                                </div>
                            </td>
                            <td data-label="Acoes">
                                <div class="usuarios-actions">
                                    <a href="/pages/usuarios/editar.php?id=<?= (int) $item['id'] ?>" class="btn-acao btn-editar">Editar</a>
                                    <a href="/pages/usuarios/senha.php?id=<?= (int) $item['id'] ?>" class="btn-acao btn-detalhe">Senha</a>

                                    <?php if ($item['status'] === 'ATIVO'): ?>
                                        <form method="post" action="/pages/usuarios/alterar_status.php" class="acao-inline">
                                            <?= Csrf::inputField() ?>
                                            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                            <input type="hidden" name="acao" value="inativar">
                                            <button
                                                type="submit"
                                                class="btn-acao btn-inativar"
                                                onclick="return confirm('Deseja inativar este usuario?')"
                                            >
                                                Inativar
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="/pages/usuarios/alterar_status.php" class="acao-inline">
                                            <?= Csrf::inputField() ?>
                                            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                            <input type="hidden" name="acao" value="ativar">
                                            <button
                                                type="submit"
                                                class="btn-acao btn-ativar"
                                                onclick="return confirm('Deseja ativar este usuario?')"
                                            >
                                                Ativar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPaginas > 1): ?>
            <div class="usuarios-pagination-wrap">
                <div class="usuarios-pagination-copy">
                    <strong>Paginacao administrativa</strong>
                    <span>Navegue entre as paginas sem perder o recorte aplicado.</span>
                </div>

                <div class="paginacao">
                    <?php if ($pagina > 1): ?>
                        <a href="?<?= http_build_query(array_merge($queryBase, ['pagina' => $pagina - 1])) ?>" aria-label="Pagina anterior" title="Pagina anterior">&laquo;</a>
                    <?php endif; ?>

                    <?php foreach (paginacaoProfissionalUsuarios($pagina, $totalPaginas) as $itemPaginacao): ?>
                        <?php if ($itemPaginacao === '...'): ?>
                            <span class="paginacao-ellipsis">...</span>
                        <?php else: ?>
                            <a
                                href="?<?= http_build_query(array_merge($queryBase, ['pagina' => $itemPaginacao])) ?>"
                                class="<?= $itemPaginacao === $pagina ? 'ativa' : '' ?>"
                            >
                                <?= $itemPaginacao ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if ($pagina < $totalPaginas): ?>
                        <a href="?<?= http_build_query(array_merge($queryBase, ['pagina' => $pagina + 1])) ?>" aria-label="Proxima pagina" title="Proxima pagina">&raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

</section>

<?php include __DIR__ . '/../_footer.php'; ?>

</main>
</div>
</body>
</html>
