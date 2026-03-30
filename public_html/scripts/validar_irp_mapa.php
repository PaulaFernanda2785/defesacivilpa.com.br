<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Database.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Este script deve ser executado via CLI.\n";
    exit(1);
}

$db = Database::getConnection();

const PESOS_GRAVIDADE = [
    'BAIXO' => 1,
    'MODERADO' => 2,
    'ALTO' => 3,
    'MUITO ALTO' => 4,
    'EXTREMO' => 5,
];

function pesoGravidade(string $nivel): int
{
    $chave = strtoupper(trim($nivel));
    return PESOS_GRAVIDADE[$chave] ?? 0;
}

function carregarCatalogoMunicipios(PDO $db): array
{
    $sql = "
        SELECT cod_ibge, municipio
        FROM municipios_regioes_pa
        WHERE cod_ibge IS NOT NULL
          AND cod_ibge <> ''
          AND municipio IS NOT NULL
          AND municipio <> ''
    ";

    $stmt = $db->query($sql);
    $linhas = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $catalogo = [];

    foreach ($linhas as $linha) {
        $codigo = trim((string) ($linha['cod_ibge'] ?? ''));
        $nome = trim((string) ($linha['municipio'] ?? ''));

        if ($codigo === '' || $nome === '') {
            continue;
        }

        $catalogo[$codigo] = $nome;
    }

    return $catalogo;
}

function descreverMunicipioCodigo(?string $codigo, array $catalogoMunicipios): string
{
    $codIbge = trim((string) $codigo);

    if ($codIbge === '') {
        return '-';
    }

    $nome = trim((string) ($catalogoMunicipios[$codIbge] ?? ''));
    return $nome !== '' ? "{$nome} ({$codIbge})" : $codIbge;
}

