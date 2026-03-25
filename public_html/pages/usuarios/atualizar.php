<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Services/HistoricoService.php';

Protect::check(['ADMIN']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php');
    exit;
}

$db = Database::getConnection();

$id     = (int) ($_POST['id'] ?? 0);
$nome   = trim($_POST['nome'] ?? '');
$email  = mb_strtolower(trim($_POST['email'] ?? ''));
$perfil = $_POST['perfil'] ?? '';
$status = $_POST['status'] ?? '';

if ($id <= 0 || !$nome || !$email || !$perfil || !$status) {
    die('Dados invalidos.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die('E-mail invalido.');
}

if (!in_array($perfil, ['ADMIN','GESTOR','ANALISTA','OPERACOES'], true)) {
    die('Perfil invalido.');
}

if (!in_array($status, ['ATIVO','INATIVO'], true)) {
    die('Status invalido.');
}

$usuarioLogado = $_SESSION['usuario'];

if ($usuarioLogado['id'] === $id && $perfil !== 'ADMIN') {
    die('O administrador nao pode remover o proprio perfil.');
}

if ($usuarioLogado['id'] === $id && $status !== 'ATIVO') {
    die('O administrador nao pode desativar o proprio usuario.');
}

$stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id <> ?");
$stmt->execute([$email, $id]);

if ($stmt->fetch()) {
    die('Ja existe outro usuario cadastrado com este e-mail.');
}

$stmt = $db->prepare("
    UPDATE usuarios SET
        nome = :nome,
        email = :email,
        perfil = :perfil,
        status = :status
    WHERE id = :id
");

$stmt->execute([
    ':nome' => $nome,
    ':email' => $email,
    ':perfil' => $perfil,
    ':status' => $status,
    ':id' => $id,
]);

HistoricoService::registrar(
    (int) $usuarioLogado['id'],
    (string) $usuarioLogado['nome'],
    'ATUALIZAR_USUARIO',
    'Atualizou os dados de um usuario do sistema',
    sprintf('Usuario ID %d | Nome: %s | E-mail: %s | Perfil: %s | Status: %s', $id, $nome, $email, $perfil, $status)
);

header('Location: listar.php?atualizado=1');
exit;
