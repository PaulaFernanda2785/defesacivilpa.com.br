<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Services/HistoricoService.php';

Protect::check(['ADMIN','GESTOR','ANALISTA','OPERACOES']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('ID inválido');
}

require_once __DIR__ . '/../../app/Core/Database.php';

$db = Database::getConnection();

$stmt = $db->prepare("SELECT numero FROM alertas WHERE id = ?");
$stmt->execute([$id]);
$numeroAlerta = $stmt->fetchColumn() ?? '—';


/* =========================
   REGISTRA HISTÓRICO (1x)
========================= */
HistoricoService::registrar(
    $_SESSION['usuario']['id'],
    $_SESSION['usuario']['nome'],
    'BAIXAR_PDF',
    'Baixou PDF do alerta',
    "Alerta nº {$numeroAlerta} (ID {$id})"
);


/* =========================
   REDIRECIONA PARA PDF REAL
========================= */
header("Location: /pages/alertas/pdf.php?id={$id}&download=1");
exit;
