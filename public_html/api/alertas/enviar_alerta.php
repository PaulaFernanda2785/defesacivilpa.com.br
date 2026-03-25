<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Services/AlertaEnvioService.php';

try {

    Protect::check(['ADMIN','GESTOR','ANALISTA']);

    $usuario = $_SESSION['usuario'];
    $id = (int)($_POST['id'] ?? 0);

    $res = AlertaEnvioService::enviar($id, $usuario);

    echo json_encode([
        'ok'   => $res['ok'] ?? false,
        'data' => $res
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'ok'  => false,
        'msg' => $e->getMessage()
    ]);
}

