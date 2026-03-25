<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Helpers/UploadHelper.php';
require_once __DIR__ . '/../../app/Services/HistoricoService.php';

header('Content-Type: application/json; charset=utf-8');

Protect::check(['ADMIN','GESTOR','ANALISTA']);

$usuario = $_SESSION['usuario'] ?? null;

$data = json_decode(file_get_contents('php://input'), true);
$imagemBase64 = is_array($data) ? (string) ($data['imagem'] ?? $data['imagem_base64'] ?? '') : '';

if (!$data || empty($data['alerta_id']) || $imagemBase64 === '') {
    http_response_code(400);
    echo json_encode(['erro' => 'Dados invalidos.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$alertaId = (int) $data['alerta_id'];

if ($alertaId <= 0) {
    http_response_code(400);
    echo json_encode(['erro' => 'Alerta invalido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $imgData = UploadHelper::decodeBase64Png($imagemBase64);

    $dir = __DIR__ . '/../../storage/mapas';
    UploadHelper::ensureDirectory($dir);

    $arquivo = "/storage/mapas/alerta_{$alertaId}.png";
    $salvo = file_put_contents(__DIR__ . '/../../storage/mapas/alerta_' . $alertaId . '.png', $imgData);

    if ($salvo === false) {
        throw new RuntimeException('Nao foi possivel salvar a imagem do mapa.');
    }

    $db = Database::getConnection();
    $stmtNumero = $db->prepare("SELECT numero FROM alertas WHERE id = ?");
    $stmtNumero->execute([$alertaId]);
    $numeroAlerta = (string) ($stmtNumero->fetchColumn() ?? '');

    $stmt = $db->prepare("UPDATE alertas SET imagem_mapa = ? WHERE id = ?");
    $stmt->execute([$arquivo, $alertaId]);

    if (is_array($usuario) && !empty($usuario['id']) && !empty($usuario['nome'])) {
        HistoricoService::registrar(
            (int) $usuario['id'],
            (string) $usuario['nome'],
            'GERAR_MAPA_ALERTA',
            'Gerou a imagem do mapa do alerta para o PDF',
            $numeroAlerta !== ''
                ? "Alerta n {$numeroAlerta} (ID {$alertaId})"
                : "Alerta ID {$alertaId}"
        );
    }

    echo json_encode([
        'status' => 'ok',
        'arquivo' => $arquivo,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode([
        'erro' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
