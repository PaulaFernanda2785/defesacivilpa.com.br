<?php
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Helpers/SecurityHeaders.php';
require_once __DIR__ . '/_pressao_shared.php';

SecurityHeaders::applyJson(60, true);

$db = Database::getConnection();

[$where, $params] = montarFiltroMapaPressao($_GET, 'a', 'am', 'mr');

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
        MAX(am.municipio_nome) AS municipio,
        MAX(mr.regiao_integracao) AS regiao
    FROM alertas a
    INNER JOIN alerta_municipios am
        ON am.alerta_id = a.id
    INNER JOIN municipios_regioes_pa mr
        ON mr.cod_ibge = am.municipio_codigo
    WHERE " . implode(' AND ', $where) . "
    GROUP BY
        a.id,
        a.numero,
        a.fonte,
        a.tipo_evento,
        a.nivel_gravidade,
        a.data_alerta,
        a.inicio_alerta,
        a.fim_alerta,
        am.municipio_codigo
    ORDER BY municipio, a.data_alerta DESC, a.inicio_alerta DESC, a.numero DESC
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
        ];
    }

    $peso = pesoGravidadeMapa($row['nivel_gravidade'] ?? null);

    $municipios[$codIbge]['pressao'] += $peso;
    $municipios[$codIbge]['tipos_evento'][(string) ($row['tipo_evento'] ?? '')] = true;

    if ($peso >= $municipios[$codIbge]['_peso_max']) {
        $municipios[$codIbge]['_peso_max'] = $peso;
        $municipios[$codIbge]['nivel'] = $row['nivel_gravidade'] ?? null;
    }

    $municipios[$codIbge]['alertas']++;
    $municipios[$codIbge]['detalhes'][] = [
        'id' => (int) ($row['id'] ?? 0),
        'numero' => $row['numero'],
        'tipo_evento' => $row['tipo_evento'],
        'gravidade' => $row['nivel_gravidade'],
        'peso_gravidade' => $peso,
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

    $municipio['pressao'] = (int) $municipio['pressao'];
    $municipio['alertas'] = (int) $municipio['alertas'];
    $municipio['quantidade_alertas_ativos'] = (int) $municipio['alertas'];

    unset($municipio['_peso_max']);
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