function contarAlertasFiltrados(PDO $db, array $filtro): int
{
    $sql = "
        SELECT COUNT(DISTINCT a.id) AS total
        FROM alertas a
        INNER JOIN alerta_municipios am
            ON am.alerta_id = a.id
        INNER JOIN municipios_regioes_pa mr
            ON mr.cod_ibge = am.municipio_codigo
        WHERE a.status = 'ATIVO'
    ";

    $params = [];

    if (!empty($filtro['data'])) {
        $sql .= " AND a.data_alerta = :data";
        $params[':data'] = $filtro['data'];
    }

    if (!empty($filtro['gravidade'])) {
        $sql .= " AND a.nivel_gravidade = :gravidade";
        $params[':gravidade'] = $filtro['gravidade'];
    }

    if (!empty($filtro['fonte'])) {
        $sql .= " AND a.fonte = :fonte";
        $params[':fonte'] = $filtro['fonte'];
    }

    if (!empty($filtro['tipo_evento'])) {
        $sql .= " AND a.tipo_evento = :tipo_evento";
        $params[':tipo_evento'] = $filtro['tipo_evento'];
    }

    if (!empty($filtro['regiao'])) {
        $sql .= " AND mr.regiao_integracao = :regiao";
        $params[':regiao'] = $filtro['regiao'];
    }

    if (!empty($filtro['municipio'])) {
        $municipio = trim((string) $filtro['municipio']);

        if (preg_match('/^\d{7}$/', $municipio)) {
            $sql .= " AND am.municipio_codigo = :municipio_codigo";
            $params[':municipio_codigo'] = $municipio;
        } else {
            $sql .= " AND am.municipio_nome = :municipio_nome";
            $params[':municipio_nome'] = $municipio;
        }
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function encontrarCenarioAlertaUnicoMunicipio(PDO $db): ?array
{
    $sql = "
        SELECT
            a.id,
            a.numero,
            a.data_alerta,
            a.nivel_gravidade,
            a.fonte,
            a.tipo_evento,
            MIN(am.municipio_codigo) AS municipio_codigo
        FROM alertas a
        INNER JOIN alerta_municipios am
            ON am.alerta_id = a.id
        WHERE a.status = 'ATIVO'
        GROUP BY
            a.id,
            a.numero,
            a.data_alerta,
            a.nivel_gravidade,
            a.fonte,
            a.tipo_evento
        HAVING COUNT(DISTINCT am.municipio_codigo) = 1
        ORDER BY a.data_alerta DESC, a.id DESC
    ";

    $stmt = $db->query($sql);
    $candidatos = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    foreach ($candidatos as $candidato) {
        $filtroUnicidade = [
            'data' => $candidato['data_alerta'],
            'gravidade' => $candidato['nivel_gravidade'],
            'fonte' => $candidato['fonte'],
            'tipo_evento' => $candidato['tipo_evento'],
            'municipio' => $candidato['municipio_codigo'],
        ];

        if (contarAlertasFiltrados($db, $filtroUnicidade) === 1) {
            return $candidato;
        }
    }

    return null;
}

function encontrarCenarioAlertaMultiploRegiao(PDO $db): ?array
{
    $sql = "
        SELECT
            a.id,
            a.numero,
            a.data_alerta,
            a.nivel_gravidade,
            a.fonte,
            a.tipo_evento,
            mr.regiao_integracao AS regiao,
            COUNT(DISTINCT am.municipio_codigo) AS municipios_no_recorte,
            MIN(am.municipio_codigo) AS municipio_exemplo
        FROM alertas a
        INNER JOIN alerta_municipios am
            ON am.alerta_id = a.id
        INNER JOIN municipios_regioes_pa mr
            ON mr.cod_ibge = am.municipio_codigo
        WHERE a.status = 'ATIVO'
        GROUP BY
            a.id,
            a.numero,
            a.data_alerta,
            a.nivel_gravidade,
            a.fonte,
            a.tipo_evento,
            mr.regiao_integracao
        HAVING COUNT(DISTINCT am.municipio_codigo) > 1
        ORDER BY municipios_no_recorte DESC, a.data_alerta DESC, a.id DESC
    ";

    $stmt = $db->query($sql);
    $candidatos = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    foreach ($candidatos as $candidato) {
        $filtroUnicidade = [
            'data' => $candidato['data_alerta'],
            'gravidade' => $candidato['nivel_gravidade'],
            'fonte' => $candidato['fonte'],
            'tipo_evento' => $candidato['tipo_evento'],
            'regiao' => $candidato['regiao'],
        ];

        if (contarAlertasFiltrados($db, $filtroUnicidade) === 1) {
            return $candidato;
        }
    }

    return null;
}

function chamarEndpointMapa(string $arquivoEndpoint, array $params): array
{
    $phpBin = PHP_BINARY;
    $endpointAbsoluto = realpath(__DIR__ . '/../api/mapa/' . $arquivoEndpoint);

    if ($endpointAbsoluto === false) {
        throw new RuntimeException("Endpoint nao encontrado: {$arquivoEndpoint}");
    }

    $runner = tempnam(sys_get_temp_dir(), 'irpval_');

    if ($runner === false) {
        throw new RuntimeException('Nao foi possivel criar arquivo temporario de execucao.');
    }

    $codigo = "<?php\n";
    $codigo .= '$_GET = ' . var_export($params, true) . ";\n";
    $codigo .= "require " . var_export($endpointAbsoluto, true) . ";\n";

    file_put_contents($runner, $codigo);

    $comando = escapeshellarg($phpBin) . ' ' . escapeshellarg($runner) . ' 2>&1';
    $tentativas = 2;
    $ultimaFalha = '';

    for ($tentativa = 1; $tentativa <= $tentativas; $tentativa++) {
        $linhas = [];
        $codigoSaida = 0;
        exec($comando, $linhas, $codigoSaida);
        $saida = implode("\n", $linhas);

        if ($codigoSaida === 0) {
            $dados = json_decode($saida, true);

            if (is_array($dados)) {
                @unlink($runner);
                return $dados;
            }

            $ultimaFalha = "Saida JSON invalida de {$arquivoEndpoint}: {$saida}";
        } else {
            $ultimaFalha = "Falha ao executar endpoint {$arquivoEndpoint}: {$saida}";
        }

        $falhaConexao = str_contains($saida, 'Nao foi possivel conectar ao servidor MySQL');
        if ($tentativa < $tentativas && $falhaConexao) {
            usleep(300000);
            continue;
        }

        break;
    }

    @unlink($runner);
    throw new RuntimeException($ultimaFalha);
}

function irpDoDia(array $serie, string $dia): ?int
{
    foreach ($serie as $item) {
        if ((string) ($item['dia'] ?? '') === $dia) {
            return (int) ($item['irp'] ?? 0);
        }
    }

    return null;
}

function registrarResultado(array &$resultados, string $cenario, string $etapa, bool $ok, string $detalhe): void
{
    $resultados[] = [
        'cenario' => $cenario,
        'etapa' => $etapa,
        'ok' => $ok,
        'detalhe' => $detalhe,
    ];
}

function filtroBaseParaEndpoint(array $base): array
{
    return array_filter([
        'data_inicio' => $base['data_alerta'] ?? null,
        'data_fim' => $base['data_alerta'] ?? null,
        'tipo_evento' => $base['tipo_evento'] ?? null,
        'gravidade' => $base['nivel_gravidade'] ?? null,
        'fonte' => $base['fonte'] ?? null,
    ], static fn ($valor) => $valor !== null && $valor !== '');
}

function celulaMarkdown(string $texto): string
{
    return str_replace(
        ["|", "\r\n", "\n", "\r"],
        ["\\|", '<br>', '<br>', '<br>'],
        $texto
    );
}

function gerarRelatorioMarkdown(
    array $resultados,
    int $falhas,
    ?array $cenario1,
    ?array $cenario2,
    DateTimeImmutable $instante
): string {
    $total = count($resultados);
    $aprovados = $total - $falhas;
    $statusGeral = $falhas === 0 ? 'APROVADO' : 'REPROVADO';

    $linhas = [];
    $linhas[] = '# Relatorio de Validacao IRP (Mapa)';
    $linhas[] = '';
    $linhas[] = '- Data: ' . $instante->format('Y-m-d H:i:s');
    $linhas[] = '- Status geral: **' . $statusGeral . '**';
    $linhas[] = '- Resultado: **' . $aprovados . '/' . $total . ' verificacoes aprovadas**';
    $linhas[] = '';
    $linhas[] = '## Cenarios Selecionados Automaticamente';

    if (is_array($cenario1)) {
        $municipioC1 = (string) ($cenario1['municipio_descricao'] ?? ($cenario1['municipio_codigo'] ?? '-'));
        $linhas[] = '- C1 (1 alerta x 1 municipio): alerta #' . ($cenario1['numero'] ?? '-')
            . ', data ' . ($cenario1['data_alerta'] ?? '-')
            . ', municipio ' . $municipioC1
            . ', gravidade ' . ($cenario1['nivel_gravidade'] ?? '-');
    } else {
        $linhas[] = '- C1: nao identificado.';
    }

    if (is_array($cenario2)) {
        $municipioExemplo = (string) ($cenario2['municipio_exemplo_descricao'] ?? ($cenario2['municipio_exemplo'] ?? '-'));
        $linhas[] = '- C2 (1 alerta x N municipios na regiao): alerta #' . ($cenario2['numero'] ?? '-')
            . ', data ' . ($cenario2['data_alerta'] ?? '-')
            . ', regiao ' . ($cenario2['regiao'] ?? '-')
            . ', municipios no recorte ' . (int) ($cenario2['municipios_no_recorte'] ?? 0)
            . ', municipio exemplo ' . $municipioExemplo
            . ', gravidade ' . ($cenario2['nivel_gravidade'] ?? '-');
    } else {
        $linhas[] = '- C2: nao identificado.';
    }

    $linhas[] = '';
    $linhas[] = '## Resultado Detalhado';
    $linhas[] = '';
    $linhas[] = '| Status | Cenario | Etapa | Detalhe |';
    $linhas[] = '|---|---|---|---|';

    foreach ($resultados as $resultado) {
        $status = !empty($resultado['ok']) ? 'PASS' : 'FAIL';
        $cenario = celulaMarkdown((string) ($resultado['cenario'] ?? ''));
        $etapa = celulaMarkdown((string) ($resultado['etapa'] ?? ''));
        $detalhe = celulaMarkdown((string) ($resultado['detalhe'] ?? ''));

        $linhas[] = "| {$status} | {$cenario} | {$etapa} | {$detalhe} |";
    }

    $linhas[] = '';
    $linhas[] = '---';
    $linhas[] = 'Gerado automaticamente por `public_html/scripts/validar_irp_mapa.php`.';

    return implode("\n", $linhas) . "\n";
}

function salvarRelatorioMarkdown(string $conteudo, DateTimeImmutable $instante): array
{
    $raizProjeto = dirname(__DIR__, 2);
    $diretorioRelatorios = $raizProjeto . '/storage/reports/validacao_irp';

    if (!is_dir($diretorioRelatorios) && !mkdir($diretorioRelatorios, 0775, true) && !is_dir($diretorioRelatorios)) {
        throw new RuntimeException('Nao foi possivel criar o diretorio de relatorios: ' . $diretorioRelatorios);
    }

    $arquivoTimestamp = $diretorioRelatorios . '/validacao_irp_' . $instante->format('Ymd_His') . '.md';
    $arquivoLatest = $diretorioRelatorios . '/validacao_irp_latest.md';

    if (file_put_contents($arquivoTimestamp, $conteudo) === false) {
        throw new RuntimeException('Nao foi possivel salvar o relatorio: ' . $arquivoTimestamp);
    }

    if (file_put_contents($arquivoLatest, $conteudo) === false) {
        throw new RuntimeException('Nao foi possivel atualizar o relatorio latest: ' . $arquivoLatest);
    }

    return [$arquivoTimestamp, $arquivoLatest];
}

$resultados = [];
$falhas = 0;

echo "BATERIA CURTA DE VALIDACAO DO IRP (MAPA)\n";
echo "Data: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('-', 72) . "\n";

$cenario1 = null;
$cenario2 = null;
$catalogoMunicipios = carregarCatalogoMunicipios($db);
$municipioDescricaoC1 = '-';
$municipioDescricaoC2Exemplo = '-';

try {
    $cenario1 = encontrarCenarioAlertaUnicoMunicipio($db);
    $cenario2 = encontrarCenarioAlertaMultiploRegiao($db);

    if ($cenario1 === null) {
        throw new RuntimeException('Nao foi possivel encontrar cenario isolado de 1 alerta em 1 municipio.');
    }

    if ($cenario2 === null) {
        throw new RuntimeException('Nao foi possivel encontrar cenario isolado de 1 alerta em N municipios na regiao.');
    }

    $municipioDescricaoC1 = descreverMunicipioCodigo((string) ($cenario1['municipio_codigo'] ?? ''), $catalogoMunicipios);
    $municipioDescricaoC2Exemplo = descreverMunicipioCodigo((string) ($cenario2['municipio_exemplo'] ?? ''), $catalogoMunicipios);
    $cenario1['municipio_descricao'] = $municipioDescricaoC1;
    $cenario2['municipio_exemplo_descricao'] = $municipioDescricaoC2Exemplo;

    // CENARIO 1: 1 alerta em 1 municipio (filtro municipal)
    $peso1 = pesoGravidade((string) $cenario1['nivel_gravidade']);
    $esperado1 = $peso1 * 1;
    $filtro1 = filtroBaseParaEndpoint($cenario1);
    $filtro1['municipio'] = (string) $cenario1['municipio_codigo'];

    $serie1 = chamarEndpointMapa('linha_tempo_pressao.php', $filtro1);
    $irpDia1 = irpDoDia($serie1, (string) $cenario1['data_alerta']);
    $ok1 = ($irpDia1 === $esperado1);
    registrarResultado(
        $resultados,
        'C1 - 1 alerta x 1 municipio',
        'linha_tempo_pressao',
        $ok1,
        "Esperado {$peso1} x 1 = {$esperado1}; obtido {$irpDia1}"
    );

    if (!$ok1) {
        $falhas++;
    }

$municipios1 = chamarEndpointMapa('municipios_pressao.php', $filtro1);
$registroMunicipio1 = null;

foreach ($municipios1 as $item) {
    if ((string) ($item['cod_ibge'] ?? '') === (string) $cenario1['municipio_codigo']) {
        $registroMunicipio1 = $item;
        break;
    }
}

$ok1b = is_array($registroMunicipio1) && (int) ($registroMunicipio1['pressao'] ?? -1) === $esperado1;
registrarResultado(
    $resultados,
    'C1 - 1 alerta x 1 municipio',
    'municipios_pressao',
    $ok1b,
    'Municipio: ' . $municipioDescricaoC1
    . '; pressao esperada ' . $esperado1
    . '; obtida ' . (int) ($registroMunicipio1['pressao'] ?? -1)
);

if (!$ok1b) {
    $falhas++;
}

// CENARIO 2: 1 alerta em N municipios (filtro regional)
$peso2 = pesoGravidade((string) $cenario2['nivel_gravidade']);
$n2 = (int) $cenario2['municipios_no_recorte'];
$esperado2 = $peso2 * $n2;

$filtro2 = filtroBaseParaEndpoint($cenario2);
$filtro2['regiao'] = (string) $cenario2['regiao'];

$serie2 = chamarEndpointMapa('linha_tempo_pressao.php', $filtro2);
$irpDia2 = irpDoDia($serie2, (string) $cenario2['data_alerta']);
$ok2 = ($irpDia2 === $esperado2);
registrarResultado(
    $resultados,
    'C2 - 1 alerta x N municipios (regiao)',
    'linha_tempo_pressao',
    $ok2,
    "Esperado {$peso2} x {$n2} = {$esperado2}; obtido {$irpDia2}"
);

if (!$ok2) {
    $falhas++;
}

$regioes2 = chamarEndpointMapa('regioes_pressao.php', $filtro2);
$registroRegiao2 = null;

foreach ($regioes2 as $item) {
    if ((string) ($item['regiao'] ?? '') === (string) $cenario2['regiao']) {
        $registroRegiao2 = $item;
        break;
    }
}

$ok2b = is_array($registroRegiao2)
    && (int) ($registroRegiao2['pressao'] ?? -1) === $esperado2
    && (int) ($registroRegiao2['quantidade_alertas_ativos'] ?? -1) === 1;
registrarResultado(
    $resultados,
    'C2 - 1 alerta x N municipios (regiao)',
    'regioes_pressao',
    $ok2b,
    'Pressao regional esperada ' . $esperado2
    . '; obtida ' . (int) ($registroRegiao2['pressao'] ?? -1)
    . '; alertas ativos no recorte = ' . (int) ($registroRegiao2['quantidade_alertas_ativos'] ?? -1)
);

if (!$ok2b) {
    $falhas++;
}

$detalheAlerta2 = null;
if (is_array($registroRegiao2) && !empty($registroRegiao2['detalhes']) && is_array($registroRegiao2['detalhes'])) {
    foreach ($registroRegiao2['detalhes'] as $detalhe) {
        if ((int) ($detalhe['id'] ?? 0) === (int) $cenario2['id']) {
            $detalheAlerta2 = $detalhe;
            break;
        }
    }
}

$ok2c = is_array($detalheAlerta2)
    && (int) ($detalheAlerta2['pressao'] ?? -1) === $esperado2
    && (int) ($detalheAlerta2['municipios_total'] ?? -1) === $n2;
registrarResultado(
    $resultados,
    'C2 - 1 alerta x N municipios (regiao)',
    'detalhe_alerta_regional',
    $ok2c,
    'Detalhe esperado: pressao=' . $esperado2 . ' e municipios=' . $n2
    . '; obtido: pressao=' . (int) ($detalheAlerta2['pressao'] ?? -1)
    . ' e municipios=' . (int) ($detalheAlerta2['municipios_total'] ?? -1)
);

if (!$ok2c) {
    $falhas++;
}

$municipios2 = chamarEndpointMapa('municipios_pressao.php', $filtro2);
$todosComPesoCorreto = true;

if (count($municipios2) !== $n2) {
    $todosComPesoCorreto = false;
} else {
    foreach ($municipios2 as $item) {
        if ((int) ($item['pressao'] ?? -1) !== $peso2) {
            $todosComPesoCorreto = false;
            break;
        }
    }
}

registrarResultado(
    $resultados,
    'C2 - 1 alerta x N municipios (regiao)',
    'municipios_pressao',
    $todosComPesoCorreto,
    'Municipios esperados=' . $n2 . ', cada um com pressao=' . $peso2
    . '; obtidos=' . count($municipios2)
);

if (!$todosComPesoCorreto) {
    $falhas++;
}

// CENARIO 3: filtro por municipio em alerta que afeta N municipios
$filtro3 = $filtro2;
$filtro3['municipio'] = (string) $cenario2['municipio_exemplo'];

$esperado3 = $peso2 * 1;
$serie3 = chamarEndpointMapa('linha_tempo_pressao.php', $filtro3);
$irpDia3 = irpDoDia($serie3, (string) $cenario2['data_alerta']);
$ok3 = ($irpDia3 === $esperado3);

registrarResultado(
    $resultados,
    'C3 - filtro municipal sobre alerta com N municipios',
    'linha_tempo_pressao',
    $ok3,
    "Municipio filtro {$municipioDescricaoC2Exemplo}. Esperado {$peso2} x 1 = {$esperado3}; obtido {$irpDia3}"
);

if (!$ok3) {
    $falhas++;
}
} catch (Throwable $e) {
    $falhas++;
    registrarResultado(
        $resultados,
        'EXECUCAO',
        'erro_fatal',
        false,
        'Falha durante a validacao: ' . $e->getMessage()
    );
}

foreach ($resultados as $resultado) {
    $status = $resultado['ok'] ? 'PASS' : 'FAIL';
    echo "[{$status}] {$resultado['cenario']} :: {$resultado['etapa']}\n";
    echo "       {$resultado['detalhe']}\n";
}

echo str_repeat('-', 72) . "\n";
echo 'Resumo: ' . (count($resultados) - $falhas) . '/' . count($resultados) . " verificacoes aprovadas.\n";

$instanteRelatorio = new DateTimeImmutable('now');
$markdownRelatorio = gerarRelatorioMarkdown($resultados, $falhas, $cenario1, $cenario2, $instanteRelatorio);
[$arquivoRelatorio, $arquivoLatest] = salvarRelatorioMarkdown($markdownRelatorio, $instanteRelatorio);
echo "Relatorio markdown: {$arquivoRelatorio}\n";
echo "Relatorio latest:   {$arquivoLatest}\n";

if ($falhas > 0) {
    exit(1);
}

exit(0);
