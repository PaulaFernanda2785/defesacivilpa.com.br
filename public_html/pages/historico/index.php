<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Services/HistoricoPdfConfig.php';
require_once __DIR__ . '/../../app/Helpers/PaginationHelper.php';
require_once __DIR__ . '/../../app/Services/HistoricoService.php';

Protect::check(['ADMIN', 'GESTOR']);

$usuario = $_SESSION['usuario'] ?? [];
$db = Database::getConnection();

$usuarioNome = trim((string) ($_GET['usuario_nome'] ?? ''));
$acaoCodigo = trim((string) ($_GET['acao_codigo'] ?? ''));
$dataInicio = trim((string) ($_GET['data_inicio'] ?? ''));
$dataFim = trim((string) ($_GET['data_fim'] ?? ''));

$pagina = max(1, (int) ($_GET['page'] ?? 1));
$limite = 10;
$offset = ($pagina - 1) * $limite;

$where = [];
$params = [];
$exclusaoAcessos = HistoricoService::montarClausulaExclusaoAcoesPagina('acao_codigo', 'historico_oculto_');

if ($exclusaoAcessos['sql'] !== '') {
    $where[] = $exclusaoAcessos['sql'];
    $params = array_merge($params, $exclusaoAcessos['params']);
}

if ($dataInicio !== '') {
    $where[] = 'data_hora >= :data_inicio';
    $params[':data_inicio'] = TimeHelper::localDateStartToUtc($dataInicio);
}

if ($dataFim !== '') {
    $where[] = 'data_hora <= :data_fim';
    $params[':data_fim'] = TimeHelper::localDateEndToUtc($dataFim);
}

if ($usuarioNome !== '') {
    $where[] = 'usuario_nome LIKE :usuario_nome';
    $params[':usuario_nome'] = '%' . $usuarioNome . '%';
}

if ($acaoCodigo !== '') {
    $where[] = 'acao_codigo = :acao_codigo';
    $params[':acao_codigo'] = $acaoCodigo;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmtTotal = $db->prepare("
    SELECT COUNT(*)
    FROM historico_usuarios
    $whereSql
");
$stmtTotal->execute($params);
$totalRegistros = (int) $stmtTotal->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalRegistros / $limite));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * $limite;

