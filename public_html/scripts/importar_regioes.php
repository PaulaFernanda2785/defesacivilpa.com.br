<?php
require_once __DIR__ . '/../app/Core/Database.php';

$db = Database::getConnection();

$arquivo = __DIR__ . '/../storage/csv/municipios_regioes_pa.csv';

if (!file_exists($arquivo)) {
    die('Arquivo CSV não encontrado.');
}

$db->exec("TRUNCATE TABLE regioes_integracao");

$regioes = [];

$handle = fopen($arquivo, 'r');
$header = fgetcsv($handle, 1000, ';');

while (($linha = fgetcsv($handle, 1000, ';')) !== false) {
    $regiao = trim($linha[1]);
    $regioes[$regiao] = true;
}

fclose($handle);

$stmt = $db->prepare("INSERT INTO regioes_integracao (nome) VALUES (?)");

foreach (array_keys($regioes) as $regiao) {
    $stmt->execute([$regiao]);
}

echo "✔ Regiões de integração importadas.";
