<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Services/HistoricoService.php';

Protect::check(['ADMIN','GESTOR','ANALISTA','OPERACOES']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: listar.php');
    exit;
}

$db = Database::getConnection();
$usuarioLogado = $_SESSION['usuario'];

$id = (int) ($_POST['id'] ?? 0);
$senha = $_POST['senha'] ?? '';
$confirmacao = $_POST['confirmacao'] ?? '';
$rotaRetornoErro = '/pages/usuarios/senha.php?' . http_build_query(['id' => $id]);

if ($id <= 0 || !$senha || !$confirmacao) {
    header('Location: ' . $rotaRetornoErro . '&erro=dados_invalidos');
    exit;
}

if ($senha !== $confirmacao) {
    header('Location: ' . $rotaRetornoErro . '&erro=confirmacao');
    exit;
}

if (strlen($senha) < 8) {
    header('Location: ' . $rotaRetornoErro . '&erro=senha_curta');
    exit;
}

if ($usuarioLogado['perfil'] !== 'ADMIN' && $usuarioLogado['id'] !== $id) {
    die('Acesso negado.');
}

$stmtUsuario = $db->prepare("
    SELECT nome, email
    FROM usuarios
    WHERE id = :id
    LIMIT 1
");
$stmtUsuario->execute([':id' => $id]);
$usuarioAlvo = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

if (!$usuarioAlvo) {
    die('Usuario nao encontrado.');
}

$hash = password_hash($senha, PASSWORD_DEFAULT);

$stmt = $db->prepare("
    UPDATE usuarios
    SET senha_hash = :senha
    WHERE id = :id
");

$stmt->execute([
    ':senha' => $hash,
    ':id' => $id,
]);

HistoricoService::registrar(
    (int) $usuarioLogado['id'],
    (string) $usuarioLogado['nome'],
    'ALTERAR_SENHA_USUARIO',
    'Alterou a senha de um usuario do sistema',
    sprintf(
        'Usuario ID %d | Nome: %s | E-mail: %s',
        $id,
        (string) ($usuarioAlvo['nome'] ?? '-'),
        (string) ($usuarioAlvo['email'] ?? '-')
    )
);

if ($usuarioLogado['perfil'] === 'ADMIN') {
    header('Location: listar.php?senha=ok');
} else {
    header('Location: /pages/painel.php?senha=ok');
}
exit;
