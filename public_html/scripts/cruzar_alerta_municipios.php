<?php
require_once __DIR__ . '/../app/Core/Database.php';

$db = Database::getConnection();

$arquivo = __DIR__ . '/../storage/csv/municipios_regioes_pa.csv';

$db->exec("TRUNCATE TABLE municipio_regiao");

$handle = fopen($arquivo, 'r');
$header = fgetcsv($handle, 1000, ';');

$stmtMunicipio = $db->prepare("SELECT id FROM municipios WHERE nome = ?");
$stmtRegiao    = $db->prepare("SELECT id FROM regioes_integracao WHERE nome = ?");
$stmtInsert    = $db->prepare(
    "INSERT INTO municipio_regiao (municipio_id, regiao_id) VALUES (?, ?)"
);

while (($linha = fgetcsv($handle, 1000, ';')) !== false) {
    $municipio = trim($linha[0]);
    $regiao    = trim($linha[1]);

    $stmtMunicipio->execute([$municipio]);
    $stmtRegiao->execute([$regiao]);

    $m = $stmtMunicipio->fetchColumn();
    $r = $stmtRegiao->fetchColumn();

    if ($m && $r) {
        $stmtInsert->execute([$m, $r]);
    }
}

fclose($handle);

echo "✔ Municípios relacionados às regiões.";
