<?php
require_once __DIR__ . '/../../app/Core/Protect.php';

Protect::check(['ADMIN','GESTOR','ANALISTA','OPERACOES']);

header('Content-Type: application/json; charset=utf-8');

/* =========================================
   CONFIGURAÇÃO
========================================= */
$urlCsv = 'https://docs.google.com/spreadsheets/d/e/2PACX-1vQK16_yCETLrGVH3EfM9yMrwirNW2ZkX6t1nwTfPaKzjQppBbKok1J9zEob9c65xJlWC44Qs4QeeLCU/pub?gid=0&single=true&output=csv';

/* =========================================
   FUNÇÃO: CSV → ARRAY
========================================= */
function normalizarChave(string $str): string
{
    $str = mb_strtolower(trim($str), 'UTF-8');
    $str = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
    $str = preg_replace('/[^a-z0-9]+/', '_', $str);
    return trim($str, '_');
}

function csvParaArray(string $csv): array
{
    $linhas = array_map('str_getcsv', explode("\n", $csv));
    $cabecalhoOriginal = array_shift($linhas);

    $cabecalho = array_map(
        fn($c) => normalizarChave($c),
        $cabecalhoOriginal
    );

    $dados = [];

    foreach ($linhas as $linha) {
        if (count($linha) !== count($cabecalho)) {
            continue;
        }

        $dados[] = array_combine($cabecalho, $linha);
    }

    return $dados;
}


/* =========================================
   CARREGA CSV
========================================= */
$csv = @file_get_contents($urlCsv);

if (!$csv) {
    http_response_code(500);
    echo json_encode([
        'erro' => 'Falha ao acessar a planilha COMPDEC'
    ]);
    exit;
}

/* =========================================
   NORMALIZA DADOS
========================================= */
$linhas = csvParaArray($csv);
$resultado = [];

foreach ($linhas as $l) {

    if (empty($l['municipio'])) {
        continue;
    }

    $resultado[] = [
        'regiao_integracao' => trim($l['regiao_integracao'] ?? ''),
        'municipio'         => trim($l['municipio']),
        'tem_compdec'       => strtolower(trim($l['tem_compdec'] ?? '')) === 'sim',
        'prefeito'          => trim($l['prefeito'] ?? ''),
        'ubm'               => trim($l['ubm'] ?? ''),
        'coordenador'       => trim($l['coordenador'] ?? ''),
        'telefone'          => trim($l['telefone'] ?? ''),
        'email'             => trim($l['email'] ?? ''),
        'endereco'          => trim($l['endereco'] ?? ''),
        'data_atualizacao'  => trim($l['data_atualizacao'] ?? ''),
        'latitude'          => isset($l['latitude']) ? (float)$l['latitude'] : null,
        'longitude'         => isset($l['longitude']) ? (float)$l['longitude'] : null,
    ];
}

/* =========================================
   SAÍDA
========================================= */
echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
