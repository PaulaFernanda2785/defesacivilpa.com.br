<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Database.php';

Protect::check(['ADMIN','GESTOR','ANALISTA','OPERACOES']);

header('Content-Type: application/json; charset=UTF-8');

$db = Database::getConnection();

$alertaId = (int)($_GET['alerta_id'] ?? 0);

if ($alertaId <= 0) {
    http_response_code(400);
    echo json_encode(['erro' => 'alerta_id inválido']);
    exit;
}

/* =========================
   REGIÕES + MUNICÍPIOS
========================= */
$stmt = $db->prepare("
    SELECT
        base.regiao_integracao,
        base.municipio
    FROM alerta_municipios am
    INNER JOIN municipios_regioes_pa base
        ON base.cod_ibge = am.municipio_codigo
    WHERE am.alerta_id = ?
    ORDER BY base.regiao_integracao, base.municipio
");

$stmt->execute([$alertaId]);

$dados = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $regiao = $row['regiao_integracao'];

    if (!isset($dados[$regiao])) {
        $dados[$regiao] = [];
    }

    $dados[$regiao][] = $row['municipio'];
}

echo json_encode($dados, JSON_UNESCAPED_UNICODE);

exit;
