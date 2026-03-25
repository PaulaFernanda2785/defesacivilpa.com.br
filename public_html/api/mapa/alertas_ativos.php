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

$where = [
    "a.status = 'ATIVO'",
    "a.area_geojson IS NOT NULL",
    "a.area_geojson <> ''",
];
$params = [];
$territorioWhere = [];

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
    $territorioWhere[] = "mr.regiao_integracao = :regiao";
    $params[':regiao'] = trim((string) $_GET['regiao']);
}

if (!empty($_GET['municipio'])) {
    $municipio = trim((string) $_GET['municipio']);

    if (preg_match('/^\d{7}$/', $municipio)) {
        $territorioWhere[] = "am.municipio_codigo = :municipio_codigo";
        $params[':municipio_codigo'] = $municipio;
    } else {
        $territorioWhere[] = "am.municipio_nome = :municipio_nome";
        $params[':municipio_nome'] = $municipio;
    }
}

if (!empty($territorioWhere)) {
    $where[] = "
        EXISTS (
            SELECT 1
            FROM alerta_municipios am
            INNER JOIN municipios_regioes_pa mr
                ON mr.cod_ibge = am.municipio_codigo
            WHERE am.alerta_id = a.id
              AND " . implode(' AND ', $territorioWhere) . "
        )
    ";
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
        a.area_geojson
    FROM alertas a
    WHERE " . implode(' AND ', $where) . "
    ORDER BY a.data_alerta DESC, a.inicio_alerta DESC, a.numero DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);

$features = [];

while ($alerta = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $geojson = json_decode((string) ($alerta['area_geojson'] ?? ''), true);

    if (!$geojson) {
        continue;
    }

    $geojsonFeatures = [];

    if (($geojson['type'] ?? null) === 'FeatureCollection' && !empty($geojson['features']) && is_array($geojson['features'])) {
        $geojsonFeatures = $geojson['features'];
    } elseif (($geojson['type'] ?? null) === 'Feature' && !empty($geojson['geometry'])) {
        $geojsonFeatures = [$geojson];
    } elseif (!empty($geojson['geometry'])) {
        $geojsonFeatures = [[
            'type' => 'Feature',
            'geometry' => $geojson['geometry'],
        ]];
    }

    if (!$geojsonFeatures) {
        continue;
    }

    foreach ($geojsonFeatures as $feature) {
        if (empty($feature['geometry'])) {
            continue;
        }

        $features[] = [
            'type' => 'Feature',
            'geometry' => $feature['geometry'],
            'properties' => [
                'id' => (int) $alerta['id'],
                'numero' => $alerta['numero'],
                'fonte' => $alerta['fonte'],
                'tipo_evento' => $alerta['tipo_evento'],
                'nivel_gravidade' => $alerta['nivel_gravidade'],
                'data_alerta' => $alerta['data_alerta'],
                'inicio_alerta' => $alerta['inicio_alerta'],
                'fim_alerta' => $alerta['fim_alerta'],
            ],
        ];
    }
}

echo json_encode([
    'type' => 'FeatureCollection',
    'features' => $features,
], JSON_UNESCAPED_UNICODE);

exit;
