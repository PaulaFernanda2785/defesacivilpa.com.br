<?php


ob_start();

require_once __DIR__ . '/../../../app/Core/Protect.php';
require_once __DIR__ . '/../../../app/Core/Database.php';
require_once __DIR__ . '/../../../app/Helpers/SecurityHeaders.php';
require_once __DIR__ . '/../../../app/Services/AnaliseGlobalService.php';
require_once __DIR__ . '/../../../app/Services/RelatorioAnaliticoPdfService.php';
require_once __DIR__ . '/../../../app/Services/HistoricoService.php';

Protect::check(['ADMIN', 'GESTOR', 'ANALISTA', 'OPERACOES']);
SecurityHeaders::applyDownload();


$db = Database::getConnection();
$usuario = $_SESSION['usuario'] ?? null;

/* ===============================
   FILTROS PADRONIZADOS
=============================== */
$filtro = [
    'ano'       => $_GET['ano']       ?? null,
    'mes'       => $_GET['mes']       ?? null,
    'regiao'    => $_GET['regiao']    ?? null,
    'municipio' => $_GET['municipio'] ?? null,
];

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
        'BAIXAR_RELATORIO_ANALITICO',
        'Baixou o relatorio analitico consolidado em PDF',
        $referencias !== [] ? implode(' | ', $referencias) : 'Sem filtros adicionais'
    );
}

/* ===============================
   DADOS
=============================== */
$dados = AnaliseGlobalService::gerar($db, $filtro);

/* ===============================
   GERAR PDF
=============================== */
RelatorioAnaliticoPdfService::gerar($dados, $filtro);
exit;

