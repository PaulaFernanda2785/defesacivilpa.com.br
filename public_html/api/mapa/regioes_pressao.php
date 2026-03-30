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
    ORDER BY regiao, a.data_alerta DESC, a.inicio_alerta DESC, a.numero DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);

$regioes = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $regiao = trim((string) ($row['regiao'] ?? ''));

    if ($regiao === '') {
        continue;
    }

    if (!isset($regioes[$regiao])) {
        $regioes[$regiao] = [
            'regiao' => $regiao,
            'alertas' => 0,
            'municipios' => 0,
            'gravidade' => null,
            'pressao' => 0,
            'tipos_evento' => [],
            'municipios_lista' => [],
            'detalhes' => [],
            '_peso_max' => 0,
            '_alertas' => [],
        ];
    }

    $peso = pesoGravidadeMapa($row['nivel_gravidade'] ?? null);
    $alertaId = (int) ($row['id'] ?? 0);
    $codIbge = (string) ($row['cod_ibge'] ?? '');
    $municipioNome = (string) ($row['municipio'] ?? '');

    $regioes[$regiao]['pressao'] += $peso;
    $regioes[$regiao]['tipos_evento'][(string) ($row['tipo_evento'] ?? '')] = true;

    if ($codIbge !== '' && $municipioNome !== '') {
        $regioes[$regiao]['municipios_lista'][$codIbge] = $municipioNome;
    }

    if ($peso >= $regioes[$regiao]['_peso_max']) {
        $regioes[$regiao]['_peso_max'] = $peso;
        $regioes[$regiao]['gravidade'] = $row['nivel_gravidade'] ?? null;
    }

    if (!isset($regioes[$regiao]['_alertas'][$alertaId])) {
        $regioes[$regiao]['_alertas'][$alertaId] = [
            'id' => $alertaId,
            'numero' => $row['numero'],
            'tipo_evento' => $row['tipo_evento'],
            'gravidade' => $row['nivel_gravidade'],
            'peso_gravidade' => $peso,
            'data_alerta' => $row['data_alerta'],
            'inicio_alerta' => $row['inicio_alerta'],
            'fim_alerta' => $row['fim_alerta'],
            'fonte' => $row['fonte'],
            'pressao' => 0,
            'municipios' => [],
            'regiao' => $regiao,
        ];
        $regioes[$regiao]['alertas']++;
    }

    $regioes[$regiao]['_alertas'][$alertaId]['pressao'] += $peso;

    if ($codIbge !== '' && $municipioNome !== '') {
        $regioes[$regiao]['_alertas'][$alertaId]['municipios'][$codIbge] = $municipioNome;
    }
}

$resultado = array_values($regioes);

foreach ($resultado as &$regiao) {
    $regiao['tipos_evento'] = array_values(array_filter(array_keys($regiao['tipos_evento'])));
    sort($regiao['tipos_evento'], SORT_NATURAL | SORT_FLAG_CASE);

    $regiao['municipios_lista'] = array_values($regiao['municipios_lista']);
    sort($regiao['municipios_lista'], SORT_NATURAL | SORT_FLAG_CASE);
    $regiao['municipios'] = count($regiao['municipios_lista']);

    $regiao['detalhes'] = array_values($regiao['_alertas']);

    foreach ($regiao['detalhes'] as &$detalhe) {
        $detalhe['municipios'] = array_values($detalhe['municipios']);
        sort($detalhe['municipios'], SORT_NATURAL | SORT_FLAG_CASE);
        $detalhe['municipios_total'] = count($detalhe['municipios']);
    }
    unset($detalhe);

    usort($regiao['detalhes'], static function (array $a, array $b): int {
        $pressaoA = (int) ($a['pressao'] ?? 0);
        $pressaoB = (int) ($b['pressao'] ?? 0);

        if ($pressaoA !== $pressaoB) {
            return $pressaoB <=> $pressaoA;
        }

        return strcmp((string) ($b['inicio_alerta'] ?? ''), (string) ($a['inicio_alerta'] ?? ''));
    });

    $regiao['pressao'] = (int) $regiao['pressao'];
    $regiao['alertas'] = (int) $regiao['alertas'];
    $regiao['municipios'] = (int) $regiao['municipios'];
    $regiao['quantidade_alertas_ativos'] = (int) $regiao['alertas'];

    unset($regiao['_peso_max'], $regiao['_alertas']);
}
unset($regiao);

usort($resultado, static function (array $a, array $b): int {
    $pressaoA = (int) ($a['pressao'] ?? 0);
    $pressaoB = (int) ($b['pressao'] ?? 0);

    if ($pressaoA !== $pressaoB) {
        return $pressaoB <=> $pressaoA;
    }

    return strcasecmp((string) ($a['regiao'] ?? ''), (string) ($b['regiao'] ?? ''));
});

echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
exit;
