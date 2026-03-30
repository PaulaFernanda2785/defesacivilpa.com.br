<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Helpers/SecurityHeaders.php';

Protect::check(['ADMIN', 'GESTOR', 'ANALISTA', 'OPERACOES']);
SecurityHeaders::applyJson(60, true);

$raizProjeto = dirname(__DIR__, 3);
$diretorioRelatorios = $raizProjeto . '/storage/reports/validacao_irp';
$tipo = trim((string) ($_GET['tipo'] ?? 'latest'));

if (!is_dir($diretorioRelatorios)) {
    http_response_code(404);
    echo json_encode([
        'ok' => false,
        'erro' => 'Diretorio de relatorios de validacao nao encontrado.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$arquivo = 'validacao_irp_latest.md';

if ($tipo === 'arquivo') {
    $arquivoSolicitado = trim((string) ($_GET['arquivo'] ?? ''));

    if (!preg_match('/^validacao_irp_\d{8}_\d{6}\.md$/', $arquivoSolicitado)) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'erro' => 'Nome de arquivo invalido.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $arquivo = $arquivoSolicitado;
} elseif ($tipo !== 'latest') {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'erro' => 'Tipo de consulta invalido. Use tipo=latest ou tipo=arquivo.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$caminhoAbsoluto = $diretorioRelatorios . '/' . $arquivo;

if (!is_file($caminhoAbsoluto) || !is_readable($caminhoAbsoluto)) {
    http_response_code(404);
    echo json_encode([
        'ok' => false,
        'erro' => 'Relatorio de validacao ainda nao foi gerado.',
        'arquivo' => $arquivo,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$conteudo = file_get_contents($caminhoAbsoluto);

if ($conteudo === false) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'erro' => 'Nao foi possivel ler o relatorio de validacao.',
        'arquivo' => $arquivo,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (str_starts_with($conteudo, "\xEF\xBB\xBF")) {
    $conteudo = substr($conteudo, 3);
}

function extrairSecaoMarkdown(string $markdown, string $titulo): string
{
    $padrao = '/^##\s+' . preg_quote($titulo, '/') . '\s*$\R(?P<corpo>.*?)(?=^##\s+|\z)/ms';

    if (!preg_match($padrao, $markdown, $matches)) {
        return '';
    }

    return trim((string) ($matches['corpo'] ?? ''));
}

function limparTextoMarkdown(string $texto): string
{
    $valor = trim($texto);
    $valor = str_replace('\|', '|', $valor);
    $valor = preg_replace('/<br\s*\/?>/i', ' / ', $valor) ?? $valor;
    $valor = preg_replace('/\s+/u', ' ', $valor) ?? $valor;
    return trim($valor);
}

function dividirLinhaTabelaMarkdown(string $linha): array
{
    $linha = trim($linha);

    if ($linha === '' || $linha[0] !== '|') {
        return [];
    }

    $linha = substr($linha, 1);
    if (str_ends_with($linha, '|')) {
        $linha = substr($linha, 0, -1);
    }

    $partes = preg_split('/(?<!\\\\)\|/', $linha);
    if (!is_array($partes)) {
        return [];
    }

    return array_map(static fn (string $item): string => limparTextoMarkdown($item), $partes);
}

function extrairCenariosMarkdown(string $markdown): array
{
    $secao = extrairSecaoMarkdown($markdown, 'Cenarios Selecionados Automaticamente');
    if ($secao === '') {
        return [];
    }

    $linhas = preg_split('/\R/', $secao) ?: [];
    $cenarios = [];

    foreach ($linhas as $linha) {
        if (preg_match('/^\s*-\s+(.+)$/', $linha, $match)) {
            $cenarios[] = limparTextoMarkdown((string) ($match[1] ?? ''));
        }
    }

    return $cenarios;
}

function extrairTabelaDetalhadaMarkdown(string $markdown): array
{
    $secao = extrairSecaoMarkdown($markdown, 'Resultado Detalhado');
    if ($secao === '') {
        return [];
    }

    $linhas = preg_split('/\R/', $secao) ?: [];
    $detalhes = [];

    foreach ($linhas as $linha) {
        $linha = trim($linha);

        if ($linha === '' || $linha[0] !== '|') {
            continue;
        }

        if (preg_match('/^\|\s*-+\s*\|\s*-+\s*\|\s*-+\s*\|\s*-+\s*\|?\s*$/', $linha)) {
            continue;
        }

        $colunas = dividirLinhaTabelaMarkdown($linha);
        if (count($colunas) < 4) {
            continue;
        }

        if (strcasecmp($colunas[0], 'Status') === 0) {
            continue;
        }

        $detalhes[] = [
            'status' => $colunas[0],
            'cenario' => $colunas[1],
            'etapa' => $colunas[2],
            'detalhe' => implode(' | ', array_slice($colunas, 3)),
        ];
    }

    return $detalhes;
}

$dataRelatorio = null;
$statusGeral = null;
$resultado = null;
$cenarios = extrairCenariosMarkdown($conteudo);
$detalhes = extrairTabelaDetalhadaMarkdown($conteudo);

if (preg_match('/^- Data:\s*(.+)$/m', $conteudo, $matchData)) {
    $dataRelatorio = trim((string) ($matchData[1] ?? ''));
}

if (preg_match('/^- Status geral:\s*\*\*(.+)\*\*$/m', $conteudo, $matchStatus)) {
    $statusGeral = trim((string) ($matchStatus[1] ?? ''));
}

if (preg_match('/^- Resultado:\s*\*\*(.+)\*\*$/m', $conteudo, $matchResultado)) {
    $resultado = trim((string) ($matchResultado[1] ?? ''));
}

echo json_encode([
    'ok' => true,
    'arquivo' => $arquivo,
    'atualizado_em' => date('Y-m-d H:i:s', (int) filemtime($caminhoAbsoluto)),
    'data_relatorio' => $dataRelatorio,
    'status_geral' => $statusGeral,
    'resultado' => $resultado,
    'cenarios' => $cenarios,
    'detalhes' => $detalhes,
    'conteudo_markdown' => $conteudo,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

exit;
