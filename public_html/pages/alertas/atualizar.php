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
$id = (int) ($_POST['id'] ?? 0);

function redirectWithFormError(string $path, string $message, array $extraParams = []): void
{
    $params = array_merge($extraParams, ['erro' => $message]);
    $query = http_build_query($params);
    $location = $path . ($query !== '' ? '?' . $query : '');
    header('Location: ' . $location);
    exit;
}

function redirectToEditarWithError(int $id, string $message): void
{
    if ($id > 0) {
        redirectWithFormError('editar.php', $message, ['id' => $id]);
    }

    redirectWithFormError('listar.php', $message);
}

try {
    if ($id <= 0) {
        redirectWithFormError('listar.php', 'ID invalido para edicao do alerta.');
    }

    $stmtDados = $db->prepare("
        SELECT numero, informacoes, area_origem, kml_arquivo
        FROM alertas
        WHERE id = ?
    ");
    $stmtDados->execute([$id]);
    $dadosAtuais = $stmtDados->fetch(PDO::FETCH_ASSOC);

    if (!$dadosAtuais) {
        redirectWithFormError('listar.php', 'Alerta nao encontrado para edicao.');
    }

    $numeroAlerta = $dadosAtuais['numero'];
    $imagemAtual = $dadosAtuais['informacoes'];
    $areaOrigemAtual = $dadosAtuais['area_origem'];
    $kmlArquivo = $dadosAtuais['kml_arquivo'];

    $db->beginTransaction();

    $tipo_evento     = AlertaFormHelper::validateTipoEvento((string) ($_POST['tipo_evento'] ?? ''));
    $nivel_gravidade = AlertaFormHelper::validateNivelGravidade((string) ($_POST['nivel_gravidade'] ?? ''));
    $riscos          = AlertaFormHelper::validateTexto((string) ($_POST['riscos'] ?? ''), 'Riscos', AlertaFormHelper::RISCOS_MAX);
    $recomendacoes   = AlertaFormHelper::validateTexto((string) ($_POST['recomendacoes'] ?? ''), 'Recomendacoes', AlertaFormHelper::RECOMENDACOES_MAX);
    $geoNormalizado  = AlertaFormHelper::normalizeAreaGeojson((string) ($_POST['area_geojson'] ?? ''));
    $area_geojson    = AlertaFormHelper::encodeAreaGeojson($geoNormalizado);
    $fonte           = AlertaFormHelper::validateFonte((string) ($_POST['fonte'] ?? ''));
    $data_alerta     = AlertaFormHelper::validateDate((string) ($_POST['data_alerta'] ?? ''), 'Data do alerta');
    $inicio_alerta   = AlertaFormHelper::validateDateTimeLocal($_POST['inicio_alerta'] ?? null, 'Inicio da vigencia', false);
    $fim_alerta      = AlertaFormHelper::validateDateTimeLocal($_POST['fim_alerta'] ?? null, 'Fim da vigencia', false);
    $areaOrigem      = AlertaFormHelper::normalizeAreaOrigem($_POST['area_origem'] ?? $areaOrigemAtual);

    AlertaFormHelper::validatePeriodo($inicio_alerta, $fim_alerta);

    if (!empty($_FILES['kml']['tmp_name'])) {
        $novoNome = UploadHelper::storeKml(
            $_FILES['kml'],
            __DIR__ . '/../../uploads/kml',
            'alerta_'
        );

        $novoKmlArquivo = '/uploads/kml/' . $novoNome;

        if (!empty($kmlArquivo) && $kmlArquivo !== $novoKmlArquivo) {
            $kmlAntigo = __DIR__ . '/../../' . ltrim($kmlArquivo, '/');

            if (is_file($kmlAntigo)) {
                unlink($kmlAntigo);
            }
        }

        $kmlArquivo = $novoKmlArquivo;
    }

    if ($areaOrigem !== 'KML') {
        if (!empty($kmlArquivo)) {
            $kmlAntigo = __DIR__ . '/../../' . ltrim($kmlArquivo, '/');

            if (is_file($kmlAntigo)) {
                unlink($kmlAntigo);
            }
        }

        $kmlArquivo = null;
    }

    $informacoes = $imagemAtual;

    if (!empty($_FILES['informacoes']['tmp_name'])) {
        $novoNome = UploadHelper::storeImage(
            $_FILES['informacoes'],
            __DIR__ . '/../../uploads/informacoes',
            'info_'
        );

        if (!empty($imagemAtual)) {
            $antiga = __DIR__ . '/../../' . ltrim($imagemAtual, '/');

            if (is_file($antiga)) {
                unlink($antiga);
            }
        }

        $informacoes = '/uploads/informacoes/' . $novoNome;
    }

    $stmt = $db->prepare("
        UPDATE alertas SET
            fonte = :fonte,
            tipo_evento = :tipo,
            nivel_gravidade = :gravidade,
            riscos = :riscos,
            recomendacoes = :recomendacoes,
            informacoes = :informacoes,
            area_geojson = :geojson,
            area_origem = :origem,
            kml_arquivo = :kml,
            imagem_mapa = NULL,
            alerta_enviado_compdec = 0,
            data_envio_compdec = NULL,
            data_alerta = :data_alerta,
            inicio_alerta = :inicio_alerta,
            fim_alerta = :fim_alerta
        WHERE id = :id
    ");

    $stmt->execute([
        ':fonte' => $fonte,
        ':tipo' => $tipo_evento,
        ':gravidade' => $nivel_gravidade,
        ':riscos' => $riscos,
        ':recomendacoes' => $recomendacoes,
        ':informacoes' => $informacoes,
        ':geojson' => $area_geojson,
        ':origem' => $areaOrigem,
        ':kml' => $kmlArquivo,
        ':data_alerta' => $data_alerta,
        ':inicio_alerta' => $inicio_alerta,
        ':fim_alerta' => $fim_alerta,
        ':id' => $id,
    ]);

    $db->prepare("DELETE FROM alerta_municipios WHERE alerta_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM alerta_regioes WHERE alerta_id = ?")->execute([$id]);

    $municipios = TerritorioService::municipiosAfetados($geoNormalizado);
    $regioes = TerritorioService::regioesAfetadas($municipios);

    $stmtMun = $db->prepare("
        INSERT INTO alerta_municipios
        (alerta_id, municipio_codigo, municipio_nome)
        VALUES (?, ?, ?)
    ");

    foreach ($municipios as $municipio) {
        $stmtMun->execute([$id, $municipio['codigo'], $municipio['nome']]);
    }

    $stmtReg = $db->prepare("
        INSERT INTO alerta_regioes
        (alerta_id, regiao_integracao)
        VALUES (?, ?)
    ");

    foreach (array_keys($regioes) as $regiao) {
        $stmtReg->execute([$id, $regiao]);
    }

    $db->commit();

    HistoricoService::registrar(
        $_SESSION['usuario']['id'],
        $_SESSION['usuario']['nome'],
        'EDITAR_ALERTA',
        'Editou informacoes do alerta',
        "Alerta n {$numeroAlerta} (ID {$id})"
    );

    header('Location: listar.php?atualizado=1');
    exit;

} catch (RuntimeException $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    redirectToEditarWithError($id, $e->getMessage());

} catch (Throwable $e) {

    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log('[ALERTA_ATUALIZAR] ' . $e->getMessage());
    redirectToEditarWithError($id, 'Erro interno ao atualizar alerta. Tente novamente.');
}
