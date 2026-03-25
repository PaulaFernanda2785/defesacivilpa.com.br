<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Services/HistoricoPdfConfig.php';
require_once __DIR__ . '/../../app/Services/PdfHistoricoService.php';
require_once __DIR__ . '/../../app/Services/HistoricoService.php';

Protect::check(['ADMIN', 'GESTOR']);

$db = Database::getConnection();

/* =========================
   FILTROS
========================= */
$usuarioNome = trim((string) ($_GET['usuario_nome'] ?? ''));
$acaoCodigo = trim((string) ($_GET['acao_codigo'] ?? ''));
$dataInicio = trim((string) ($_GET['data_inicio'] ?? ''));
$dataFim = trim((string) ($_GET['data_fim'] ?? ''));

/* =========================
   WHERE DINAMICO
========================= */
$where = [];
$params = [];
$exclusaoAcessos = HistoricoService::montarClausulaExclusaoAcoesPagina('acao_codigo', 'pdf_oculto_');

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

/* =========================
   TOTAL DO RECORTE
========================= */
$stmtTotal = $db->prepare("
    SELECT COUNT(*)
    FROM historico_usuarios
    $whereSql
");
$stmtTotal->execute($params);
$totalRegistros = (int) $stmtTotal->fetchColumn();

$limiteExportacao = HistoricoPdfConfig::MAX_REGISTROS_EXPORTACAO;

/* =========================
   TOTALIZADORES EM SQL
========================= */
$stmtTotalizadores = $db->prepare("
    SELECT
        acao_codigo,
        COUNT(*) AS total
    FROM historico_usuarios
    $whereSql
    GROUP BY acao_codigo
    ORDER BY total DESC, acao_codigo
");
$stmtTotalizadores->execute($params);

$totalizadores = [];

foreach ($stmtTotalizadores->fetchAll(PDO::FETCH_ASSOC) as $itemTotalizador) {
    $codigoAcao = trim((string) ($itemTotalizador['acao_codigo'] ?? ''));

    if ($codigoAcao === '') {
        continue;
    }

    $totalizadores[$codigoAcao] = (int) ($itemTotalizador['total'] ?? 0);
}

/* =========================
   BUSCA REGISTROS OTIMIZADA
========================= */
$stmt = $db->prepare("
    SELECT
        data_hora,
        usuario_nome,
        acao_codigo,
        acao_descricao,
        COALESCE(referencia, '-') AS referencia
    FROM historico_usuarios
    $whereSql
    ORDER BY data_hora DESC
    LIMIT :limite
");

foreach ($params as $indice => $valor) {
    $stmt->bindValue($indice, $valor);
}

$stmt->bindValue(':limite', $limiteExportacao, PDO::PARAM_INT);
$stmt->execute();

$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
$registrosExportados = count($registros);
$houveCorte = $totalRegistros > $registrosExportados;

/* =========================
   DESCRICAO DOS FILTROS
========================= */
$filtrosDescricao = [
    'periodo' => (
        $dataInicio !== '' || $dataFim !== ''
            ? sprintf(
                '%s ate %s',
                $dataInicio !== '' ? TimeHelper::formatDate($dataInicio, '-') : '-',
                $dataFim !== '' ? TimeHelper::formatDate($dataFim, '-') : '-'
            )
            : 'Todos'
    ),
    'usuario' => $usuarioNome !== '' ? $usuarioNome : 'Todos',
    'acao' => $acaoCodigo !== '' ? HistoricoService::labelAcao($acaoCodigo) : 'Todas',
];

$assinaturaExportacao = [
    'filtros' => $filtrosDescricao,
    'total_registros' => $totalRegistros,
    'registros_exportados' => $registrosExportados,
    'houve_corte' => $houveCorte,
    'ultima_data_exportada' => $registros[0]['data_hora'] ?? null,
    'primeira_data_exportada' => $registros[$registrosExportados - 1]['data_hora'] ?? null,
    'totalizadores' => $totalizadores,
];

foreach ($registros as &$registro) {
    $registro['data_hora'] = TimeHelper::formatUtcDateTime($registro['data_hora'] ?? null);
}
unset($registro);

$usuario = $_SESSION['usuario'] ?? null;

if (is_array($usuario) && !empty($usuario['id']) && !empty($usuario['nome'])) {
    $referencias = [];

    if ($usuarioNome !== '') {
        $referencias[] = 'Usuario: ' . $usuarioNome;
    }

    if ($acaoCodigo !== '') {
        $referencias[] = 'Acao: ' . HistoricoService::labelAcao($acaoCodigo);
    }

    if ($dataInicio !== '' || $dataFim !== '') {
        $referencias[] = 'Periodo: ' . ($dataInicio !== '' ? $dataInicio : '-') . ' ate ' . ($dataFim !== '' ? $dataFim : '-');
    }

    HistoricoService::registrar(
        (int) $usuario['id'],
        (string) $usuario['nome'],
        'BAIXAR_RELATORIO_HISTORICO',
        'Baixou o relatorio do historico de usuarios em PDF',
        $referencias !== [] ? implode(' | ', $referencias) : 'Sem filtros adicionais'
    );
}

/* =========================
   HASH DO RELATORIO
========================= */
$hash = hash('sha256', json_encode($assinaturaExportacao));

/* =========================
   GERA PDF
========================= */
PdfHistoricoService::gerar(
    $registros,
    $filtrosDescricao,
    (string) ($_SESSION['usuario']['nome'] ?? 'Usuario nao identificado'),
    $totalizadores,
    $hash,
    [
        'total_registros' => $totalRegistros,
        'registros_exportados' => $registrosExportados,
        'limite_registros' => $limiteExportacao,
        'houve_corte' => $houveCorte,
    ]
);
