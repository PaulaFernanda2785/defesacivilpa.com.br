<?php
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Helpers/SecurityHeaders.php';

SecurityHeaders::applyJson(1800, true);

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(405);
        header('Allow: GET');
        echo json_encode([
            'erro' => 'Metodo nao permitido.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $regiao = trim((string) ($_GET['regiao'] ?? ''));

    if ($regiao === '') {
        echo json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (strlen($regiao) > 120) {
        http_response_code(422);
        echo json_encode([
            'erro' => 'Regiao invalida.'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $db = Database::getConnection();

    $stmt = $db->prepare("
        SELECT DISTINCT municipio
        FROM municipios_regioes_pa
        WHERE TRIM(UPPER(regiao_integracao)) = TRIM(UPPER(?))
        ORDER BY municipio
    ");

    $stmt->execute([$regiao]);

    $municipios = array_values(array_filter(array_map(static function ($municipio) {
        return trim((string) $municipio);
    }, $stmt->fetchAll(PDO::FETCH_COLUMN)), static fn ($municipio) => $municipio !== ''));

    echo json_encode($municipios, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

} catch (Throwable $e) {
    error_log('[ajax_municipios] ' . $e->getMessage());

    http_response_code(500);
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo json_encode([
        'erro' => 'Erro interno ao carregar municipios.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
