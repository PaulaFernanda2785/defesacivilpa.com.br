<?php
require_once __DIR__ . '/../../app/Core/Protect.php';
require_once __DIR__ . '/../../app/Core/Session.php';
require_once __DIR__ . '/../../app/Core/Csrf.php';
require_once __DIR__ . '/../../app/Core/Database.php';
require_once __DIR__ . '/../../app/Helpers/SecurityHeaders.php';

SecurityHeaders::applyJson();
Protect::check(['ADMIN', 'GESTOR', 'ANALISTA', 'OPERACOES']);

function responder(string $resposta, string $intencao, array $dados = [], int $status = 200): void
{
    http_response_code($status);

    echo json_encode([
        'resposta' => $resposta,
        'intencao' => $intencao,
        'dados' => $dados,
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

function normalizarTextoIA(string $texto): string
{
    $texto = trim(mb_strtolower($texto, 'UTF-8'));

    if (function_exists('iconv')) {
        $convertido = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        if ($convertido !== false) {
            $texto = $convertido;
        }
    }

    $texto = preg_replace('/[^a-z0-9\s]/', ' ', $texto) ?? $texto;
    $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

    return trim($texto);
}

function normalizarDataFiltroIA(?string $valor): ?DateTimeImmutable
{
    if ($valor === null) {
        return null;
    }

    $valor = trim($valor);

    if ($valor === '') {
        return null;
    }

    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

    if (!($data instanceof DateTimeImmutable)) {
        return null;
    }

    return $data->format('Y-m-d') === $valor ? $data : null;
}

function contem(string $textoNormalizado, array $palavras): bool
{
    foreach ($palavras as $palavra) {
        if (str_contains($textoNormalizado, normalizarTextoIA($palavra))) {
            return true;
        }
    }

    return false;
}

function pesoSql(string $coluna = 'a.nivel_gravidade'): string
{
    return "
        CASE {$coluna}
            WHEN 'EXTREMO' THEN 5
            WHEN 'MUITO ALTO' THEN 4
            WHEN 'ALTO' THEN 3
            WHEN 'MODERADO' THEN 2
            WHEN 'BAIXO' THEN 1
            ELSE 0
        END
    ";
}

function fromTerritorial(): string
{
    return "
        FROM alertas a
        LEFT JOIN alerta_municipios am
            ON am.alerta_id = a.id
        LEFT JOIN municipios_regioes_pa mr
            ON mr.cod_ibge = am.municipio_codigo
    ";
}

function fetchAssoc(PDO $db, string $sql, array $params = []): array
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function fetchAllAssoc(PDO $db, string $sql, array $params = []): array
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function construirFiltros(array $contexto, bool $ignorarTerritorio = false): array
{
    $filtros = is_array($contexto['filtros'] ?? null) ? $contexto['filtros'] : [];
    $where = ["a.status = 'ATIVO'"];
    $params = [];

    if (!empty($filtros['data_inicio'])) {
        $dataInicio = normalizarDataFiltroIA((string) $filtros['data_inicio']);

        if ($dataInicio instanceof DateTimeImmutable) {
            $where[] = "a.data_alerta >= :data_inicio";
            $params[':data_inicio'] = $dataInicio->format('Y-m-d');
        }
    }

    if (!empty($filtros['data_fim'])) {
        $dataFim = normalizarDataFiltroIA((string) $filtros['data_fim']);

        if ($dataFim instanceof DateTimeImmutable) {
            $where[] = "a.data_alerta < :data_fim_exclusiva";
            $params[':data_fim_exclusiva'] = $dataFim->modify('+1 day')->format('Y-m-d');
        }
    }

    if (!empty($filtros['tipo_evento'])) {
        $where[] = "a.tipo_evento = :tipo_evento";
        $params[':tipo_evento'] = $filtros['tipo_evento'];
    }

    if (!empty($filtros['gravidade'])) {
        $where[] = "a.nivel_gravidade = :gravidade";
        $params[':gravidade'] = $filtros['gravidade'];
    }

    if (!empty($filtros['fonte'])) {
        $where[] = "a.fonte = :fonte";
        $params[':fonte'] = $filtros['fonte'];
    }

    if (!$ignorarTerritorio) {
        if (!empty($filtros['regiao'])) {
            $where[] = "mr.regiao_integracao = :regiao";
            $params[':regiao'] = trim((string) $filtros['regiao']);
        }

        $municipio = trim((string) ($filtros['municipio'] ?? $filtros['municipio_nome'] ?? ''));

        if ($municipio !== '') {
            if (preg_match('/^\d{7}$/', $municipio)) {
                $where[] = "am.municipio_codigo = :municipio_codigo";
                $params[':municipio_codigo'] = $municipio;
            } else {
                $where[] = "am.municipio_nome = :municipio_nome";
                $params[':municipio_nome'] = $municipio;
            }
        }
    }

    return [$where, $params];
}

function escopoLabel(array $contexto, ?string $territorio = null): string
{
    $filtros = is_array($contexto['filtros'] ?? null) ? $contexto['filtros'] : [];

    if ($territorio) {
        return $territorio;
    }

    if (!empty($filtros['municipio_nome'])) {
        return 'Municipio ' . $filtros['municipio_nome'];
    }

    if (!empty($filtros['regiao'])) {
        return 'Regiao ' . $filtros['regiao'];
    }

    return 'Panorama estadual';
}

function regiaoDoContexto(array $contexto, array $catalogoRegioes): ?array
{
    $filtros = is_array($contexto['filtros'] ?? null) ? $contexto['filtros'] : [];
    $regiaoAtual = trim((string) ($filtros['regiao'] ?? ''));

    if ($regiaoAtual === '') {
        return null;
    }

    $regiaoNormalizada = normalizarTextoIA($regiaoAtual);

    foreach ($catalogoRegioes as $item) {
        if (normalizarTextoIA((string) ($item['regiao'] ?? '')) === $regiaoNormalizada) {
            return $item;
        }
    }

    return ['regiao' => $regiaoAtual];
}

function municipioDoContexto(array $contexto, array $catalogoMunicipios): ?array
{
    $filtros = is_array($contexto['filtros'] ?? null) ? $contexto['filtros'] : [];
    $codigoAtual = trim((string) ($filtros['municipio'] ?? ''));
    $nomeAtual = trim((string) ($filtros['municipio_nome'] ?? ''));

    foreach ($catalogoMunicipios as $item) {
        $codigoItem = trim((string) ($item['cod_ibge'] ?? ''));
        $nomeItem = trim((string) ($item['municipio'] ?? ''));

        if ($codigoAtual !== '' && $codigoItem === $codigoAtual) {
            return $item;
        }

        if ($nomeAtual !== '' && normalizarTextoIA($nomeItem) === normalizarTextoIA($nomeAtual)) {
            return $item;
        }
    }

    return null;
}

function resumoOperacional(PDO $db, string $fromSql, array $where, array $params): array
{
    $whereSql = implode(' AND ', $where);
    $peso = pesoSql();

    $resumo = fetchAssoc($db, "
        SELECT
            COUNT(DISTINCT a.id) AS alertas,
            COUNT(DISTINCT CASE WHEN am.municipio_codigo IS NOT NULL THEN am.municipio_codigo END) AS municipios,
            COUNT(DISTINCT CASE WHEN mr.regiao_integracao IS NOT NULL THEN mr.regiao_integracao END) AS regioes,
            COALESCE(SUM(CASE WHEN am.municipio_codigo IS NULL THEN 0 ELSE {$peso} END), 0) AS irp
        {$fromSql}
        WHERE {$whereSql}
    ", $params);

    $evento = fetchAssoc($db, "
        SELECT a.tipo_evento, COUNT(DISTINCT a.id) AS total
        {$fromSql}
        WHERE {$whereSql}
        GROUP BY a.tipo_evento
        ORDER BY total DESC, a.tipo_evento
        LIMIT 1
    ", $params);

    $gravidade = fetchAssoc($db, "
        SELECT a.nivel_gravidade, COUNT(DISTINCT a.id) AS total
        {$fromSql}
        WHERE {$whereSql}
        GROUP BY a.nivel_gravidade
        ORDER BY " . pesoSql('a.nivel_gravidade') . " DESC, total DESC
        LIMIT 1
    ", $params);

    return [
        'alertas' => (int) ($resumo['alertas'] ?? 0),
        'municipios' => (int) ($resumo['municipios'] ?? 0),
        'regioes' => (int) ($resumo['regioes'] ?? 0),
        'irp' => (int) ($resumo['irp'] ?? 0),
        'evento_principal' => (string) ($evento['tipo_evento'] ?? 'Sem evento dominante'),
        'gravidade_principal' => (string) ($gravidade['nivel_gravidade'] ?? 'Sem gravidade dominante'),
    ];
}

function listarTopRegioes(PDO $db, string $fromSql, array $where, array $params, int $limit = 5): array
{
    $whereSql = implode(' AND ', $where);
    $peso = pesoSql();

    return fetchAllAssoc($db, "
        SELECT
            mr.regiao_integracao AS regiao,
            COALESCE(SUM(CASE WHEN am.municipio_codigo IS NULL THEN 0 ELSE {$peso} END), 0) AS irp,
            COUNT(DISTINCT am.municipio_codigo) AS municipios_afetados,
            COUNT(DISTINCT a.id) AS alertas
        {$fromSql}
        WHERE {$whereSql}
          AND mr.regiao_integracao IS NOT NULL
        GROUP BY mr.regiao_integracao
        ORDER BY irp DESC, alertas DESC, mr.regiao_integracao
        LIMIT {$limit}
    ", $params);
}

function listarMunicipiosPorGravidade(PDO $db, string $fromSql, array $where, array $params, string $gravidade, int $limit = 10): array
{
    $whereLocal = array_merge($where, ["a.nivel_gravidade = :gravidade_alvo"]);
    $paramsLocal = $params;
    $paramsLocal[':gravidade_alvo'] = $gravidade;

    return fetchAllAssoc($db, "
        SELECT
            am.municipio_codigo AS cod_ibge,
            am.municipio_nome AS municipio,
            mr.regiao_integracao AS regiao,
            COUNT(DISTINCT a.id) AS alertas
        " . fromTerritorial() . "
        WHERE " . implode(' AND ', $whereLocal) . "
          AND am.municipio_nome IS NOT NULL
        GROUP BY am.municipio_codigo, am.municipio_nome, mr.regiao_integracao
        ORDER BY alertas DESC, am.municipio_nome
        LIMIT {$limit}
    ", $paramsLocal);
}

function catalogoRegioes(PDO $db): array
{
    return fetchAllAssoc($db, "
        SELECT DISTINCT regiao_integracao AS regiao
        FROM municipios_regioes_pa
        WHERE regiao_integracao IS NOT NULL
          AND regiao_integracao <> ''
          AND regiao_integracao <> 'regiao_integracao'
        ORDER BY regiao_integracao
    ");
}

function catalogoMunicipios(PDO $db): array
{
    return fetchAllAssoc($db, "
        SELECT DISTINCT cod_ibge, municipio, regiao_integracao AS regiao
        FROM municipios_regioes_pa
        WHERE municipio IS NOT NULL
          AND municipio <> ''
          AND cod_ibge IS NOT NULL
          AND cod_ibge <> ''
        ORDER BY municipio
    ");
}

function melhorCorrespondencia(string $perguntaNormalizada, array $catalogo, string $campo): ?array
{
    $escolha = null;
    $tamanho = 0;

    foreach ($catalogo as $item) {
        $nome = trim((string) ($item[$campo] ?? ''));
        if ($nome === '') {
            continue;
        }

        $normalizado = normalizarTextoIA($nome);

        if ($normalizado !== '' && str_contains($perguntaNormalizada, $normalizado) && strlen($normalizado) > $tamanho) {
            $escolha = $item;
            $tamanho = strlen($normalizado);
        }
    }

    return $escolha;
}

Session::start();

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    responder('Metodo nao permitido.', 'metodo_invalido', [], 405);
}

Csrf::validateRequestOrFail();

$usuario = $_SESSION['usuario'] ?? null;
$perfisPermitidos = ['ADMIN', 'GESTOR', 'ANALISTA', 'OPERACOES'];

if (!$usuario) {
    responder('Sessao expirada. Faca login novamente.', 'erro', [], 401);
}

if (!in_array($usuario['perfil'] ?? '', $perfisPermitidos, true)) {
    responder('Voce nao tem permissao para usar este recurso.', 'acesso_negado', [], 403);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    responder('Entrada invalida.', 'erro', [], 400);
}

$perguntaOriginal = trim((string) ($input['pergunta'] ?? ''));
$pergunta = normalizarTextoIA($perguntaOriginal);
$contexto = is_array($input['contexto'] ?? null) ? $input['contexto'] : [];

if ($pergunta === '') {
    responder('Por favor, informe uma pergunta.', 'vazia');
}

$db = Database::getConnection();
$fromSql = fromTerritorial();
[$whereAtual, $paramsAtual] = construirFiltros($contexto, false);
$escopoAtual = escopoLabel($contexto);
$topoRegioesAtual = listarTopRegioes($db, $fromSql, $whereAtual, $paramsAtual, 3);
$catalogoRegioes = catalogoRegioes($db);
$catalogoMunicipios = catalogoMunicipios($db);
$regiaoMencionada = melhorCorrespondencia($pergunta, $catalogoRegioes, 'regiao');
$municipioMencionado = melhorCorrespondencia($pergunta, $catalogoMunicipios, 'municipio');
$regiaoAtual = regiaoDoContexto($contexto, $catalogoRegioes);
$municipioAtual = municipioDoContexto($contexto, $catalogoMunicipios);

if (contem($pergunta, ['oi', 'ola', 'bom dia', 'boa tarde', 'boa noite'])) {
    responder(
        'Posso atuar como assistente operacional do mapa: resumir o recorte atual, focar regiao ou municipio, destacar eventos dominantes e abrir detalhamentos na propria tela.',
        'saudacao',
        [
            'escopo_label' => $escopoAtual,
            'follow_ups' => [
                ['label' => 'Resumo do recorte', 'prompt' => 'Faca um resumo operacional do recorte atual.'],
                ['label' => 'Regiao lider', 'prompt' => 'Qual regiao esta mais pressionada no recorte atual?'],
                ['label' => 'Evento dominante', 'prompt' => 'Qual evento predomina no recorte atual?'],
            ],
        ]
    );
}

if (contem($pergunta, ['obrigado', 'obrigada', 'valeu'])) {
    responder(
        'Sigo disponivel. Se quiser, posso continuar a analise do recorte atual ou abrir um detalhamento territorial no mapa.',
        'encerramento_cordial',
        [
            'escopo_label' => $escopoAtual,
            'follow_ups' => [
                ['label' => 'Resumo operacional', 'prompt' => 'Faca um resumo operacional do recorte atual.'],
                ['label' => 'Abrir detalhamento atual', 'prompt' => 'Abra o detalhamento do recorte atual.'],
            ],
        ]
    );
}

if (contem($pergunta, ['ajuda', 'o que posso perguntar', 'sugestoes'])) {
    responder(
        'Posso trabalhar com o recorte atual do mapa para resumir o cenario, destacar regioes ou municipios, identificar eventos dominantes, abrir o IRP e limpar filtros.',
        'ajuda_operacional',
        [
            'escopo_label' => $escopoAtual,
            'resumo_cards' => [
                ['label' => 'Recorte atual', 'value' => $escopoAtual],
                ['label' => 'Modo', 'value' => (string) (($contexto['filtros']['modo'] ?? '') === 'regioes' ? 'Regioes' : 'Municipios')],
            ],
            'acoes_operacionais' => [
                ['tipo' => 'abrir_ajuda', 'label' => 'Abrir guia de uso', 'descricao' => 'Abrir guia da tela'],
                ['tipo' => 'abrir_irp', 'label' => 'Entender o IRP', 'descricao' => 'Abrir explicacao do IRP'],
            ],
            'follow_ups' => [
                ['label' => 'Resumo do recorte', 'prompt' => 'Faca um resumo operacional do recorte atual.'],
                ['label' => 'Evento dominante', 'prompt' => 'Qual evento predomina no recorte atual?'],
                ['label' => 'Regiao lider', 'prompt' => 'Qual regiao esta mais pressionada no recorte atual?'],
            ],
        ]
    );
}

if (contem($pergunta, ['limpar filtros', 'visao geral', 'voltar para o estado'])) {
    responder(
        'Posso limpar o recorte atual e retornar ao panorama geral do estado.',
        'limpar_filtros',
        [
            'escopo_label' => $escopoAtual,
            'acoes_operacionais' => [[
                'tipo' => 'limpar_filtros',
                'label' => 'Limpar filtros do mapa',
                'descricao' => 'Retornar ao panorama geral',
                'executar' => true,
            ]],
            'follow_ups' => [
                ['label' => 'Resumo geral', 'prompt' => 'Faca um resumo operacional do recorte atual.'],
            ],
        ]
    );
}

if ($municipioMencionado) {
    [$whereMunicipio, $paramsMunicipio] = construirFiltros($contexto, true);
    $whereMunicipio[] = 'am.municipio_codigo = :ia_municipio_codigo';
    $paramsMunicipio[':ia_municipio_codigo'] = $municipioMencionado['cod_ibge'];
    $resumoMunicipio = resumoOperacional($db, $fromSql, $whereMunicipio, $paramsMunicipio);
    $abrirModal = contem($pergunta, ['modal', 'detalhe', 'detalhamento', 'abrir']);
    $executar = $abrirModal || contem($pergunta, ['mostrar', 'destacar', 'focar', 'zoom']);
    $respostaMunicipio = sprintf(
        'No municipio %s, o recorte atual indica %d alertas ativos, IRP %d e gravidade predominante %s.',
        $municipioMencionado['municipio'],
        $resumoMunicipio['alertas'],
        $resumoMunicipio['irp'],
        $resumoMunicipio['gravidade_principal']
    );

    if (contem($pergunta, ['evento', 'predomina', 'dominante', 'frequente'])) {
        $respostaMunicipio = sprintf(
            'No municipio %s, o evento dominante no recorte atual e %s.',
            $municipioMencionado['municipio'],
            $resumoMunicipio['evento_principal']
        );
    } elseif (contem($pergunta, ['irp', 'pressao', 'pressao de risco'])) {
        $respostaMunicipio = sprintf(
            'No municipio %s, o IRP atual esta em %d, com gravidade predominante %s.',
            $municipioMencionado['municipio'],
            $resumoMunicipio['irp'],
            $resumoMunicipio['gravidade_principal']
        );
    } elseif (contem($pergunta, ['quantos alertas', 'alertas ativos'])) {
        $respostaMunicipio = sprintf(
            'No municipio %s, existem %d alertas ativos no recorte atual.',
            $municipioMencionado['municipio'],
            $resumoMunicipio['alertas']
        );
    }

    responder(
        $respostaMunicipio,
        'municipio_especifico',
        [
            'escopo_label' => 'Municipio ' . $municipioMencionado['municipio'],
            'resumo_cards' => [
                ['label' => 'Municipio', 'value' => $municipioMencionado['municipio']],
                ['label' => 'Regiao', 'value' => (string) ($municipioMencionado['regiao'] ?? 'Nao informada')],
                ['label' => 'Alertas ativos', 'value' => (string) $resumoMunicipio['alertas']],
                ['label' => 'IRP', 'value' => (string) $resumoMunicipio['irp']],
                ['label' => 'Evento dominante', 'value' => $resumoMunicipio['evento_principal']],
            ],
            'acoes_operacionais' => [[
                'tipo' => $abrirModal ? 'abrir_modal_municipio' : 'filtrar_municipio',
                'cod_ibge' => $municipioMencionado['cod_ibge'],
                'municipio' => $municipioMencionado['municipio'],
                'label' => $abrirModal ? 'Abrir modal do municipio' : 'Focar municipio no mapa',
                'descricao' => 'Aplicar foco municipal',
                'executar' => $executar,
            ]],
            'follow_ups' => [
                ['label' => 'Evento dominante', 'prompt' => 'Qual evento predomina no municipio ' . $municipioMencionado['municipio'] . '?'],
                ['label' => 'Pressao atual', 'prompt' => 'Como esta a pressao de risco no municipio ' . $municipioMencionado['municipio'] . '?'],
            ],
        ]
    );
}

if ($regiaoMencionada) {
    [$whereRegiao, $paramsRegiao] = construirFiltros($contexto, true);
    $whereRegiao[] = 'mr.regiao_integracao = :ia_regiao';
    $paramsRegiao[':ia_regiao'] = $regiaoMencionada['regiao'];
    $resumoRegiao = resumoOperacional($db, $fromSql, $whereRegiao, $paramsRegiao);
    $abrirModal = contem($pergunta, ['modal', 'detalhe', 'detalhamento', 'abrir']);
    $executar = $abrirModal || contem($pergunta, ['mostrar', 'destacar', 'focar', 'zoom']);
    $respostaRegiao = sprintf(
        'Na regiao %s, o recorte atual soma %d alertas ativos, %d municipios afetados e IRP %d.',
        $regiaoMencionada['regiao'],
        $resumoRegiao['alertas'],
        $resumoRegiao['municipios'],
        $resumoRegiao['irp']
    );

    if (contem($pergunta, ['municipios', 'extremo'])) {
        $municipiosExtremosRegiao = listarMunicipiosPorGravidade($db, $fromSql, $whereRegiao, $paramsRegiao, 'EXTREMO');

        if (!$municipiosExtremosRegiao) {
            responder(
                'Nao ha municipios em alerta extremo na regiao ' . $regiaoMencionada['regiao'] . ' para o recorte atual.',
                'municipios_extremo_regiao',
                ['escopo_label' => 'Regiao ' . $regiaoMencionada['regiao']]
            );
        }

        $nomes = array_map(static fn(array $item): string => (string) $item['municipio'], $municipiosExtremosRegiao);

        responder(
            'Na regiao ' . $regiaoMencionada['regiao'] . ', os municipios em alerta extremo sao: ' . implode(', ', $nomes) . '.',
            'municipios_extremo_regiao',
            [
                'escopo_label' => 'Regiao ' . $regiaoMencionada['regiao'],
                'resumo_cards' => [
                    ['label' => 'Regiao', 'value' => $regiaoMencionada['regiao']],
                    ['label' => 'Municipios extremos', 'value' => (string) count($municipiosExtremosRegiao)],
                    ['label' => 'IRP regional', 'value' => (string) $resumoRegiao['irp']],
                ],
                'acoes_operacionais' => [[
                    'tipo' => 'filtrar_regiao',
                    'regiao' => $regiaoMencionada['regiao'],
                    'label' => 'Focar regiao no mapa',
                    'descricao' => 'Aplicar foco regional',
                ]],
                'follow_ups' => [
                    ['label' => 'Abrir detalhe da regiao', 'prompt' => 'Abra o detalhamento da regiao ' . $regiaoMencionada['regiao'] . '.'],
                ],
            ]
        );
    }

    if (contem($pergunta, ['municipios', 'muito alto'])) {
        $municipiosMuitoAltosRegiao = listarMunicipiosPorGravidade($db, $fromSql, $whereRegiao, $paramsRegiao, 'MUITO ALTO');

        if (!$municipiosMuitoAltosRegiao) {
            responder(
                'Nao ha municipios em alerta muito alto na regiao ' . $regiaoMencionada['regiao'] . ' para o recorte atual.',
                'municipios_muito_alto_regiao',
                ['escopo_label' => 'Regiao ' . $regiaoMencionada['regiao']]
            );
        }

        $nomes = array_map(static fn(array $item): string => (string) $item['municipio'], $municipiosMuitoAltosRegiao);

        responder(
            'Na regiao ' . $regiaoMencionada['regiao'] . ', os municipios em alerta muito alto sao: ' . implode(', ', $nomes) . '.',
            'municipios_muito_alto_regiao',
            [
                'escopo_label' => 'Regiao ' . $regiaoMencionada['regiao'],
                'resumo_cards' => [
                    ['label' => 'Regiao', 'value' => $regiaoMencionada['regiao']],
                    ['label' => 'Municipios muito altos', 'value' => (string) count($municipiosMuitoAltosRegiao)],
                    ['label' => 'IRP regional', 'value' => (string) $resumoRegiao['irp']],
                ],
                'acoes_operacionais' => [[
                    'tipo' => 'filtrar_regiao',
                    'regiao' => $regiaoMencionada['regiao'],
                    'label' => 'Focar regiao no mapa',
                    'descricao' => 'Aplicar foco regional',
                ]],
                'follow_ups' => [
                    ['label' => 'Abrir detalhe da regiao', 'prompt' => 'Abra o detalhamento da regiao ' . $regiaoMencionada['regiao'] . '.'],
                ],
            ]
        );
    }

    if (contem($pergunta, ['evento', 'predomina', 'dominante', 'frequente'])) {
        $respostaRegiao = sprintf(
            'Na regiao %s, o evento dominante no recorte atual e %s.',
            $regiaoMencionada['regiao'],
            $resumoRegiao['evento_principal']
        );
    } elseif (contem($pergunta, ['irp', 'pressao', 'pressao de risco'])) {
        $respostaRegiao = sprintf(
            'Na regiao %s, o IRP atual esta em %d e a gravidade predominante e %s.',
            $regiaoMencionada['regiao'],
            $resumoRegiao['irp'],
            $resumoRegiao['gravidade_principal']
        );
    } elseif (contem($pergunta, ['quantos alertas', 'alertas ativos'])) {
        $respostaRegiao = sprintf(
            'Na regiao %s, existem %d alertas ativos no recorte atual.',
            $regiaoMencionada['regiao'],
            $resumoRegiao['alertas']
        );
    }

    responder(
        $respostaRegiao,
        'regiao_especifica',
        [
            'escopo_label' => 'Regiao ' . $regiaoMencionada['regiao'],
            'resumo_cards' => [
                ['label' => 'Regiao', 'value' => $regiaoMencionada['regiao']],
                ['label' => 'Alertas ativos', 'value' => (string) $resumoRegiao['alertas']],
                ['label' => 'Municipios em risco', 'value' => (string) $resumoRegiao['municipios']],
                ['label' => 'IRP', 'value' => (string) $resumoRegiao['irp']],
                ['label' => 'Evento dominante', 'value' => $resumoRegiao['evento_principal']],
            ],
            'acoes_operacionais' => [[
                'tipo' => $abrirModal ? 'abrir_modal_regiao' : 'filtrar_regiao',
                'regiao' => $regiaoMencionada['regiao'],
                'label' => $abrirModal ? 'Abrir modal da regiao' : 'Focar regiao no mapa',
                'descricao' => 'Aplicar foco regional',
                'executar' => $executar,
            ]],
            'follow_ups' => [
                ['label' => 'Eventos da regiao', 'prompt' => 'Qual evento predomina na regiao ' . $regiaoMencionada['regiao'] . '?'],
                ['label' => 'Abrir detalhe', 'prompt' => 'Abra o detalhamento da regiao ' . $regiaoMencionada['regiao'] . '.'],
            ],
        ]
    );
}

if (contem($pergunta, ['abrir detalhamento', 'abrir detalhe', 'abrir modal', 'mostrar detalhe', 'mostrar detalhamento'])) {
    if ($municipioAtual) {
        responder(
            'Vou abrir o detalhamento do municipio atualmente selecionado no mapa.',
            'abrir_detalhamento_atual_municipio',
            [
                'escopo_label' => 'Municipio ' . $municipioAtual['municipio'],
                'acoes_operacionais' => [[
                    'tipo' => 'abrir_modal_municipio',
                    'cod_ibge' => $municipioAtual['cod_ibge'],
                    'municipio' => $municipioAtual['municipio'],
                    'label' => 'Abrir modal do municipio',
                    'descricao' => 'Exibir o detalhamento municipal',
                    'executar' => true,
                ]],
                'follow_ups' => [
                    ['label' => 'Evento do municipio', 'prompt' => 'Qual evento predomina no municipio ' . $municipioAtual['municipio'] . '?'],
                    ['label' => 'Pressao do municipio', 'prompt' => 'Como esta a pressao de risco no municipio ' . $municipioAtual['municipio'] . '?'],
                ],
            ]
        );
    }

    if ($regiaoAtual) {
        responder(
            'Vou abrir o detalhamento da regiao atualmente selecionada no mapa.',
            'abrir_detalhamento_atual_regiao',
            [
                'escopo_label' => 'Regiao ' . $regiaoAtual['regiao'],
                'acoes_operacionais' => [[
                    'tipo' => 'abrir_modal_regiao',
                    'regiao' => $regiaoAtual['regiao'],
                    'label' => 'Abrir modal da regiao',
                    'descricao' => 'Exibir o detalhamento regional',
                    'executar' => true,
                ]],
                'follow_ups' => [
                    ['label' => 'Evento da regiao', 'prompt' => 'Qual evento predomina na regiao ' . $regiaoAtual['regiao'] . '?'],
                    ['label' => 'Municipios extremos', 'prompt' => 'Quais municipios estao em alerta extremo na regiao ' . $regiaoAtual['regiao'] . '?'],
                ],
            ]
        );
    }

    if (contem($pergunta, ['regiao lider']) && $topoRegioesAtual) {
        $principal = $topoRegioesAtual[0];

        responder(
            'Vou abrir o detalhamento da regiao lider do recorte atual.',
            'abrir_detalhamento_regiao_lider',
            [
                'escopo_label' => 'Regiao ' . $principal['regiao'],
                'acoes_operacionais' => [[
                    'tipo' => 'abrir_modal_regiao',
                    'regiao' => $principal['regiao'],
                    'label' => 'Abrir modal da regiao lider',
                    'descricao' => 'Exibir o detalhamento da regiao com maior IRP',
                    'executar' => true,
                ]],
                'follow_ups' => [
                    ['label' => 'Evento da lider', 'prompt' => 'Qual evento predomina na regiao ' . $principal['regiao'] . '?'],
                ],
            ]
        );
    }
}

if (contem($pergunta, ['resumo', 'cenario', 'contexto atual', 'recorte atual', 'panorama'])) {
    $resumo = resumoOperacional($db, $fromSql, $whereAtual, $paramsAtual);

    responder(
        sprintf(
            'No recorte atual do mapa, ha %d alertas ativos, %d municipios afetados, %d regioes impactadas e IRP %d. O evento dominante e %s.',
            $resumo['alertas'],
            $resumo['municipios'],
            $resumo['regioes'],
            $resumo['irp'],
            $resumo['evento_principal']
        ),
        'resumo_operacional',
        [
            'escopo_label' => $escopoAtual,
            'resumo_cards' => [
                ['label' => 'Alertas ativos', 'value' => (string) $resumo['alertas']],
                ['label' => 'Municipios afetados', 'value' => (string) $resumo['municipios']],
                ['label' => 'Regioes afetadas', 'value' => (string) $resumo['regioes']],
                ['label' => 'IRP', 'value' => (string) $resumo['irp']],
            ],
            'acoes_operacionais' => [
                ['tipo' => 'abrir_irp', 'label' => 'Abrir explicacao do IRP', 'descricao' => 'Ver metodologia do IRP'],
            ],
            'follow_ups' => [
                ['label' => 'Regiao lider', 'prompt' => 'Qual regiao esta mais pressionada no recorte atual?'],
                ['label' => 'Evento dominante', 'prompt' => 'Qual evento predomina no recorte atual?'],
            ],
        ]
    );
}

if (contem($pergunta, ['quantos alertas', 'alertas ativos'])) {
    $resumo = resumoOperacional($db, $fromSql, $whereAtual, $paramsAtual);

    responder(
        sprintf('No escopo atual existem %d alertas ativos.', $resumo['alertas']),
        'alertas_ativos',
        [
            'escopo_label' => $escopoAtual,
            'resumo_cards' => [
                ['label' => 'Alertas ativos', 'value' => (string) $resumo['alertas']],
                ['label' => 'IRP', 'value' => (string) $resumo['irp']],
            ],
            'follow_ups' => [
                ['label' => 'Resumo completo', 'prompt' => 'Faca um resumo operacional do recorte atual.'],
            ],
        ]
    );
}

if (contem($pergunta, ['regioes', 'maior risco', 'mais afetadas', 'mais pressionadas', 'regiao lider'])) {
    $regioes = $topoRegioesAtual;

    if (!$regioes) {
        responder('Nao ha regioes com alertas ativos dentro do recorte atual.', 'regioes_afetadas', [
            'escopo_label' => $escopoAtual,
        ]);
    }

    $principal = $regioes[0];
    $lista = array_map(
        static fn(array $regiao): string => sprintf(
            '%s (IRP %s, %s municipios)',
            $regiao['regiao'],
            $regiao['irp'],
            $regiao['municipios_afetados']
        ),
        $regioes
    );

    responder(
        'As regioes mais pressionadas no recorte atual sao: ' . implode(', ', $lista) . '.',
        'regioes_afetadas',
        [
            'escopo_label' => $escopoAtual,
            'resumo_cards' => [
                ['label' => 'Regiao lider', 'value' => (string) $principal['regiao']],
                ['label' => 'IRP da lider', 'value' => (string) $principal['irp']],
                ['label' => 'Municipios afetados', 'value' => (string) $principal['municipios_afetados']],
                ['label' => 'Alertas', 'value' => (string) $principal['alertas']],
            ],
            'acoes_operacionais' => [[
                'tipo' => 'filtrar_regiao',
                'regiao' => $principal['regiao'],
                'label' => 'Focar regiao lider no mapa',
                'descricao' => 'Aplicar destaque regional',
                'executar' => contem($pergunta, ['mostrar', 'destacar', 'focar', 'zoom']),
            ]],
            'follow_ups' => [
                ['label' => 'Abrir modal da lider', 'prompt' => 'Abra o detalhamento da regiao ' . $principal['regiao'] . '.'],
                ['label' => 'Evento da lider', 'prompt' => 'Qual evento predomina na regiao ' . $principal['regiao'] . '?'],
            ],
        ]
    );
}

if (contem($pergunta, ['municipios', 'extremo'])) {
    $municipios = listarMunicipiosPorGravidade($db, $fromSql, $whereAtual, $paramsAtual, 'EXTREMO');

    if (!$municipios) {
        responder('Nao ha municipios em alerta extremo dentro do recorte atual.', 'municipios_extremo', [
            'escopo_label' => $escopoAtual,
        ]);
    }

    $principal = $municipios[0];
    $nomes = array_map(static fn(array $item): string => (string) $item['municipio'], $municipios);

    responder(
        'Os municipios em alerta extremo no recorte atual sao: ' . implode(', ', $nomes) . '.',
        'municipios_extremo',
        [
            'escopo_label' => $escopoAtual,
            'resumo_cards' => [
                ['label' => 'Primeiro municipio', 'value' => (string) $principal['municipio']],
                ['label' => 'Regiao', 'value' => (string) ($principal['regiao'] ?? 'Nao informada')],
                ['label' => 'Alertas extremos', 'value' => (string) $principal['alertas']],
            ],
            'acoes_operacionais' => [[
                'tipo' => 'filtrar_municipio',
                'cod_ibge' => $principal['cod_ibge'],
                'municipio' => $principal['municipio'],
                'label' => 'Focar primeiro municipio',
                'descricao' => 'Aplicar foco municipal',
            ]],
            'follow_ups' => [
                ['label' => 'Abrir detalhe', 'prompt' => 'Abra o detalhamento do municipio ' . $principal['municipio'] . '.'],
            ],
        ]
    );
}

if (contem($pergunta, ['municipios', 'muito alto'])) {
    $municipios = listarMunicipiosPorGravidade($db, $fromSql, $whereAtual, $paramsAtual, 'MUITO ALTO');

    if (!$municipios) {
        responder('Nao ha municipios em alerta muito alto dentro do recorte atual.', 'municipios_muito_alto', [
            'escopo_label' => $escopoAtual,
        ]);
    }

    $principal = $municipios[0];
    $nomes = array_map(static fn(array $item): string => (string) $item['municipio'], $municipios);

    responder(
        'Os municipios em alerta muito alto no recorte atual sao: ' . implode(', ', $nomes) . '.',
        'municipios_muito_alto',
        [
            'escopo_label' => $escopoAtual,
            'resumo_cards' => [
                ['label' => 'Primeiro municipio', 'value' => (string) $principal['municipio']],
                ['label' => 'Regiao', 'value' => (string) ($principal['regiao'] ?? 'Nao informada')],
                ['label' => 'Alertas muito altos', 'value' => (string) $principal['alertas']],
            ],
            'acoes_operacionais' => [[
                'tipo' => 'filtrar_municipio',
                'cod_ibge' => $principal['cod_ibge'],
                'municipio' => $principal['municipio'],
                'label' => 'Focar primeiro municipio',
                'descricao' => 'Aplicar foco municipal',
            ]],
            'follow_ups' => [
                ['label' => 'Abrir detalhe', 'prompt' => 'Abra o detalhamento do municipio ' . $principal['municipio'] . '.'],
            ],
        ]
    );
}

if (contem($pergunta, ['evento', 'frequente', 'predomina', 'dominante'])) {
    $evento = fetchAssoc($db, "
        SELECT a.tipo_evento, COUNT(DISTINCT a.id) AS total
        {$fromSql}
        WHERE " . implode(' AND ', $whereAtual) . "
        GROUP BY a.tipo_evento
        ORDER BY total DESC, a.tipo_evento
        LIMIT 1
    ", $paramsAtual);

    if (!$evento) {
        responder('Nao foi possivel identificar um evento dominante no recorte atual.', 'evento_frequente', [
            'escopo_label' => $escopoAtual,
        ]);
    }

    responder(
        "O evento dominante no recorte atual e {$evento['tipo_evento']}, com {$evento['total']} alertas ativos.",
        'evento_frequente',
        [
            'escopo_label' => $escopoAtual,
            'resumo_cards' => [
                ['label' => 'Evento dominante', 'value' => (string) $evento['tipo_evento']],
                ['label' => 'Alertas', 'value' => (string) $evento['total']],
            ],
            'follow_ups' => [
                ['label' => 'Resumo operacional', 'prompt' => 'Faca um resumo operacional do recorte atual.'],
                ['label' => 'Regiao lider', 'prompt' => 'Qual regiao esta mais pressionada no recorte atual?'],
            ],
        ]
    );
}

if (contem($pergunta, ['irp', 'pressao', 'pressao de risco'])) {
    $resumo = resumoOperacional($db, $fromSql, $whereAtual, $paramsAtual);

    responder(
        "A pressao de risco atual no recorte do mapa esta estimada em {$resumo['irp']}.",
        'irp_atual',
        [
            'escopo_label' => $escopoAtual,
            'resumo_cards' => [
                ['label' => 'IRP', 'value' => (string) $resumo['irp']],
                ['label' => 'Gravidade dominante', 'value' => $resumo['gravidade_principal']],
                ['label' => 'Evento dominante', 'value' => $resumo['evento_principal']],
            ],
            'acoes_operacionais' => [
                ['tipo' => 'abrir_irp', 'label' => 'Abrir explicacao do IRP', 'descricao' => 'Ver metodologia do indicador'],
            ],
            'follow_ups' => [
                ['label' => 'Resumo do recorte', 'prompt' => 'Faca um resumo operacional do recorte atual.'],
            ],
        ]
    );
}

responder(
    'Ainda nao identifiquei essa pergunta com seguranca, mas posso ajudar com resumo do recorte atual, eventos dominantes, regioes mais pressionadas, municipios em alerta extremo e acoes no proprio mapa.',
    'desconhecida',
    [
        'escopo_label' => $escopoAtual,
        'follow_ups' => [
            ['label' => 'Resumo do recorte', 'prompt' => 'Faca um resumo operacional do recorte atual.'],
            ['label' => 'Regiao lider', 'prompt' => 'Qual regiao esta mais pressionada no recorte atual?'],
            ['label' => 'Evento dominante', 'prompt' => 'Qual evento predomina no recorte atual?'],
        ],
    ]
);
