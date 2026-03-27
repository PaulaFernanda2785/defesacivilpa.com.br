<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Helpers/AlertaFormHelper.php';
require_once __DIR__ . '/../../app/Helpers/UploadHelper.php';
require_once __DIR__ . '/../../app/Services/TerritorioService.php';
require_once __DIR__ . '/../../app/Services/HistoricoService.php';

Protect::check(['ADMIN','GESTOR','ANALISTA']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php');
    exit;
}

$db = Database::getConnection();

try {
    $db->beginTransaction();

    $tipo_evento     = AlertaFormHelper::validateTipoEvento((string) ($_POST['tipo_evento'] ?? ''));
    $nivel_gravidade = AlertaFormHelper::validateNivelGravidade((string) ($_POST['nivel_gravidade'] ?? ''));
    $data_alerta     = AlertaFormHelper::validateDate((string) ($_POST['data_alerta'] ?? ''), 'Data do alerta');
    $inicio_alerta   = AlertaFormHelper::validateDateTimeLocal($_POST['inicio_alerta'] ?? null, 'Inicio da vigencia');
    $fim_alerta      = AlertaFormHelper::validateDateTimeLocal($_POST['fim_alerta'] ?? null, 'Fim da vigencia');
    $fonte           = AlertaFormHelper::validateFonte((string) ($_POST['fonte'] ?? ''));
    $riscos          = AlertaFormHelper::validateTexto((string) ($_POST['riscos'] ?? ''), 'Riscos', AlertaFormHelper::RISCOS_MAX);
    $recomendacoes   = AlertaFormHelper::validateTexto((string) ($_POST['recomendacoes'] ?? ''), 'Recomendacoes', AlertaFormHelper::RECOMENDACOES_MAX);
    $geoNormalizado  = AlertaFormHelper::normalizeAreaGeojson((string) ($_POST['area_geojson'] ?? ''));
    $area_geojson    = AlertaFormHelper::encodeAreaGeojson($geoNormalizado);

    AlertaFormHelper::validatePeriodo($inicio_alerta, $fim_alerta);

    $area_origem = AlertaFormHelper::normalizeAreaOrigem($_POST['area_origem'] ?? 'DESENHO');
    $kml_arquivo = null;
    $informacoes = null;

    if (!empty($_FILES['informacoes']['tmp_name'])) {
        $novoNome = UploadHelper::storeImage(
            $_FILES['informacoes'],
            __DIR__ . '/../../uploads/informacoes',
            'info_'
        );

        $informacoes = '/uploads/informacoes/' . $novoNome;
    }

    if (!empty($_FILES['kml']['tmp_name'])) {
        $novoNome = UploadHelper::storeKml(
            $_FILES['kml'],
            __DIR__ . '/../../uploads/kml',
            'alerta_'
        );

        $kmlTemporario = '/uploads/kml/' . $novoNome;

        if ($area_origem === 'KML') {
            $kml_arquivo = $kmlTemporario;
        } else {
            $arquivoDescartado = __DIR__ . '/../../' . ltrim($kmlTemporario, '/');

            if (is_file($arquivoDescartado)) {
                unlink($arquivoDescartado);
            }
        }
    }

    $ano = date('Y', strtotime($data_alerta));

    $stmt = $db->prepare("
        SELECT MAX(numero)
        FROM alertas
        WHERE YEAR(data_alerta) = ?
    ");
    $stmt->execute([$ano]);
    $ultimo = $stmt->fetchColumn();

    $novoSeq = $ultimo ? ((int) explode('/', $ultimo)[0] + 1) : 1;
    $numero_alerta = str_pad($novoSeq, 3, '0', STR_PAD_LEFT) . '/' . $ano;

    $singleClickValidated = Csrf::currentRequestIsValidated();

    if (!$singleClickValidated) {
        throw new RuntimeException('Falha ao validar envio unico da requisicao. Recarregue a pagina e tente novamente.');
    }

    if ($singleClickValidated) {
        $stmt = $db->prepare("
            INSERT INTO alertas (
                numero,
                status,
                fonte,
                tipo_evento,
                nivel_gravidade,
                data_alerta,
                inicio_alerta,
                fim_alerta,
                riscos,
                recomendacoes,
                informacoes,
                area_geojson,
                area_origem,
                kml_arquivo,
                criado_em
            ) VALUES (
                :numero,
                'ATIVO',
                :fonte,
                :tipo_evento,
                :nivel_gravidade,
                :data_alerta,
                :inicio_alerta,
                :fim_alerta,
                :riscos,
                :recomendacoes,
                :informacoes,
                :area_geojson,
                :area_origem,
                :kml_arquivo,
                :criado_em
            )
        ");

        $stmt->execute([
            ':numero' => $numero_alerta,
            ':fonte' => $fonte,
            ':tipo_evento' => $tipo_evento,
            ':nivel_gravidade' => $nivel_gravidade,
            ':data_alerta' => $data_alerta,
            ':inicio_alerta' => $inicio_alerta,
            ':fim_alerta' => $fim_alerta,
            ':riscos' => $riscos,
            ':recomendacoes' => $recomendacoes,
            ':informacoes' => $informacoes,
            ':area_geojson' => $area_geojson,
            ':area_origem' => $area_origem,
            ':kml_arquivo' => $kml_arquivo,
            ':criado_em' => TimeHelper::now(),
        ]);
    }

    $alertaId = $db->lastInsertId();

    $municipios = TerritorioService::municipiosAfetados($geoNormalizado);
    $regioes = TerritorioService::regioesAfetadas($municipios);

    foreach ($municipios as $municipio) {
        $db->prepare("
            INSERT INTO alerta_municipios
            (alerta_id, municipio_codigo, municipio_nome)
            VALUES (?, ?, ?)
        ")->execute([$alertaId, $municipio['codigo'], $municipio['nome']]);
    }

    foreach (array_keys($regioes) as $regiao) {
        $db->prepare("
            INSERT INTO alerta_regioes
            (alerta_id, regiao_integracao)
            VALUES (?, ?)
        ")->execute([$alertaId, $regiao]);
    }

    $db->commit();

    HistoricoService::registrar(
        $_SESSION['usuario']['id'],
        $_SESSION['usuario']['nome'],
        'CADASTRAR_ALERTA',
        'Cadastrou novo alerta',
        "Alerta n {$numero_alerta} (ID {$alertaId})"
    );

    header('Location: listar.php?salvo=1');
    exit;

} catch (RuntimeException $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    http_response_code(422);
    die($e->getMessage());

} catch (Throwable $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log('[SALVAR_ALERTA] ' . $e->getMessage());
    http_response_code(500);
    die('Erro interno ao salvar alerta.');
}
