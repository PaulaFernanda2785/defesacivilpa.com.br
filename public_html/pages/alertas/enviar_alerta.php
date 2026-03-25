<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Services/HistoricoService.php';
require_once __DIR__ . '/../../app/Services/EmailService.php';

header('Content-Type: application/json');

Protect::check(['ADMIN','GESTOR','ANALISTA']);

$usuario = $_SESSION['usuario'] ?? null;

if (!$usuario) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'Usuário não autenticado']);
    exit;
}

$alertaId = (int)($_POST['alerta_id'] ?? 0);

if ($alertaId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'erro' => 'ID do alerta inválido']);
    exit;
}

$db = Database::getConnection();

$stmt = $db->prepare("
    SELECT id, numero, tipo_evento, status
    FROM alertas
    WHERE id = :id
");
$stmt->execute([':id' => $alertaId]);

$alerta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$alerta) {
    echo json_encode(['ok' => false, 'erro' => 'Alerta não encontrado']);
    exit;
}

if ($alerta['status'] !== 'ATIVO') {
    echo json_encode(['ok' => false, 'erro' => 'Alerta não está ativo']);
    exit;
}

$stmt = $db->prepare("
    SELECT
        m.municipio,
        m.email,
        m.tem_compdec
    FROM municipios m
    INNER JOIN alertas_municipios am ON am.municipio_id = m.id
    WHERE am.alerta_id = :alerta
      AND m.tem_compdec = 'SIM'
      AND m.email IS NOT NULL
      AND m.email <> ''
");

$stmt->execute([':alerta' => $alertaId]);
$municipios = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$municipios) {
    echo json_encode([
        'ok' => false,
        'erro' => 'Nenhum município com COMPDEC e e-mail válido encontrado'
    ]);
    exit;
}

$emailService = new EmailService();
$agoraEnvio = TimeHelper::now();

$enviados = 0;

foreach ($municipios as $m) {

    $mensagemHtml = "
        <h2>Alerta da Defesa Civil</h2>
        <p><strong>Evento:</strong> {$alerta['tipo_evento']}</p>
        <p><strong>Número do Alerta:</strong> {$alerta['numero']}</p>
        <p><strong>Município:</strong> {$m['municipio']}</p>
        <p>Este é um alerta oficial da Defesa Civil do Estado do Pará.</p>
    ";

    $ok = $emailService->enviar(
        $m['email'],
        'Alerta da Defesa Civil – ' . $alerta['numero'],
        strip_tags($mensagemHtml),
        $mensagemHtml
    );

    if ($ok) {
        $enviados++;
    }
}

$db->prepare("
    UPDATE alertas
    SET alerta_enviado_compdec = 1,
        data_envio_compdec = :data_envio_compdec
    WHERE id = :id
")->execute([
    ':data_envio_compdec' => $agoraEnvio,
    ':id' => $alertaId
]);

HistoricoService::registrar(
    $usuario['id'],
    $usuario['nome'],
    'ENVIO_ALERTA',
    'Envio manual de alerta para COMPDEC',
    "Alerta {$alerta['numero']} enviado para {$enviados} municípios"
);

echo json_encode([
    'ok' => true,
    'mensagem' => 'Alerta enviado com sucesso',
    'enviados' => $enviados
]);
