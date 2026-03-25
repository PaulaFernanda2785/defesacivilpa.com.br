<?php
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Helpers/SecurityHeaders.php';

SecurityHeaders::applyJson(60, true);

$db = Database::getConnection();

function normalizarDataFiltroMapa(?string $valor): ?DateTimeImmutable
{
    if ($valor === null) {
        return null;
    }

    $valor = trim($valor);

    if ($valor === '') {
        return null;
    }

    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

    if (!($data instanceof DateTimeImmutable)) {
        return null;
    }

    return $data->format('Y-m-d') === $valor ? $data : null;
}

$where = ["a.status = 'ATIVO'"];
$params = [];

if (!empty($_GET['data_inicio'])) {
    $dataInicio = normalizarDataFiltroMapa((string) $_GET['data_inicio']);

    if ($dataInicio instanceof DateTimeImmutable) {
        $where[] = "a.data_alerta >= :data_inicio";
        $params[':data_inicio'] = $dataInicio->format('Y-m-d');
    }
}

if (!empty($_GET['data_fim'])) {
    $dataFim = normalizarDataFiltroMapa((string) $_GET['data_fim']);

    if ($dataFim instanceof DateTimeImmutable) {
        $where[] = "a.data_alerta < :data_fim_exclusiva";
        $params[':data_fim_exclusiva'] = $dataFim->modify('+1 day')->format('Y-m-d');
    }
}

if (!empty($_GET['gravidade'])) {
    $where[] = "a.nivel_gravidade = :gravidade";
    $params[':gravidade'] = $_GET['gravidade'];
}

if (!empty($_GET['fonte'])) {
    $where[] = "a.fonte = :fonte";
    $params[':fonte'] = $_GET['fonte'];
}

if (!empty($_GET['tipo_evento'])) {
    $where[] = "a.tipo_evento = :tipo_evento";
    $params[':tipo_evento'] = $_GET['tipo_evento'];
}

if (!empty($_GET['regiao'])) {
    $where[] = "mr.regiao_integracao = :regiao";
    $params[':regiao'] = trim((string) $_GET['regiao']);
}

if (!empty($_GET['municipio'])) {
    $municipio = trim((string) $_GET['municipio']);

    if (preg_match('/^\d{7}$/', $municipio)) {
        $where[] = "am.municipio_codigo = :municipio_codigo";
        $params[':municipio_codigo'] = $municipio;
    } else {
        $where[] = "am.municipio_nome = :municipio_nome";
        $params[':municipio_nome'] = $municipio;
    }
}

$whereSql = implode(' AND ', $where);
$fromSql = "
    FROM alertas a
    LEFT JOIN alerta_municipios am
        ON am.alerta_id = a.id
    LEFT JOIN municipios_regioes_pa mr
        ON mr.cod_ibge = am.municipio_codigo
";

$stmt = $db->prepare("
    SELECT
        COUNT(DISTINCT a.id) AS alertas_ativos,
        COUNT(DISTINCT CASE WHEN am.municipio_codigo IS NOT NULL THEN am.municipio_codigo END) AS municipios,
        COUNT(DISTINCT CASE WHEN mr.regiao_integracao IS NOT NULL THEN mr.regiao_integracao END) AS regioes
    $fromSql
    WHERE $whereSql
");
$stmt->execute($params);
$kpis = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$alertasAtivos = (int) ($kpis['alertas_ativos'] ?? 0);
$municipios = (int) ($kpis['municipios'] ?? 0);
$regioes = (int) ($kpis['regioes'] ?? 0);

echo json_encode([
    'alertas_ativos' => $alertasAtivos,
    'municipios' => $municipios,
    'regioes' => $regioes,
], JSON_UNESCAPED_UNICODE);

exit;
