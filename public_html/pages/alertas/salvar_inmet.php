<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Services/InmetService.php';
require_once __DIR__ . '/../../app/Services/TerritorioService.php';
require_once __DIR__ . '/../../app/Services/AlertaService.php';
require_once __DIR__ . '/../../app/Services/HistoricoService.php';

Protect::check(['ADMIN','GESTOR','ANALISTA']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: importar_inmet.php');
    exit;
}

$db = Database::getConnection();

/* =====================================================
   1) URL DO INMET
===================================================== */
$inmetUrl = trim($_POST['inmet_url'] ?? '');
if ($inmetUrl === '') {
    die('URL do INMET não informada.');
}

/* =====================================================
   2) EXTRAI ID DO INMET (OBRIGATÓRIO)
===================================================== */
if (!preg_match('/\/(\d+)$/', $inmetUrl, $m)) {
    die('Não foi possível identificar o ID do alerta do INMET.');
}
$inmet_id = $m[1];

/* =====================================================
   3) BLOQUEIO DE DUPLICIDADE (ANTES DE TUDO)
===================================================== */
$stmtCheck = $db->prepare("
    SELECT numero
    FROM alertas
    WHERE inmet_id = ?
    LIMIT 1
");
$stmtCheck->execute([$inmet_id]);

$existente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if ($existente) {
    header(
        'Location: listar.php?erro=inmet_duplicado&numero=' .
        urlencode($existente['numero'])
    );
    exit;
}

/* =====================================================
   4) IMPORTA DADOS DO INMET
===================================================== */
try {
    $dados = InmetService::importarPorUrl($inmetUrl);
} catch (Throwable $e) {
    http_response_code(422);
    die(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

if (empty($dados['area_geojson'])) {
    die('Área geográfica não encontrada.');
}

if (empty($dados['data_alerta'])) {
    die('Data oficial do alerta INMET não identificada.');
}

/* =====================================================
   5) NORMALIZA DATAS
===================================================== */
$dataAlerta   = date('Y-m-d H:i:s', strtotime($dados['data_alerta']));
$inicioAlerta = !empty($dados['inicio_alerta'])
    ? date('Y-m-d H:i:s', strtotime($dados['inicio_alerta']))
    : null;

$fimAlerta = !empty($dados['fim_alerta'])
    ? date('Y-m-d H:i:s', strtotime($dados['fim_alerta']))
    : null;

/* =====================================================
   6) TRANSAÇÃO
===================================================== */
$db->beginTransaction();

try {

    /* Numeração correta */
    $numeroAlerta = AlertaService::gerarNumero($dataAlerta);

    /* INSERT ALERTA */
    $stmt = $db->prepare("
        INSERT INTO alertas (
            numero,
            fonte,
            inmet_id,
            inmet_url,
            tipo_evento,
            nivel_gravidade,
            data_alerta,
            inicio_alerta,
            fim_alerta,
            riscos,
            recomendacoes,
            area_geojson,
            status,
            criado_em
        ) VALUES (
            :numero,
            'INMET',
            :inmet_id,
            :url,
            :tipo,
            :gravidade,
            :data_alerta,
            :inicio,
            :fim,
            :riscos,
            :recomendacoes,
            :geojson,
            'ATIVO',
            :criado_em
        )
    ");

    $stmt->execute([
        ':numero'        => $numeroAlerta,
        ':inmet_id'      => $inmet_id,
        ':url'           => $inmetUrl,
        ':tipo'          => $dados['tipo_evento'],
        ':gravidade'     => $dados['nivel_gravidade'],
        ':data_alerta'   => $dataAlerta,
        ':inicio'        => $inicioAlerta,
        ':fim'           => $fimAlerta,
        ':riscos'        => $dados['riscos'],
        ':recomendacoes' => $dados['recomendacoes'],
        ':geojson'       => json_encode($dados['area_geojson']),
        ':criado_em'     => TimeHelper::now(),
    ]);

    $alertaId = $db->lastInsertId();

    /* Municípios */
    $municipios = TerritorioService::municipiosAfetados($dados['area_geojson']);
    $stmtMun = $db->prepare("
        INSERT INTO alerta_municipios
        (alerta_id, municipio_codigo, municipio_nome)
        VALUES (:alerta, :codigo, :nome)
    ");

    foreach ($municipios as $m) {
        $stmtMun->execute([
            ':alerta' => $alertaId,
            ':codigo' => $m['codigo'],
            ':nome'   => $m['nome']
        ]);
    }

    /* Regiões */
    $regioes = TerritorioService::regioesAfetadas($municipios);
    $stmtReg = $db->prepare("
        INSERT INTO alerta_regioes
        (alerta_id, regiao_integracao)
        VALUES (:alerta, :regiao)
    ");

    foreach (array_keys($regioes) as $regiao) {
        $stmtReg->execute([
            ':alerta' => $alertaId,
            ':regiao' => $regiao
        ]);
    }

    $db->commit();
    
    /*===============================
       HISTÓRICO – IMPORTAÇÃO INMET
    ================================ */
    HistoricoService::registrar(
        $_SESSION['usuario']['id'],
        $_SESSION['usuario']['nome'],
        'IMPORTAR_INMET',
        'Importou alerta do INMET',
        "Alerta nº {$numeroAlerta} (ID {$alertaId}) | URL: {$inmetUrl}"
    );


    header('Location: listar.php?inmet=1');
    exit;

} catch (Throwable $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log('[IMPORTAR_INMET] ' . $e->getMessage());

    http_response_code(500);
    die('Erro ao salvar alerta do INMET.');
}

