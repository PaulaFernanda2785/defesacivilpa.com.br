<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Csrf.php';
require_once __DIR__ . '/../../app/Helpers/AlertaFormHelper.php';
require_once __DIR__ . '/../../app/Services/TerritorioService.php';

Protect::check(['ADMIN', 'GESTOR', 'ANALISTA']);

header('Content-Type: application/json; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'erro' => 'Metodo nao permitido.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

Csrf::validateRequestOrFail();

try {
    $payload = json_decode((string) file_get_contents('php://input'), true);

    if (!is_array($payload)) {
        throw new RuntimeException('Requisicao invalida.');
    }

    $areaGeojson = $payload['area_geojson'] ?? null;

    if (is_array($areaGeojson)) {
        $areaGeojson = json_encode($areaGeojson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    if (!is_string($areaGeojson) || trim($areaGeojson) === '') {
        throw new RuntimeException('Area geografica nao informada.');
    }

    $geoNormalizado = AlertaFormHelper::normalizeAreaGeojson($areaGeojson);
    $municipios = TerritorioService::municipiosAfetados($geoNormalizado);
    $regioes = TerritorioService::municipiosPorRegiao($municipios);

    $saida = [];

    foreach ($regioes as $regiao => $listaMunicipios) {
        $saida[] = [
            'regiao' => $regiao,
            'municipios' => $listaMunicipios,
        ];
    }

    echo json_encode([
        'ok' => true,
        'total_municipios' => count($municipios),
        'total_regioes' => count($saida),
        'regioes' => $saida,
    ], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'erro' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'erro' => 'Nao foi possivel processar o territorio informado.',
    ], JSON_UNESCAPED_UNICODE);
}
