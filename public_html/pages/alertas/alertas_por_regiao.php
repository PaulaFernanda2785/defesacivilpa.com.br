<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Database.php';

Protect::check(['ADMIN','GESTOR','ANALISTA','OPERACOES']);

$db = Database::getConnection();

$alertaId = (int)($_GET['id'] ?? 0);
if ($alertaId <= 0) {
    echo json_encode([]);
    exit;
}

/* 1️⃣ Regiões do alerta clicado */
$stmt = $db->prepare("
    SELECT DISTINCT regiao_integracao
    FROM alerta_regioes
    WHERE alerta_id = ?
");
$stmt->execute([$alertaId]);

$regioes = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (!$regioes) {
    echo json_encode([]);
    exit;
}

/* 2️⃣ Todos os alertas ATIVOS nessas regiões */
$placeholders = implode(',', array_fill(0, count($regioes), '?'));

$sql = "
    SELECT DISTINCT a.id
    FROM alertas a
    JOIN alerta_regioes r ON r.alerta_id = a.id
    WHERE a.status = 'ATIVO'
      AND r.regiao_integracao IN ($placeholders)
";

$stmt = $db->prepare($sql);
$stmt->execute($regioes);

echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
