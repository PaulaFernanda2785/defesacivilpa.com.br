<?php
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Helpers/SecurityHeaders.php';
require_once __DIR__ . '/_pressao_shared.php';

SecurityHeaders::applyJson(60, true);

$db = Database::getConnection();

[$where, $params] = montarFiltroMapaPressao($_GET, 'a', 'am', 'mr');
$pesoGravidadeSql = casoPesoGravidadeSql('a.nivel_gravidade');

$sql = "
    SELECT
        base.dia,
        SUM(base.pontos_irp) AS irp
    FROM (
        SELECT
            a.id AS alerta_id,
            a.data_alerta AS dia,
            ({$pesoGravidadeSql} * COUNT(DISTINCT am.municipio_codigo)) AS pontos_irp
        FROM alertas a
        INNER JOIN alerta_municipios am
            ON am.alerta_id = a.id
        INNER JOIN municipios_regioes_pa mr
            ON mr.cod_ibge = am.municipio_codigo
        WHERE " . implode(' AND ', $where) . "
        GROUP BY
            a.id,
            a.data_alerta,
            a.nivel_gravidade
    ) AS base
    GROUP BY base.dia
    ORDER BY base.dia
";

$stmt = $db->prepare($sql);
$stmt->execute($params);

$resultado = [];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $resultado[] = [
        'dia' => $row['dia'],
        'irp' => (int) ($row['irp'] ?? 0),
    ];
}

echo json_encode($resultado, JSON_UNESCAPED_UNICODE);

exit;
