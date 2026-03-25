<?php
require_once __DIR__ . '/../app/Core/Database.php';

$db = Database::getConnection();

$arquivo = __DIR__ . '/../storage/geo/municipios_pa.geojson';

if (!file_exists($arquivo)) {
    die('Arquivo GeoJSON não encontrado.');
}

$json = json_decode(file_get_contents($arquivo), true);

$db->exec("TRUNCATE TABLE municipios");

$sql = "INSERT INTO municipios (codigo_ibge, nome, geometria) VALUES (?, ?, ?)";
$stmt = $db->prepare($sql);

foreach ($json['features'] as $feature) {
    $codigo = $feature['properties']['CD_MUN'];
    $nome   = $feature['properties']['NM_MUN'];
    $geom   = json_encode($feature['geometry']);

    $stmt->execute([$codigo, $nome, $geom]);
}

echo "✔ Municípios importados com sucesso.";
