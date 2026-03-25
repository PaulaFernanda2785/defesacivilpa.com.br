<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Services/HistoricoService.php';

Protect::check(['ADMIN']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Metodo nao permitido.');
}

$db = Database::getConnection();
$id = (int) ($_POST['id'] ?? 0);
$acao = $_POST['acao'] ?? '';

if ($id <= 0 || !in_array($acao, ['ativar', 'inativar'], true)) {
    header('Location: listar.php?status=erro');
    exit;
}

$usuarioLogado = $_SESSION['usuario'];

$stmt = $db->prepare("
    SELECT nome, email, status
    FROM usuarios
    WHERE id = ?
");
$stmt->execute([$id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    header('Location: listar.php?status=nao_encontrado');
    exit;
}

if ((int) $usuarioLogado['id'] === $id) {
    die('Voce nao pode alterar o proprio status.');
}

$novoStatus = $acao === 'ativar' ? 'ATIVO' : 'INATIVO';

$stmt = $db->prepare("
    UPDATE usuarios
    SET status = ?
    WHERE id = ?
");
$stmt->execute([$novoStatus, $id]);

HistoricoService::registrar(
    (int) $usuarioLogado['id'],
    (string) $usuarioLogado['nome'],
    'ALTERAR_STATUS_USUARIO',
    'Alterou o status de um usuario do sistema',
    sprintf(
        'Usuario ID %d | Nome: %s | E-mail: %s | Status anterior: %s | Novo status: %s',
        $id,
        (string) ($usuario['nome'] ?? '-'),
        (string) ($usuario['email'] ?? '-'),
        (string) ($usuario['status'] ?? '-'),
        $novoStatus
    )
);

header('Location: listar.php?status=ok');
exit;
