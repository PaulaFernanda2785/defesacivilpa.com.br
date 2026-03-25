<?php
require_once __DIR__ . '/../../../app/Core/Database.php';
require_once __DIR__ . '/../../../app/Helpers/SecurityHeaders.php';

SecurityHeaders::applyJson(300, true);

$pdo = Database::getConnection();


$tipo = $_GET['tipo'] ?? null;

try {

    // ===============================
    // ANOS
    // ===============================
    if ($tipo === 'anos') {

        $sql = "
            SELECT DISTINCT YEAR(data_alerta) AS ano
            FROM alertas
            ORDER BY ano DESC
        ";

        echo json_encode(
            $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN)
        );
        exit;
    }

    // ===============================
    // REGIÕES
    // ===============================
    if ($tipo === 'regioes') {

        $sql = "
            SELECT DISTINCT regiao_integracao
            FROM municipios_regioes_pa
            WHERE regiao_integracao IS NOT NULL
              AND regiao_integracao <> ''
              AND regiao_integracao <> 'regiao_integracao'
            ORDER BY regiao_integracao
        ";


        echo json_encode(
            $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN)
        );
        exit;
    }

    // ===============================
    // MUNICÍPIOS POR REGIÃO
    // ===============================
    if ($tipo === 'municipios') {

        $regiao = $_GET['regiao'] ?? '';

        $sql = "
            SELECT municipio
            FROM municipios_regioes_pa
            WHERE regiao_integracao = :regiao
            ORDER BY municipio
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['regiao' => $regiao]);

        echo json_encode(
            $stmt->fetchAll(PDO::FETCH_COLUMN)
        );
        exit;
    }

    echo json_encode([]);

} catch (Exception $e) {
    error_log('[filtros_base] ' . $e->getMessage());

    echo json_encode([
        'erro' => true,
        'msg'  => 'Nao foi possivel carregar os filtros agora.'
    ]);

}