$stmtResumo = $db->prepare("
    SELECT
        COUNT(DISTINCT usuario_id) AS total_usuarios,
        COUNT(DISTINCT acao_codigo) AS total_acoes,
        MIN(data_hora) AS primeira_acao,
        MAX(data_hora) AS ultima_acao
    FROM historico_usuarios
    $whereSql
");
$stmtResumo->execute($params);
$resumo = $stmtResumo->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt = $db->prepare("
    SELECT *
    FROM historico_usuarios
    $whereSql
    ORDER BY data_hora DESC
    LIMIT :limite OFFSET :offset
");

foreach ($params as $indice => $valor) {
    $stmt->bindValue($indice, $valor);
}

$stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($registros as &$registro) {
    $registro['data_hora_local'] = TimeHelper::formatUtcDateTime($registro['data_hora'] ?? null);
    $registro['acao_label'] = HistoricoService::labelAcao(
        (string) ($registro['acao_codigo'] ?? ''),
        (string) ($registro['acao_descricao'] ?? '')
    );
    $referencia = trim((string) ($registro['referencia'] ?? ''));
    $registro['referencia_resumo'] = $referencia !== ''
        ? mb_strimwidth($referencia, 0, 92, '...')
        : 'Sem referencia complementar';
}
unset($registro);

$acoesDisponiveis = HistoricoService::catalogoAcoes($db);
$sqlUsuariosDisponiveis = "
    SELECT DISTINCT usuario_nome
    FROM historico_usuarios
    WHERE usuario_nome IS NOT NULL
      AND usuario_nome <> ''
";

if ($exclusaoAcessos['sql'] !== '') {
    $sqlUsuariosDisponiveis .= "\n  AND {$exclusaoAcessos['sql']}";
}

$sqlUsuariosDisponiveis .= "\n    ORDER BY usuario_nome";

$stmtUsuariosDisponiveis = $db->prepare($sqlUsuariosDisponiveis);
$stmtUsuariosDisponiveis->execute($exclusaoAcessos['params']);
$usuariosDisponiveis = $stmtUsuariosDisponiveis->fetchAll(PDO::FETCH_COLUMN);

$queryBase = array_filter([
    'usuario_nome' => $usuarioNome !== '' ? $usuarioNome : null,
    'acao_codigo' => $acaoCodigo !== '' ? $acaoCodigo : null,
    'data_inicio' => $dataInicio !== '' ? $dataInicio : null,
    'data_fim' => $dataFim !== '' ? $dataFim : null,
], static fn ($value) => $value !== null && $value !== '');

$filtrosAplicados = [];

if ($dataInicio !== '' || $dataFim !== '') {
    $filtrosAplicados[] = 'Periodo: ' . ($dataInicio !== '' ? $dataInicio : '-') . ' ate ' . ($dataFim !== '' ? $dataFim : '-');
}

if ($usuarioNome !== '') {
    $filtrosAplicados[] = 'Usuario: ' . $usuarioNome;
}

if ($acaoCodigo !== '') {
    $filtrosAplicados[] = 'Acao: ' . HistoricoService::labelAcao($acaoCodigo);
}

$filtrosResumo = $filtrosAplicados !== []
    ? implode(' | ', $filtrosAplicados)
    : 'Sem filtros adicionais';

$totalUsuariosFiltrados = (int) ($resumo['total_usuarios'] ?? 0);
$totalAcoesFiltradas = (int) ($resumo['total_acoes'] ?? 0);
$primeiraAcao = TimeHelper::formatUtcDateTime($resumo['primeira_acao'] ?? null, 'Sem dados');
$ultimaAcao = TimeHelper::formatUtcDateTime($resumo['ultima_acao'] ?? null, 'Sem dados');
$totalEmTela = count($registros);
$inicioRegistro = $totalRegistros > 0 ? $offset + 1 : 0;
$fimRegistro = $totalRegistros > 0 ? $offset + $totalEmTela : 0;
$totalPaginasExibicao = max(1, $totalPaginas);
$pdfQueryString = http_build_query($queryBase);
$limitePdf = HistoricoPdfConfig::MAX_REGISTROS_EXPORTACAO;
$registrosPdf = min($totalRegistros, $limitePdf);
$pdfOtimizado = $totalRegistros > $limitePdf;
$janelaAuditoria = ($primeiraAcao !== 'Sem dados' || $ultimaAcao !== 'Sem dados')
    ? $primeiraAcao . ' ate ' . $ultimaAcao
    : 'Sem dados no recorte atual';

$resumoExecutivo = [
    [
        'label' => 'Trilha auditada',
        'value' => $totalRegistros . ' registros',
        'note' => $totalRegistros > 0
            ? "Exibindo {$inicioRegistro} a {$fimRegistro} nesta pagina."
            : 'Nenhum registro localizado com os filtros atuais.',
        'tone' => 'primary',
    ],
    [
        'label' => 'Usuarios monitorados',
        'value' => $totalUsuariosFiltrados . ' usuarios',
        'note' => 'Pessoas distintas com movimentacao no recorte atual da auditoria.',
        'tone' => 'success',
    ],
    [
        'label' => 'Tipos de acao',
        'value' => $totalAcoesFiltradas . ' eventos',
        'note' => $totalAcoesFiltradas > 0
            ? 'Panorama consolidado das operacoes registradas no filtro.'
            : 'Sem tipos de acao distintos no recorte atual.',
        'tone' => 'neutral',
    ],
    [
        'label' => 'Ultima acao',
        'value' => $ultimaAcao,
        'note' => 'Referencial temporal mais recente encontrado no filtro aplicado.',
        'tone' => 'warning',
    ],
];

function paginacaoProfissionalHistorico(int $paginaAtual, int $totalPaginas): array
{
    return PaginationHelper::marcadoresCompactos($paginaAtual, $totalPaginas);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Historico do Usuario</title>
<link rel="icon" type="image/x-icon" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/logo.cbmpa.ico">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/logo.cbmpa.ico">
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/painel.css">
<link rel="stylesheet" href="/assets/css/pages/alertas-form.css">
<link rel="stylesheet" href="/assets/css/pages/usuarios-listar.css">
<link rel="stylesheet" href="/assets/css/pages/historico-index.css">
</head>

<body>
<div class="layout">
    <?php include __DIR__ . '/../_sidebar.php'; ?>

    <main class="content">
        <?php include __DIR__ . '/../_topbar.php'; ?>
        <?php
        $breadcrumb = [
            'Painel' => '/pages/painel.php',
            'Historico dos usuarios' => null,
        ];
        include __DIR__ . '/../_breadcrumb.php';
        ?>

        <section class="dashboard alerta-form-shell usuarios-shell historico-shell">
            <div class="usuarios-hero-grid historico-hero-grid">
                <div class="alerta-form-hero usuarios-hero-panel historico-hero-panel">
                    <div class="alerta-form-lead usuarios-hero-copy historico-hero-copy">
                        <span class="alerta-form-kicker">Auditoria operacional</span>
                        <h1 class="alerta-form-title">Historico dos usuarios</h1>
                        <p class="alerta-form-description">
                            Consolide autenticacao, operacoes de alerta, gestao de usuarios e relatorios em uma
                            trilha unica de auditoria, com leitura mais clara para consulta, filtro e exportacao.
                        </p>

                        <div class="usuarios-hero-chip-row">
                            <span class="usuarios-hero-chip">Pagina <?= $pagina ?> de <?= $totalPaginasExibicao ?></span>
                            <span class="usuarios-hero-chip"><?= $totalAcoesFiltradas ?> tipos de acao</span>
                            <span class="usuarios-hero-chip"><?= htmlspecialchars($filtrosResumo, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>

                        <div class="usuarios-hero-actions historico-hero-actions">
                            <a href="#historico-filtros" class="btn btn-primary">
                                Aplicar filtros
                            </a>
                            <a href="#historico-eventos" class="btn btn-secondary">
                                Eventos registrados
                            </a>
                        </div>
                    </div>
                </div>

                <div class="usuarios-summary-grid historico-summary-grid">
                    <?php foreach ($resumoExecutivo as $cardResumo): ?>
                        <article class="usuarios-summary-card usuarios-summary-card-<?= htmlspecialchars((string) ($cardResumo['tone'] ?? 'primary'), ENT_QUOTES, 'UTF-8') ?>">
                            <span class="usuarios-summary-label"><?= htmlspecialchars((string) ($cardResumo['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            <strong class="usuarios-summary-value"><?= htmlspecialchars((string) ($cardResumo['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                            <span class="usuarios-summary-note"><?= htmlspecialchars((string) ($cardResumo['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        </article>
                    <?php endforeach; ?>
                </div>

                <aside class="usuarios-command-card historico-command-card">
                    <span class="usuarios-command-kicker">Painel de auditoria</span>
                    <h2>Comando rapido do historico institucional</h2>
                    <p>
                        Esta area resume o operador da sessao, a janela temporal consultada e a politica de exportacao
                        otimizada para manter a leitura estavel com bases extensas.
                    </p>

                    <div class="usuarios-command-grid historico-command-grid">
                        <article class="usuarios-command-item">
                            <span>Operador da sessao</span>
                            <strong><?= htmlspecialchars((string) ($usuario['nome'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></strong>
                            <small>Responsavel pela navegacao, filtros ativos e exportacoes do recorte atual.</small>
                        </article>

                        <article class="usuarios-command-item">
                            <span>Janela auditada</span>
                            <strong><?= htmlspecialchars($janelaAuditoria, ENT_QUOTES, 'UTF-8') ?></strong>
                            <small>Combinacao entre o primeiro e o ultimo registro encontrados no filtro aplicado.</small>
                        </article>

                        <article class="usuarios-command-item">
                            <span>Exportacao PDF</span>
                            <strong><?= $pdfOtimizado ? 'Modo otimizado ativo' : 'Recorte completo no PDF' ?></strong>
                            <small>
                                <?= $pdfOtimizado
                                    ? "O relatorio exporta os {$registrosPdf} registros mais recentes para acelerar a geracao."
                                    : 'O recorte atual cabe integralmente no PDF sem reduzir o volume exportado.' ?>
                            </small>
                        </article>
                    </div>
                </aside>
            </div>

            <div class="alerta-form-panel usuarios-control-panel historico-control-panel">
                <div class="usuarios-control-grid historico-overview-grid">
                    <section id="historico-filtros" class="alerta-form-section usuarios-filter-panel historico-filter-panel">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Consulta administrativa</span>
                            <h2 class="alerta-section-title">Filtrar, localizar e reduzir o recorte</h2>
                            <p class="alerta-section-text">
                                Refine a consulta por periodo, usuario e tipo de acao. O recorte permanece sincronizado
                                com a paginacao, os indicadores executivos e a exportacao do relatorio em PDF.
                            </p>
                        </header>

                        <form method="get" class="usuarios-filters historico-filters">
                            <div class="usuarios-filter-grid historico-filter-grid">
                                <div class="form-group">
                                    <label for="data_inicio">Data inicial</label>
                                    <input type="date" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($dataInicio, ENT_QUOTES, 'UTF-8') ?>">
                                </div>

                                <div class="form-group">
                                    <label for="data_fim">Data final</label>
                                    <input type="date" id="data_fim" name="data_fim" value="<?= htmlspecialchars($dataFim, ENT_QUOTES, 'UTF-8') ?>">
                                </div>

                                <div class="form-group historico-field-span-2">
                                    <label for="usuario_nome">Usuario</label>
                                    <input
                                        type="text"
                                        id="usuario_nome"
                                        name="usuario_nome"
                                        list="lista-usuarios"
                                        placeholder="Digite o nome do usuario"
                                        value="<?= htmlspecialchars($usuarioNome, ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                </div>

                                <div class="form-group historico-field-span-2">
                                    <label for="acao_codigo">Tipo de acao</label>
                                    <select id="acao_codigo" name="acao_codigo">
                                        <option value="">Todas as acoes</option>
                                        <?php foreach ($acoesDisponiveis as $acao): ?>
                                            <option value="<?= htmlspecialchars($acao['codigo'], ENT_QUOTES, 'UTF-8') ?>" <?= $acaoCodigo === $acao['codigo'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($acao['label'], ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <datalist id="lista-usuarios">
                                <?php foreach ($usuariosDisponiveis as $nomeUsuario): ?>
                                    <option value="<?= htmlspecialchars((string) $nomeUsuario, ENT_QUOTES, 'UTF-8') ?>">
                                <?php endforeach; ?>
                            </datalist>

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

                            <div class="alerta-form-actions usuarios-filter-actions historico-filter-actions">
                                <div class="alerta-form-actions-left">
                                    <span class="alerta-inline-note">A consulta preserva pagina, resumo executivo e politica de exportacao do PDF.</span>
                                </div>

                                <div class="alerta-form-actions-right usuarios-filter-actions-group">
                                    <button type="button" class="btn btn-secondary" onclick="limparFiltrosHistorico()">Limpar filtros</button>
                                    <button type="submit" class="btn btn-primary">Atualizar painel</button>
                                </div>
                            </div>
                        </form>
                    </section>

                    <section class="alerta-form-section usuarios-governance-panel historico-governance-panel">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Leitura executiva</span>
                            <h2 class="alerta-section-title">Cobertura temporal, exportacao e rastreabilidade</h2>
                            <p class="alerta-section-text">
                                Use os cards abaixo para entender a janela auditada, a intensidade do recorte e a forma
                                como o PDF responde quando o historico cresce demais.
                            </p>
                        </header>

                        <div class="usuarios-insight-grid historico-insight-grid">
                            <article class="usuarios-insight-card usuarios-insight-card-emphasis">
                                <span class="usuarios-insight-kicker">Janela auditada</span>
                                <strong><?= htmlspecialchars($janelaAuditoria, ENT_QUOTES, 'UTF-8') ?></strong>
                                <p>Leitura temporal do recorte ativo considerando o primeiro e o ultimo evento encontrados.</p>
                            </article>

                            <article class="usuarios-insight-card">
                                <span class="usuarios-insight-kicker">Volume do relatorio</span>
                                <strong><?= $registrosPdf ?> registro<?= $registrosPdf === 1 ? '' : 's' ?> no PDF</strong>
                                <p>
                                    <?= $pdfOtimizado
                                        ? "Para acelerar a geracao, o PDF usa os {$registrosPdf} registros mais recentes do recorte."
                                        : 'O recorte atual cabe integralmente no PDF sem necessidade de limitar a exportacao.' ?>
                                </p>
                            </article>

                            <article class="usuarios-insight-card">
                                <span class="usuarios-insight-kicker">Primeira ocorrencia</span>
                                <strong><?= htmlspecialchars($primeiraAcao, ENT_QUOTES, 'UTF-8') ?></strong>
                                <p>Marco inicial encontrado na auditoria atual para orientar a leitura do periodo coberto.</p>
                            </article>

                            <article class="usuarios-insight-card historico-export-card">
                                <span class="usuarios-insight-kicker">Exportacao otimizada</span>
                                <strong>PDF alinhado ao filtro ativo</strong>
                                <p>O relatorio preserva o mesmo recorte aplicado nesta tela e prioriza desempenho em bases extensas.</p>
                                <a href="/pages/historico/relatorio_pdf.php?<?= htmlspecialchars($pdfQueryString, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary" target="_blank">
                                    Gerar PDF agora
                                </a>
                            </article>
                        </div>
                    </section>
                </div>
            </div>

            <section id="historico-eventos" class="alerta-form-panel usuarios-table-panel historico-table-panel">
                <header class="usuarios-table-head historico-table-head">
                    <div class="alerta-section-header">
                        <span class="alerta-section-kicker">Base de auditoria</span>
                        <h2 class="alerta-section-title">Lista de eventos registrados</h2>
                        <p class="alerta-section-text">
                            A tabela abaixo consolida data e hora, usuario, acao registrada, referencia contextual e acesso
                            ao detalhe completo de cada operacao em um desenho mais consistente com a pagina de usuarios.
                        </p>
                    </div>

                    <div class="usuarios-table-head-actions">
                        <span class="usuarios-result-chip historico-result-chip">
                            <?= $totalRegistros > 0 ? "Exibindo {$inicioRegistro} a {$fimRegistro} de {$totalRegistros}" : 'Nenhum resultado no filtro atual' ?>
                        </span>
                    </div>
                </header>

                <div class="usuarios-table-toolbar historico-table-toolbar">
                    <div class="usuarios-table-toolbar-copy">
                        <strong>Recorte administrativo:</strong>
                        <span><?= htmlspecialchars($filtrosResumo, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <div class="usuarios-table-toolbar-pills">
                        <span class="usuarios-toolbar-pill"><?= $totalUsuariosFiltrados ?> usuarios</span>
                        <span class="usuarios-toolbar-pill"><?= $totalAcoesFiltradas ?> acoes</span>
                        <span class="usuarios-toolbar-pill"><?= $registrosPdf ?> no PDF</span>
                    </div>
                </div>

                <div class="historico-table-wrap">
                    <table class="tabela-historico">
                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th>Usuario</th>
                                <th>Acao</th>
                                <th>Referencia</th>
                                <th>Detalhes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($registros === []): ?>
                                <tr>
                                    <td colspan="5" class="historico-empty-row">Nenhum registro encontrado para os filtros informados.</td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($registros as $registro): ?>
                                <tr>
                                    <td data-label="Data/Hora"><?= htmlspecialchars($registro['data_hora_local'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Usuario"><?= htmlspecialchars((string) $registro['usuario_nome'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Acao">
                                        <div class="historico-acao-cell">
                                            <span class="historico-acao-label"><?= htmlspecialchars((string) $registro['acao_label'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="historico-acao-code"><?= htmlspecialchars((string) $registro['acao_codigo'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    </td>
                                    <td data-label="Referencia"><?= htmlspecialchars((string) $registro['referencia_resumo'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td data-label="Detalhes">
                                        <button
                                            type="button"
                                            class="btn-acao btn-detalhe"
                                            onclick='abrirModalHistorico(<?= json_encode($registro, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                        >
                                            Detalhes
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPaginas > 1): ?>
                    <div class="usuarios-pagination-wrap historico-pagination-wrap">
                        <div class="usuarios-pagination-copy">
                            <strong>Paginacao da auditoria</strong>
                            <span>Navegue pelo historico sem perder o recorte aplicado.</span>
                        </div>

                        <div class="paginacao">
                            <?php if ($pagina > 1): ?>
                                <a href="?<?= http_build_query(array_merge($queryBase, ['page' => $pagina - 1])) ?>" aria-label="Pagina anterior" title="Pagina anterior">
                                    &laquo;
                                </a>
                            <?php endif; ?>

                            <?php foreach (paginacaoProfissionalHistorico($pagina, $totalPaginas) as $itemPaginacao): ?>
                                <?php if ($itemPaginacao === '...'): ?>
                                    <span class="paginacao-ellipsis">...</span>
                                <?php else: ?>
                                    <a
                                        href="?<?= http_build_query(array_merge($queryBase, ['page' => $itemPaginacao])) ?>"
                                        class="<?= $itemPaginacao === $pagina ? 'ativa' : '' ?>"
                                    >
                                        <?= $itemPaginacao ?>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <?php if ($pagina < $totalPaginas): ?>
                                <a href="?<?= http_build_query(array_merge($queryBase, ['page' => $pagina + 1])) ?>" aria-label="Proxima pagina" title="Proxima pagina">
                                    &raquo;
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        </section>

        <?php include __DIR__ . '/../_footer.php'; ?>
    </main>
</div>

<div id="modalHistorico" class="modal-ajuda">
    <div class="modal-ajuda-conteudo historico-modal-conteudo">
        <div class="modal-ajuda-header">
            <h3>Detalhe da acao registrada</h3>
            <button type="button" onclick="fecharModalHistorico()">X</button>
        </div>

        <div class="modal-ajuda-body" id="historico-modal-body"></div>

        <div class="modal-ajuda-footer">
            <button type="button" onclick="fecharModalHistorico()">Fechar</button>
        </div>
    </div>
</div>

<script src="/assets/js/pages/historico-index.js"></script>
</body>
</html>
