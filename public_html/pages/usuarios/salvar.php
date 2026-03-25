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

$nome   = trim($_POST['nome'] ?? '');
$email  = mb_strtolower(trim($_POST['email'] ?? ''));
$senha  = $_POST['senha'] ?? '';
$perfil = $_POST['perfil'] ?? '';
$status = $_POST['status'] ?? 'ATIVO';

if (!$nome || !$email || !$senha || !$perfil) {
    die('Todos os campos obrigatorios devem ser preenchidos.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die('E-mail invalido.');
}

if (strlen($senha) < 8) {
    die('A senha precisa ter pelo menos 8 caracteres.');
}

if (!in_array($perfil, ['ADMIN','GESTOR','ANALISTA','OPERACOES'], true)) {
    die('Perfil invalido.');
}

if (!in_array($status, ['ATIVO','INATIVO'], true)) {
    die('Status invalido.');
}

$stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
$stmt->execute([$email]);

if ($stmt->fetch()) {
    die('Ja existe um usuario cadastrado com este e-mail.');
}

$senhaHash = password_hash($senha, PASSWORD_DEFAULT);

$stmt = $db->prepare("
    INSERT INTO usuarios
    (nome, email, senha_hash, perfil, status)
    VALUES (?, ?, ?, ?, ?)
");

$stmt->execute([
    $nome,
    $email,
    $senhaHash,
    $perfil,
    $status,
]);

$usuario = $_SESSION['usuario'] ?? null;

if (is_array($usuario) && !empty($usuario['id']) && !empty($usuario['nome'])) {
    HistoricoService::registrar(
        (int) $usuario['id'],
        (string) $usuario['nome'],
        'CADASTRAR_USUARIO',
        'Cadastrou um novo usuario no sistema',
        sprintf('Usuario: %s | E-mail: %s | Perfil: %s | Status: %s', $nome, $email, $perfil, $status)
    );
}

header('Location: listar.php?sucesso=1');
exit;
