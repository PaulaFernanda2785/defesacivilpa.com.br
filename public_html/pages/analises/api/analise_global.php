<?php
require_once __DIR__ . '/../../../app/Core/Database.php';
require_once __DIR__ . '/../../../app/Core/Session.php';
require_once __DIR__ . '/../../../app/Helpers/SecurityHeaders.php';
require_once __DIR__ . '/../../../app/Services/AnaliseGlobalService.php';
require_once __DIR__ . '/../../../app/Services/HistoricoService.php';

SecurityHeaders::applyJson();

Session::start();

$db = Database::getConnection();
$usuario = $_SESSION['usuario'] ?? null;

$filtro = [
    'ano'       => $_GET['ano']       ?? null,
    'mes'       => $_GET['mes']       ?? null,
    'regiao'    => $_GET['regiao']    ?? null,
    'municipio' => $_GET['municipio'] ?? null,
];


try {
    if (is_array($usuario) && !empty($usuario['id']) && !empty($usuario['nome'])) {
        $referencias = [];

        foreach (['ano', 'mes', 'regiao', 'municipio'] as $campo) {
            $valor = trim((string) ($filtro[$campo] ?? ''));
            if ($valor !== '') {
                $referencias[] = strtoupper($campo) . ': ' . $valor;
            }
        }

        HistoricoService::registrar(
            (int) $usuario['id'],
            (string) $usuario['nome'],
            'GERAR_RELATORIO_ANALITICO',
            'Gerou o relatorio analitico consolidado na tela de analises',
            $referencias !== [] ? implode(' | ', $referencias) : 'Sem filtros adicionais'
        );
    }

    $dados = AnaliseGlobalService::gerar($db, $filtro);

    echo json_encode($dados, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('[analise_global] ' . $e->getMessage());

    echo json_encode([
        'erro' => true,
        'msg'  => 'Nao foi possivel gerar o relatorio analitico agora.'
    ], JSON_UNESCAPED_UNICODE);
}

