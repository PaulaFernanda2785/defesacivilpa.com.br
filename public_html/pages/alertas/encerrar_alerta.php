<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Services/HistoricoService.php';

Protect::check(['ADMIN', 'GESTOR']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php');
    exit;
}

$db = Database::getConnection();

try {
    /* =========================
       DADOS DE ENTRADA
    ========================= */
    $id = (int) ($_POST['id'] ?? 0);
    $acao = $_POST['acao'] ?? 'encerrar';

    if ($id <= 0) {
        die('ID do alerta invalido.');
    }

    /* =========================
       STATUS DESTINO
    ========================= */
    $statusDestino = match ($acao) {
        'cancelar' => 'CANCELADO',
        'ativar' => 'ATIVO',
        default => 'ENCERRADO',
    };

    /* =========================
       USUARIO LOGADO
    ========================= */
    $usuario = $_SESSION['usuario'] ?? null;

    if (!$usuario || empty($usuario['id'])) {
        die('Usuario nao autenticado.');
    }

    if ($statusDestino === 'ATIVO' && (($usuario['perfil'] ?? '') !== 'ADMIN')) {
        http_response_code(403);
        die('Apenas administradores podem ativar alertas encerrados.');
    }

    $codigoAcao = match ($statusDestino) {
        'CANCELADO' => 'CANCELAR_ALERTA',
        'ATIVO' => 'ATIVAR_ALERTA',
        default => 'ENCERRAR_ALERTA',
    };

    $descricaoAcao = match ($statusDestino) {
        'CANCELADO' => 'Cancelou alerta',
        'ATIVO' => 'Ativou alerta encerrado',
        default => 'Encerrou alerta',
    };

    $db->beginTransaction();
    $agoraLocal = TimeHelper::now();

    /* =========================
       BUSCA ALERTA
    ========================= */
    $stmtCheck = $db->prepare("
        SELECT numero, status
        FROM alertas
        WHERE id = ?
        FOR UPDATE
    ");
    $stmtCheck->execute([$id]);
    $alerta = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$alerta) {
        die('Alerta nao encontrado.');
    }

    if ($statusDestino === 'ATIVO') {
        if ($alerta['status'] !== 'ENCERRADO') {
            die('Somente alertas encerrados podem ser ativados.');
        }
    } elseif ($alerta['status'] !== 'ATIVO') {
        die('Este alerta ja foi encerrado ou cancelado.');
    }

    /* =========================
       ATUALIZA ALERTA
    ========================= */
    if ($statusDestino === 'CANCELADO') {
        $motivo = trim($_POST['motivo_cancelamento'] ?? '');

        if ($motivo === '') {
            die('Motivo do cancelamento e obrigatorio.');
        }

        $stmt = $db->prepare("
            UPDATE alertas
            SET status = 'CANCELADO',
                motivo_cancelamento = :motivo,
                data_cancelamento = :data_cancelamento,
                usuario_cancelamento = :usuario
            WHERE id = :id
        ");

        $stmt->execute([
            ':motivo' => $motivo,
            ':data_cancelamento' => $agoraLocal,
            ':usuario' => $usuario['id'],
            ':id' => $id,
        ]);
    } elseif ($statusDestino === 'ENCERRADO') {
        $stmt = $db->prepare("
            UPDATE alertas
            SET status = 'ENCERRADO',
                data_encerramento = :data_encerramento,
                usuario_id = :usuario
            WHERE id = :id
        ");

        $stmt->execute([
            ':data_encerramento' => $agoraLocal,
            ':usuario' => $usuario['id'],
            ':id' => $id,
        ]);
    } else {
        $stmt = $db->prepare("
            UPDATE alertas
            SET status = 'ATIVO',
                data_encerramento = NULL
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $id,
        ]);
    }

    $db->commit();

    /* =========================
       HISTORICO (APOS COMMIT)
    ========================= */
    HistoricoService::registrar(
        $usuario['id'],
        $usuario['nome'],
        $codigoAcao,
        $descricaoAcao,
        "Alerta n {$alerta['numero']} (ID {$id})"
    );

    header('Location: listar.php?acao=' . strtolower($statusDestino));
    exit;
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log('[ENCERRAR_ALERTA] ' . $e->getMessage());
    http_response_code(500);
    die('Erro interno ao processar alerta.');
}
