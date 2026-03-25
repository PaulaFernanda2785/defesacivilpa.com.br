<?php
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Services/HistoricoService.php';

Session::start();

$usuario = $_SESSION['usuario'] ?? null;

if (is_array($usuario) && !empty($usuario['id']) && !empty($usuario['nome'])) {
    HistoricoService::registrar(
        (int) $usuario['id'],
        (string) $usuario['nome'],
        'LOGOUT_SISTEMA',
        'Realizou logout do sistema',
        'Origem: menu principal'
    );
}

Session::destroy();

$motivo = trim((string) ($_GET['motivo'] ?? ''));
$redirect = '/index.php';

if ($motivo === 'inatividade') {
    $redirect .= '?motivo=inatividade';
}

header('Location: ' . $redirect);
exit;
