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

function pesoGravidadeMunicipio(?string $nivel): int
{
    return match (strtoupper(trim((string) $nivel))) {
        'BAIXO' => 1,
        'MODERADO' => 2,
        'ALTO' => 3,
        'MUITO ALTO' => 4,
        'EXTREMO' => 5,
        default => 0,
    };
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

$sql = "
    SELECT
        a.id,
        a.numero,
        a.fonte,
        a.tipo_evento,
        a.nivel_gravidade,
        a.data_alerta,
        a.inicio_alerta,
        a.fim_alerta,
        am.municipio_codigo AS cod_ibge,
        am.municipio_nome AS municipio,
        mr.regiao_integracao AS regiao
    FROM alertas a
    INNER JOIN alerta_municipios am
        ON am.alerta_id = a.id
    INNER JOIN municipios_regioes_pa mr
        ON mr.cod_ibge = am.municipio_codigo
    WHERE " . implode(' AND ', $where) . "
    ORDER BY am.municipio_nome, a.data_alerta DESC, a.inicio_alerta DESC, a.numero DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);

$municipios = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $codIbge = (string) ($row['cod_ibge'] ?? '');

    if ($codIbge === '') {
        continue;
    }

    if (!isset($municipios[$codIbge])) {
        $municipios[$codIbge] = [
            'cod_ibge' => $codIbge,
            'municipio' => (string) ($row['municipio'] ?? ''),
            'regiao' => (string) ($row['regiao'] ?? ''),
            'alertas' => 0,
            'nivel' => null,
            'pressao' => 0,
            'tipos_evento' => [],
            'detalhes' => [],
            '_peso_max' => 0,
            '_alertas_ids' => [],
        ];
    }

    $peso = pesoGravidadeMunicipio($row['nivel_gravidade'] ?? null);

    $municipios[$codIbge]['pressao'] += $peso;
    $municipios[$codIbge]['tipos_evento'][(string) ($row['tipo_evento'] ?? '')] = true;

    if ($peso >= $municipios[$codIbge]['_peso_max']) {
        $municipios[$codIbge]['_peso_max'] = $peso;
        $municipios[$codIbge]['nivel'] = $row['nivel_gravidade'] ?? null;
    }

    $alertaId = (int) ($row['id'] ?? 0);

    if (isset($municipios[$codIbge]['_alertas_ids'][$alertaId])) {
        continue;
    }

    $municipios[$codIbge]['_alertas_ids'][$alertaId] = true;
    $municipios[$codIbge]['alertas']++;
    $municipios[$codIbge]['detalhes'][] = [
        'id' => $alertaId,
        'numero' => $row['numero'],
        'tipo_evento' => $row['tipo_evento'],
        'gravidade' => $row['nivel_gravidade'],
        'data_alerta' => $row['data_alerta'],
        'inicio_alerta' => $row['inicio_alerta'],
        'fim_alerta' => $row['fim_alerta'],
        'fonte' => $row['fonte'],
        'pressao' => $peso,
        'municipio' => $row['municipio'],
        'regiao' => $row['regiao'],
    ];
}

$resultado = array_values($municipios);

foreach ($resultado as &$municipio) {
    $municipio['tipos_evento'] = array_values(array_filter(array_keys($municipio['tipos_evento'])));
    sort($municipio['tipos_evento'], SORT_NATURAL | SORT_FLAG_CASE);

    usort($municipio['detalhes'], static function (array $a, array $b): int {
        $pesoA = (int) ($a['pressao'] ?? 0);
        $pesoB = (int) ($b['pressao'] ?? 0);

        if ($pesoA !== $pesoB) {
            return $pesoB <=> $pesoA;
        }

        return strcmp((string) ($b['inicio_alerta'] ?? ''), (string) ($a['inicio_alerta'] ?? ''));
    });

    $municipio['quantidade_alertas_ativos'] = $municipio['alertas'];

    unset($municipio['_peso_max'], $municipio['_alertas_ids']);
}
unset($municipio);

usort($resultado, static function (array $a, array $b): int {
    $pressaoA = (int) ($a['pressao'] ?? 0);
    $pressaoB = (int) ($b['pressao'] ?? 0);

    if ($pressaoA !== $pressaoB) {
        return $pressaoB <=> $pressaoA;
    }

    return strcasecmp((string) ($a['municipio'] ?? ''), (string) ($b['municipio'] ?? ''));
});

echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
exit;
