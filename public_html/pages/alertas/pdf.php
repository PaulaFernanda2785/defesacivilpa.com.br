<?php
require_once __DIR__ . '/../../app/Core/Session.php';
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Helpers/SecurityHeaders.php';
require_once __DIR__ . '/../../app/Services/PdfService.php';

Session::start();
SecurityHeaders::applyDownload();

$usuario = $_SESSION['usuario'] ?? null;
$perfisPermitidos = ['ADMIN', 'GESTOR', 'ANALISTA', 'OPERACOES'];
$acessoInterno = is_array($usuario) && in_array((string) ($usuario['perfil'] ?? ''), $perfisPermitidos, true);

$db = Database::getConnection();

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    die('Alerta invalido.');
}

$sqlAlerta = "SELECT * FROM alertas WHERE id = :id";

if (!$acessoInterno) {
    $sqlAlerta .= " AND status = 'ATIVO'";
}

$stmt = $db->prepare($sqlAlerta);
$stmt->execute([':id' => $id]);
$alerta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$alerta) {
    http_response_code(404);
    die($acessoInterno ? 'Alerta nao encontrado.' : 'Alerta ativo nao encontrado.');
}

$mapaImagem = !empty($alerta['imagem_mapa'])
    ? (string) $alerta['imagem_mapa']
    : null;

$stmtMunicipios = $db->prepare("
    SELECT
        mr.regiao_integracao,
        am.municipio_nome
    FROM alerta_municipios am
    JOIN municipios_regioes_pa mr
        ON mr.cod_ibge = am.municipio_codigo
    WHERE am.alerta_id = :alerta_id
    ORDER BY mr.regiao_integracao, am.municipio_nome
");
$stmtMunicipios->execute([':alerta_id' => $id]);

$municipiosPorRegiao = [];

foreach ($stmtMunicipios->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $regiao = trim((string) ($row['regiao_integracao'] ?? 'Sem regiao'));
    $municipio = trim((string) ($row['municipio_nome'] ?? ''));

    if ($municipio === '') {
        continue;
    }

    if (!isset($municipiosPorRegiao[$regiao])) {
        $municipiosPorRegiao[$regiao] = [];
    }

    $municipiosPorRegiao[$regiao][] = $municipio;
}

PdfService::gerarAlerta(
    $alerta,
    $municipiosPorRegiao,
    $mapaImagem,
    !empty($_GET['download']) ? 'download' : 'stream'
);
