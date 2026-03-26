<?php
require_once __DIR__ . '/app/Core/Session.php';
require_once __DIR__ . '/app/Core/Auth.php';
require_once __DIR__ . '/app/Core/Csrf.php';
require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/AppConfig.php';
require_once __DIR__ . '/app/Helpers/AlertaFormHelper.php';
require_once __DIR__ . '/app/Helpers/LoginRateLimiter.php';
require_once __DIR__ . '/app/Helpers/SecurityHeaders.php';
require_once __DIR__ . '/app/Helpers/TimeHelper.php';
require_once __DIR__ . '/app/Services/HistoricoService.php';

TimeHelper::bootstrap();
Session::start();
SecurityHeaders::applyHtmlPage();
$cspNonce = SecurityHeaders::scriptNonce();
SecurityHeaders::applyPublicCsp([
    'include_nonce' => true,
    'script_src' => ["'self'", 'https://unpkg.com'],
    'style_src' => ["'self'", "'unsafe-inline'", 'https://unpkg.com'],
    'img_src' => ["'self'", 'data:', 'blob:', 'https://tile.openstreetmap.org', 'https://*.tile.openstreetmap.org'],
    'font_src' => ["'self'", 'data:'],
    'connect_src' => ["'self'"],
    'frame_src' => ["'self'"],
    'frame_ancestors' => ["'self'"],
]);

if (Session::isExpiredByInactivity()) {
    Session::destroy();
    header('Location: /index.php?motivo=inatividade');
    exit;
}

$appConfig = AppConfig::get();
$usuarioAtivo = $_SESSION['usuario'] ?? null;

if (is_array($usuarioAtivo)) {
    Session::touchActivity();
}

$erro = '';
$mensagemSessao = '';
$emailInformado = '';
$motivo = trim((string) ($_GET['motivo'] ?? ''));

if ($motivo === 'inatividade') {
    $mensagemSessao = 'Sua sessao foi encerrada por inatividade. Entre novamente para continuar.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::validateRequestOrFail();

    $email = trim((string) ($_POST['email'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');
    $emailInformado = $email;
    $loginThrottleKey = LoginRateLimiter::keyForRequest();

    if (LoginRateLimiter::isBlocked($loginThrottleKey)) {
        $minutosRestantes = max(1, (int) ceil(LoginRateLimiter::retryAfterSeconds($loginThrottleKey) / 60));
        $erro = 'Muitas tentativas de acesso. Aguarde ' . $minutosRestantes . ' minuto(s) e tente novamente.';
    } elseif (Auth::login($email, $senha)) {
        LoginRateLimiter::clear($loginThrottleKey);
        $usuario = $_SESSION['usuario'] ?? null;

        if (is_array($usuario) && !empty($usuario['id']) && !empty($usuario['nome'])) {
            HistoricoService::registrar(
                (int) $usuario['id'],
                (string) $usuario['nome'],
                'LOGIN_SISTEMA',
                'Realizou login no sistema',
                'Origem: tela inicial publica'
            );
        }

        header('Location: /pages/painel.php');
        exit;
    } else {
        LoginRateLimiter::registerFailure($loginThrottleKey);
        usleep(250000);

        if (LoginRateLimiter::isBlocked($loginThrottleKey)) {
            $erro = 'Muitas tentativas de acesso. Aguarde alguns minutos antes de tentar novamente.';
        } else {
            $erro = 'Usuario ou senha invalidos.';
        }
    }
}

$db = Database::getConnection();

$eventosDisponiveis = AlertaFormHelper::eventos();
$gravidadesDisponiveis = AlertaFormHelper::niveis();
$fontesDisponiveis = array_values(array_unique(array_merge(['MANUAL'], AlertaFormHelper::fontes())));
sort($fontesDisponiveis, SORT_NATURAL | SORT_FLAG_CASE);

$stmtTerritorios = $db->query("
    SELECT cod_ibge, municipio, regiao_integracao
    FROM municipios_regioes_pa
    WHERE cod_ibge IS NOT NULL
      AND cod_ibge <> ''
      AND municipio IS NOT NULL
      AND municipio <> ''
      AND regiao_integracao IS NOT NULL
      AND regiao_integracao <> ''
      AND regiao_integracao <> 'regiao_integracao'
    ORDER BY regiao_integracao, municipio
");

$regioesDisponiveis = [];
$municipiosDisponiveis = [];

foreach ($stmtTerritorios->fetchAll(PDO::FETCH_ASSOC) as $territorio) {
    $regiao = trim((string) ($territorio['regiao_integracao'] ?? ''));
    $municipio = trim((string) ($territorio['municipio'] ?? ''));
    $codIbge = trim((string) ($territorio['cod_ibge'] ?? ''));

    if ($regiao === '' || $municipio === '' || $codIbge === '') {
        continue;
    }

    $regioesDisponiveis[$regiao] = true;
    $municipiosDisponiveis[] = [
        'cod_ibge' => $codIbge,
        'municipio' => $municipio,
        'regiao' => $regiao,
    ];
}

$regioesDisponiveis = array_keys($regioesDisponiveis);
sort($regioesDisponiveis, SORT_NATURAL | SORT_FLAG_CASE);

$totalAtivos = (int) $db->query("
    SELECT COUNT(*)
    FROM alertas
    WHERE status = 'ATIVO'
")->fetchColumn();

$totalAlertasMapeados = (int) $db->query("
    SELECT COUNT(*)
    FROM alertas
    WHERE status = 'ATIVO'
      AND area_geojson IS NOT NULL
      AND area_geojson <> ''
")->fetchColumn();

$alertaMaisRecente = $db->query("
    SELECT numero, tipo_evento, data_alerta
    FROM alertas
    WHERE status = 'ATIVO'
    ORDER BY COALESCE(inicio_alerta, CONCAT(data_alerta, ' 00:00:00')) DESC, numero DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC) ?: null;

$stmtAlertasPublicos = $db->query("
    SELECT
        a.id,
        a.numero,
        a.fonte,
        a.tipo_evento,
        a.nivel_gravidade,
        a.data_alerta,
        a.inicio_alerta,
        a.fim_alerta,
        COUNT(DISTINCT am.municipio_codigo) AS total_municipios,
        GROUP_CONCAT(DISTINCT mr.regiao_integracao ORDER BY mr.regiao_integracao SEPARATOR ', ') AS regioes
    FROM alertas a
    LEFT JOIN alerta_municipios am
        ON am.alerta_id = a.id
    LEFT JOIN municipios_regioes_pa mr
        ON mr.cod_ibge = am.municipio_codigo
    WHERE a.status = 'ATIVO'
    GROUP BY
        a.id,
        a.numero,
        a.fonte,
        a.tipo_evento,
        a.nivel_gravidade,
        a.data_alerta,
        a.inicio_alerta,
        a.fim_alerta
    ORDER BY
        CASE a.nivel_gravidade
            WHEN 'EXTREMO' THEN 5
            WHEN 'MUITO ALTO' THEN 4
            WHEN 'ALTO' THEN 3
            WHEN 'MODERADO' THEN 2
            WHEN 'BAIXO' THEN 1
            ELSE 0
        END DESC,
        COALESCE(a.inicio_alerta, CONCAT(a.data_alerta, ' 00:00:00')) DESC,
        a.numero DESC
    LIMIT 12
");

$alertasPublicos = $stmtAlertasPublicos->fetchAll(PDO::FETCH_ASSOC);

$analises = [
    [
        'slug' => 'temporal',
        'kicker' => 'Temporal',
        'titulo' => 'Leitura temporal',
        'descricao' => 'Avalia sazonalidade, frequencia por periodo do dia e variacao anual dos alertas.',
        'nivel' => 'Operacional / Tatico',
        'filtros' => ['Ano', 'Mes', 'Regiao', 'Municipio', 'Evento'],
        'href' => '/pages/analises/temporal.php',
        'graficos' => [
            'Sazonalidade mensal',
            'Comparativo multievento',
            'Distribuicao mensal por evento',
            'Evolucao do periodo',
            'Evolucao anual',
            'Alertas cancelados por ano',
            'Frequencia por periodo do dia',
        ],
    ],
    [
        'slug' => 'severidade',
        'kicker' => 'Severidade',
        'titulo' => 'Severidade e impacto',
        'descricao' => 'Resume distribuicao por gravidade, duracao media e concentracao territorial.',
        'nivel' => 'Operacional',
        'filtros' => ['Ano', 'Mes', 'Regiao', 'Municipio'],
        'href' => '/pages/analises/severidade.php',
        'graficos' => [
            'Distribuicao por gravidade',
            'Proporcao entre faixas de severidade',
            'Duracao media por evento',
            'Quantidade de alertas por evento',
            'Municipios mais impactados',
        ],
    ],
    [
        'slug' => 'tipologia',
        'kicker' => 'Tipologia',
        'titulo' => 'Tipologia de eventos',
        'descricao' => 'Mostra quais eventos predominam por regiao e sua correlacao com severidade.',
        'nivel' => 'Operacional',
        'filtros' => ['Ano', 'Mes', 'Regiao', 'Municipio'],
        'href' => '/pages/analises/tipologia.php',
        'graficos' => [
            'Distribuicao por tipo de evento',
            'Participacao percentual dos eventos',
            'Correlacao entre tipologia e severidade',
            'Tipologia por regiao',
            'Sazonalidade dos eventos',
            'Recorrencia municipal por tipologia',
        ],
    ],
    [
        'slug' => 'indices',
        'kicker' => 'Indices',
        'titulo' => 'IRP e IPT',
        'descricao' => 'Prioriza territorios com maior pressao operacional e maior intensidade territorial.',
        'nivel' => 'Estrategico',
        'filtros' => ['Ano', 'Mes', 'Regiao', 'Municipio'],
        'href' => '/pages/analises/indice_risco.php',
        'graficos' => [
            'Ranking regional do IRP',
            'Ranking municipal do IPT',
            'Grafico comparativo do IRP',
            'Grafico comparativo do IPT',
        ],
    ],
];

$analisesPreview = [
    'temporal' => [
        'hero_kicker' => 'Analise operacional',
        'pagina_titulo' => 'Analise temporal de alertas',
        'pagina_descricao' => 'Organize a leitura de sazonalidade, recorrencia por periodo do dia e evolucao historica dos alertas no mesmo padrao visual das telas analiticas.',
        'resumo' => [
            ['label' => 'Sazonalidade', 'value' => 'Meses criticos', 'note' => 'Resume os periodos com maior concentracao de alertas no recorte.'],
            ['label' => 'Recorrencia diaria', 'value' => 'Turno predominante', 'note' => 'Mostra a faixa horaria mais frequente nas ocorrencias monitoradas.'],
            ['label' => 'Historico', 'value' => 'Comparativo anual', 'note' => 'Cruza a evolucao temporal com os anos anteriores do recorte selecionado.'],
        ],
        'filtros_titulo' => 'Filtros do recorte temporal',
        'filtros_texto' => 'Ano, mes, regiao, municipio e evento recalculam a leitura temporal e mantem a comparacao historica coerente.',
        'insights_titulo' => 'Leituras rapidas do periodo',
        'insights_texto' => 'A pagina destaca pico sazonal, periodo do dia predominante, evento dominante e ano historicamente mais ativo.',
        'insights' => [
            ['label' => 'Pico sazonal', 'value' => 'Mes com maior volume', 'note' => 'Ajuda a localizar a janela de maior recorrencia no recorte.'],
            ['label' => 'Periodo do dia', 'value' => 'Faixa horaria lider', 'note' => 'Indica o turno em que os alertas mais se concentram.'],
            ['label' => 'Evento dominante', 'value' => 'Tipologia principal', 'note' => 'Aponta o evento mais recorrente da leitura temporal.'],
            ['label' => 'Base historica', 'value' => 'Ano mais ativo', 'note' => 'Mostra a referencia anual com maior carga de alertas.'],
        ],
        'graficos_preview' => [
            ['kicker' => 'Secao 3', 'titulo' => 'Sazonalidade mensal do recorte', 'descricao' => 'Grafico principal com a distribuicao mensal dos alertas.', 'variant' => 'wide'],
            ['kicker' => 'Secao 4', 'titulo' => 'Comparativo mensal de eventos', 'descricao' => 'Compara o comportamento das tipologias ao longo do ano.', 'variant' => 'half'],
            ['kicker' => 'Secao 5', 'titulo' => 'Sazonalidade do evento selecionado', 'descricao' => 'Detalha a distribuicao mensal de um evento especifico.', 'variant' => 'half'],
            ['kicker' => 'Secao 6', 'titulo' => 'Evolucao anual e alertas cancelados', 'descricao' => 'Consolida historico anual, cancelamentos e recorrencia por hora.', 'variant' => 'wide'],
        ],
        'rodape' => 'Ideal para entender janelas criticas antes de aprofundar a leitura territorial no mapa.',
    ],
    'severidade' => [
        'hero_kicker' => 'Analise operacional',
        'pagina_titulo' => 'Severidade e impacto dos alertas',
        'pagina_descricao' => 'Consolide distribuicao de gravidade, duracao media dos eventos e impacto territorial no mesmo padrao visual das paginas analiticas recentes.',
        'resumo' => [
            ['label' => 'Severidade', 'value' => 'Faixas de gravidade', 'note' => 'Mostra como o recorte se distribui entre baixo, moderado, alto, muito alto e extremo.'],
            ['label' => 'Impacto', 'value' => 'Abrangencia territorial', 'note' => 'Resume a carga operacional observada por regiao e municipio.'],
            ['label' => 'Contexto', 'value' => 'Eventos e duracao', 'note' => 'Cruza severidade com tempo de permanencia dos alertas ativos.'],
        ],
        'filtros_titulo' => 'Filtros do recorte analitico',
        'filtros_texto' => 'Ano, mes, regiao e municipio recalculam automaticamente a leitura de severidade e impacto.',
        'insights_titulo' => 'Leituras rapidas do periodo',
        'insights_texto' => 'A pagina evidencia a severidade predominante, o evento mais recorrente, a maior duracao media e o municipio mais impactado.',
        'insights' => [
            ['label' => 'Severidade predominante', 'value' => 'Faixa lider', 'note' => 'Sinaliza a faixa de gravidade com maior concentracao no recorte.'],
            ['label' => 'Evento recorrente', 'value' => 'Tipologia dominante', 'note' => 'Mostra qual evento mais pressiona a operacao no periodo.'],
            ['label' => 'Duracao media', 'value' => 'Evento mais duradouro', 'note' => 'Indica os eventos que tendem a permanecer ativos por mais tempo.'],
            ['label' => 'Impacto municipal', 'value' => 'Municipio mais afetado', 'note' => 'Destaca o territorio com maior concentracao de alertas.'],
        ],
        'graficos_preview' => [
            ['kicker' => 'Secao 3', 'titulo' => 'Distribuicao final de severidade', 'descricao' => 'Grafico principal com a quantidade absoluta por gravidade.', 'variant' => 'wide'],
            ['kicker' => 'Secao 4', 'titulo' => 'Proporcao por grau de severidade', 'descricao' => 'Leitura percentual da composicao do recorte.', 'variant' => 'half'],
            ['kicker' => 'Secao 5', 'titulo' => 'Duracao media por tipo de evento', 'descricao' => 'Compara em horas os eventos com maior permanencia.', 'variant' => 'half'],
            ['kicker' => 'Secao 6', 'titulo' => 'Alertas por evento e municipios impactados', 'descricao' => 'Cruza volume de emissoes com os territorios mais pressionados.', 'variant' => 'wide'],
        ],
        'rodape' => 'Recomendado para medir carga operacional e identificar territorios onde a resposta pode ficar mais pressionada.',
    ],
    'tipologia' => [
        'hero_kicker' => 'Analise operacional',
        'pagina_titulo' => 'Tipologia de alertas',
        'pagina_descricao' => 'Observe os tipos de evento mais recorrentes, sua correlacao com a severidade e a distribuicao territorial por regioes e municipios.',
        'resumo' => [
            ['label' => 'Tipologias', 'value' => 'Eventos no recorte', 'note' => 'Resume as tipologias de alerta presentes no periodo analisado.'],
            ['label' => 'Territorio', 'value' => 'Regioes e municipios', 'note' => 'Mostra onde cada evento aparece com maior recorrencia.'],
            ['label' => 'Severidade', 'value' => 'Correlacao ativa', 'note' => 'Conecta tipologias com as faixas de gravidade observadas.'],
        ],
        'filtros_titulo' => 'Filtros do recorte analitico',
        'filtros_texto' => 'Ano, mes, regiao e municipio recalculam automaticamente a leitura tipologica.',
        'insights_titulo' => 'Leituras rapidas do periodo',
        'insights_texto' => 'A pagina destaca a tipologia dominante e os territorios com maior recorrencia para cada padrao de evento.',
        'insights' => [
            ['label' => 'Evento mais recorrente', 'value' => 'Tipologia principal', 'note' => 'Aponta o tipo de evento com maior volume no recorte.'],
            ['label' => 'Recorrencia regional', 'value' => 'Regiao lider', 'note' => 'Mostra a regiao com maior concentracao tipologica.'],
            ['label' => 'Recorrencia municipal', 'value' => 'Municipio lider', 'note' => 'Evidencia o municipio mais impactado por aquele padrao de evento.'],
        ],
        'graficos_preview' => [
            ['kicker' => 'Secao 3', 'titulo' => 'Correlacao entre evento e severidade', 'descricao' => 'Matriz visual para comparar tipologias e faixas de gravidade.', 'variant' => 'wide'],
            ['kicker' => 'Secao 4', 'titulo' => 'Distribuicao das tipologias', 'descricao' => 'Mostra a composicao total por tipo de evento.', 'variant' => 'half'],
            ['kicker' => 'Secao 5', 'titulo' => 'Tipologia por regiao e sazonalidade', 'descricao' => 'Cruza regioes, eventos e comportamento temporal.', 'variant' => 'half'],
            ['kicker' => 'Secao 6', 'titulo' => 'Recorrencia municipal por tipologia', 'descricao' => 'Indica onde cada evento se concentra com maior intensidade.', 'variant' => 'wide'],
        ],
        'rodape' => 'Use essa pagina quando precisar entender padroes de evento e sua distribuicao territorial com mais profundidade.',
    ],
    'indices' => [
        'hero_kicker' => 'Inteligencia estrategica',
        'pagina_titulo' => 'Indices de risco (IRP / IPT)',
        'pagina_descricao' => 'Acompanhe a pressao regional e territorial provocada pelos alertas multirriscos, com leitura consolidada por periodo e metodologia em modal.',
        'resumo' => [
            ['label' => 'IRP', 'value' => 'Pressao regional', 'note' => 'Mostra a carga operacional media nas regioes de integracao.'],
            ['label' => 'IPT', 'value' => 'Carga municipal', 'note' => 'Resume a intensidade territorial acumulada nos municipios.'],
            ['label' => 'Contexto', 'value' => 'Priorizacao territorial', 'note' => 'Facilita decidir onde monitorar e responder primeiro.'],
        ],
        'filtros_titulo' => 'Filtros do recorte analitico',
        'filtros_texto' => 'Ano, mes, regiao e municipio recalculam automaticamente o IRP e o IPT no mesmo painel.',
        'insights_titulo' => 'Leituras rapidas do periodo',
        'insights_texto' => 'A pagina destaca lideres de pressao, comportamento do recorte atual e acesso imediato a metodologia dos indices.',
        'insights' => [
            ['label' => 'Regiao com maior IRP', 'value' => 'Lider regional', 'note' => 'Indica a regiao com maior pressao operacional no recorte.'],
            ['label' => 'Municipio com maior IPT', 'value' => 'Lider territorial', 'note' => 'Mostra o municipio com maior carga acumulada.'],
            ['label' => 'Metodologia', 'value' => 'IRP e IPT explicados', 'note' => 'O painel possui modal proprio para interpretar os calculos.'],
        ],
        'graficos_preview' => [
            ['kicker' => 'Secao 3', 'titulo' => 'Ranking regional de pressao', 'descricao' => 'Grafico principal comparando as regioes pelo IRP.', 'variant' => 'half'],
            ['kicker' => 'Secao 4', 'titulo' => 'Ranking territorial de pressao', 'descricao' => 'Grafico principal comparando municipios pelo IPT.', 'variant' => 'half'],
            ['kicker' => 'Destaque', 'titulo' => 'Metodologia em modal', 'descricao' => 'A pagina traz explicacao detalhada sobre como os indices sao calculados.', 'variant' => 'wide'],
        ],
        'rodape' => 'Recomendado para priorizacao estrategica e decisao sobre onde a pressao multirriscos exige resposta mais imediata.',
    ],
];

foreach ($analises as &$analise) {
    $slugAnalise = (string) ($analise['slug'] ?? '');

    if (isset($analisesPreview[$slugAnalise])) {
        $analise['preview'] = $analisesPreview[$slugAnalise];
    }
}
unset($analise);
$cssPainelBasePath = __DIR__ . '/assets/css/painel.css';
$cssPainelPagePath = __DIR__ . '/assets/css/pages/painel.css';
$cssAlertasFormPath = __DIR__ . '/assets/css/pages/alertas-form.css';
$cssAnalisesIndexPath = __DIR__ . '/assets/css/pages/analises-index.css';
$cssMapaMultirriscosPath = __DIR__ . '/assets/css/mapa_multirriscos.css';
$cssLoginPath = __DIR__ . '/assets/css/login.css';
$cssPainelBaseVersion = (string) ((int) @filemtime($cssPainelBasePath));
$cssPainelPageVersion = (string) ((int) @filemtime($cssPainelPagePath));
$cssAlertasFormVersion = (string) ((int) @filemtime($cssAlertasFormPath));
$cssAnalisesIndexVersion = (string) ((int) @filemtime($cssAnalisesIndexPath));
$cssMapaMultirriscosVersion = (string) ((int) @filemtime($cssMapaMultirriscosPath));
$cssLoginVersion = (string) ((int) @filemtime($cssLoginPath));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($appConfig['name'], ENT_QUOTES, 'UTF-8') ?> - Defesa Civil do Estado do Para</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="google-site-verification" content="n12nK-Z0mLxGkBdZgzgKzSED9LVYlsNL-8JJKt-Vo9g">
<link rel="icon" type="image/x-icon" href="/assets/images/logo.cbmpa.ico">
<link rel="preconnect" href="https://unpkg.com" crossorigin>
<link rel="preconnect" href="https://tile.openstreetmap.org" crossorigin>
<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/painel.css?v=<?= htmlspecialchars($cssPainelBaseVersion, ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="/assets/css/pages/painel.css?v=<?= htmlspecialchars($cssPainelPageVersion, ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="/assets/css/pages/alertas-form.css?v=<?= htmlspecialchars($cssAlertasFormVersion, ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="/assets/css/pages/analises-index.css?v=<?= htmlspecialchars($cssAnalisesIndexVersion, ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="/assets/css/mapa_multirriscos.css?v=<?= htmlspecialchars($cssMapaMultirriscosVersion, ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="/assets/css/login.css?v=<?= htmlspecialchars($cssLoginVersion, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="public-home-page">
<header class="public-topbar">
    <a href="#inicio" class="public-brand">
        <img src="/assets/images/logo-cedec.png" alt="CEDEC-PA">
        <span class="public-brand-copy">
            <small><?= htmlspecialchars($appConfig['name'], ENT_QUOTES, 'UTF-8') ?></small>
            <strong><?= htmlspecialchars($appConfig['institution'], ENT_QUOTES, 'UTF-8') ?></strong>
            <em><?= htmlspecialchars($appConfig['department'], ENT_QUOTES, 'UTF-8') ?></em>
        </span>
    </a>

    <nav class="public-nav" aria-label="Navegacao publica">
        <a href="#mapa-publico">Mapa ao vivo</a>
        <a href="#analises-publicas">Relatorio analitico</a>
        <a href="#alertas-ativos">Alertas ativos</a>
    </nav>

    <div class="public-topbar-actions">
        <span class="topbar-pill topbar-pill-neutral">Versao <?= htmlspecialchars($appConfig['version'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php if (is_array($usuarioAtivo) && !empty($usuarioAtivo['nome'])): ?>
            <a href="/pages/painel.php" class="btn btn-primary">Acessar painel</a>
        <?php endif; ?>
    </div>
</header>

<main class="public-home" id="inicio">
    <section class="public-hero">
        <div class="public-hero-copy">
            <span class="alerta-form-kicker">Painel institucional aberto</span>
            <h1>Monitoramento multirriscos publico<br>Defesa Civil do Para</h1>
            <p>
                Consulte o mapa multirriscos em destaque, leia os indicadores territoriais, gere o relatorio analitico na tela e baixe os PDFs dos alertas ativos sem precisar estar logado.
            </p>

            <div class="public-hero-actions">
                <a href="#mapa-publico" class="btn btn-primary">Explorar o mapa</a>
                <a href="#alertas-ativos" class="btn btn-secondary">Baixar alertas ativos</a>
                <?php if (is_array($usuarioAtivo) && !empty($usuarioAtivo['nome'])): ?>
                    <a href="/pages/painel.php" class="btn btn-secondary">Abrir ambiente operacional</a>
                <?php else: ?>
                    <a href="#analises-publicas" class="btn btn-secondary">Ver analise</a>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary" data-open-info>Sobre o sistema</button>
            </div>

            <?php if ($mensagemSessao !== ''): ?>
                <div class="public-banner public-banner-warning"><?= htmlspecialchars($mensagemSessao, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <div class="public-highlight-grid">
                <article class="public-highlight-card">
                    <span class="alerta-summary-label">Alertas ativos</span>
                    <strong class="alerta-summary-value"><?= $totalAtivos ?></strong>
                    <span class="alerta-summary-note">Alertas vigentes e disponiveis para consulta e download em PDF.</span>
                </article>

                <article class="public-highlight-card">
                    <span class="alerta-summary-label">Cobertura territorial</span>
                    <strong class="alerta-summary-value"><?= count($municipiosDisponiveis) ?> municipios</strong>
                    <span class="alerta-summary-note"><?= count($regioesDisponiveis) ?> regioes de integracao monitoradas.</span>
                </article>

                <article class="public-highlight-card">
                    <span class="alerta-summary-label">Mapa pronto</span>
                    <strong class="alerta-summary-value"><?= $totalAlertasMapeados ?> areas mapeadas</strong>
                    <span class="alerta-summary-note">Leitura cartografica com foco territorial e filtros analiticos.</span>
                </article>

                <article class="public-highlight-card">
                    <span class="alerta-summary-label">Ultimo alerta ativo</span>
                    <strong class="alerta-summary-value"><?= htmlspecialchars((string) ($alertaMaisRecente['numero'] ?? 'Sem alerta ativo'), ENT_QUOTES, 'UTF-8') ?></strong>
                    <span class="alerta-summary-note">
                        <?= $alertaMaisRecente
                            ? htmlspecialchars((string) ($alertaMaisRecente['tipo_evento'] ?? ''), ENT_QUOTES, 'UTF-8') . ' em ' . htmlspecialchars(TimeHelper::formatDate((string) ($alertaMaisRecente['data_alerta'] ?? null)), ENT_QUOTES, 'UTF-8')
                            : 'Nao ha registros ativos no momento.' ?>
                    </span>
                </article>
            </div>
        </div>

        <aside class="public-access-card public-access-summary-card" id="acesso-sistema">
            <?php if (is_array($usuarioAtivo) && !empty($usuarioAtivo['nome'])): ?>
                <span class="public-access-kicker">Sessao ativa</span>
                <h2><?= htmlspecialchars((string) $usuarioAtivo['nome'], ENT_QUOTES, 'UTF-8') ?></h2>
                <p>Seu acesso autenticado esta ativo. Use os atalhos abaixo para entrar no ambiente operacional ou encerrar a sessao.</p>
                <div class="public-access-meta">
                    <span class="analises-chip">Perfil: <?= htmlspecialchars((string) ($usuarioAtivo['perfil'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="analises-chip">Timeout: <?= (int) (Session::inactivityTimeout() / 60) ?> min</span>
                </div>
                <div class="public-access-actions">
                    <a href="/pages/painel.php" class="btn btn-primary">Ir para o painel</a>
                    <a href="/logout.php" class="btn btn-secondary">Encerrar sessao</a>
                </div>
            <?php else: ?>
                <span class="public-access-kicker">Acesso protegido</span>
                <h2>Ambiente operacional com autenticacao</h2>
                <p>O visitante consulta o painel aberto. O usuario autenticado acessa cadastro, gestao, historico e os paineis analiticos completos do sistema.</p>
                <div class="public-access-meta">
                    <span class="analises-chip">Mapa ao vivo</span>
                    <span class="analises-chip">Relatorio em modal</span>
                    <span class="analises-chip">PDFs ativos</span>
                </div>
                <div class="public-access-feature-list">
                    <article class="public-access-feature">
                        <strong>Consulta publica</strong>
                        <span>Mapa multirriscos, leitura territorial e relatorio consolidado liberados para qualquer visitante.</span>
                    </article>
                    <article class="public-access-feature">
                        <strong>Acesso autenticado</strong>
                        <span>Cadastro e gerenciamento operacional continuam reservados aos perfis internos do sistema.</span>
                    </article>
                </div>
                <?php if ($erro !== ''): ?>
                    <div class="public-banner public-banner-danger public-auth-banner"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <form method="post" class="public-access-login-form">
                    <?= Csrf::inputField() ?>

                    <div class="public-access-login-grid">
                        <div class="public-auth-field">
                            <label for="login-email">E-mail institucional</label>
                            <input type="email" id="login-email" name="email" value="<?= htmlspecialchars($emailInformado, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="username">
                        </div>

                        <div class="public-auth-field">
                            <label for="login-senha">Senha</label>
                            <input type="password" id="login-senha" name="senha" required autocomplete="current-password">
                        </div>
                    </div>

                    <div class="public-access-actions">
                        <button type="submit" class="btn btn-primary">Entrar no sistema</button>
                        <button type="reset" class="btn btn-secondary">Limpar</button>
                    </div>
                </form>

                <div class="public-access-support">
                    <strong>Solicitacao de acesso</strong>
                    <a href="mailto:dgr.cedecpa@gmail.com">dgr.cedecpa@gmail.com</a>
                </div>
            <?php endif; ?>
        </aside>
    </section>

    <section class="dashboard alerta-form-shell public-section" id="mapa-publico">
        <div class="alerta-form-hero public-section-hero">
            <div class="alerta-form-lead">
                <span class="alerta-form-kicker">Mapa em destaque</span>
                <h2 class="alerta-form-title">Mapa multirriscos com leitura territorial aberta ao publico</h2>
                <p class="alerta-form-description">
                    A tela abaixo segue o mesmo idioma visual das areas internas de painel e listagem, com o mapa como elemento central, filtros sincronizados, ranking regional e detalhamento territorial em modal.
                </p>
            </div>

            <div class="alerta-form-summary">
                <div class="alerta-summary-card">
                    <span class="alerta-summary-label">Monitoramento ativo</span>
                    <span class="alerta-summary-value" id="hero-alertas-ativos"><?= $totalAtivos ?> alertas</span>
                    <span class="alerta-summary-note">Total de alertas ativos disponiveis para leitura territorial nesta tela publica.</span>
                </div>
                <div class="alerta-summary-card">
                    <span class="alerta-summary-label">Base territorial</span>
                    <span class="alerta-summary-value"><?= count($municipiosDisponiveis) ?> municipios</span>
                    <span class="alerta-summary-note">Cobertura completa em <?= count($regioesDisponiveis) ?> regioes de integracao.</span>
                </div>
                <div class="alerta-summary-card">
                    <span class="alerta-summary-label">Foco atual</span>
                    <span class="alerta-summary-value" id="hero-foco-value">Sem recorte territorial</span>
                    <span class="alerta-summary-note" id="hero-foco-note">Selecione regiao ou municipio para destacar automaticamente o recorte.</span>
                </div>
            </div>
        </div>

        <div class="alerta-form-panel">
            <div class="alerta-form-grid multirrisco-overview-grid">
                <section class="alerta-form-section">
                    <header class="alerta-section-header">
                        <span class="alerta-section-kicker">Secao 1</span>
                        <h2 class="alerta-section-title">Filtros e recorte territorial</h2>
                        <p class="alerta-section-text">Os filtros abaixo sincronizam mapa, indicadores, ranking regional, grafico de pressao e detalhamento territorial.</p>
                    </header>

                    <form id="multirrisco-form" class="multirrisco-filter-form">
                        <div class="multirrisco-filter-grid">
                            <div class="form-group">
                                <label for="data_inicio">Periodo inicial</label>
                                <input type="date" id="data_inicio" name="data_inicio">
                            </div>
                            <div class="form-group">
                                <label for="data_fim">Periodo final</label>
                                <input type="date" id="data_fim" name="data_fim">
                            </div>
                            <div class="form-group">
                                <label for="tipo_evento">Tipo de evento</label>
                                <select id="tipo_evento" name="tipo_evento">
                                    <option value="">Todos os eventos</option>
                                    <?php foreach ($eventosDisponiveis as $evento): ?>
                                        <option value="<?= htmlspecialchars($evento, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($evento, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="gravidade">Gravidade</label>
                                <select id="gravidade" name="gravidade">
                                    <option value="">Todas as gravidades</option>
                                    <?php foreach ($gravidadesDisponiveis as $gravidade): ?>
                                        <option value="<?= htmlspecialchars($gravidade, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($gravidade, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="fonte">Fonte</label>
                                <select id="fonte" name="fonte">
                                    <option value="">Todas as fontes</option>
                                    <?php foreach ($fontesDisponiveis as $fonte): ?>
                                        <option value="<?= htmlspecialchars($fonte, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($fonte, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="regiao">Regiao</label>
                                <select id="regiao" name="regiao">
                                    <option value="">Todas as regioes</option>
                                    <?php foreach ($regioesDisponiveis as $regiao): ?>
                                        <option value="<?= htmlspecialchars($regiao, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($regiao, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="municipio">Municipio</label>
                                <select id="municipio" name="municipio">
                                    <option value="">Todos os municipios</option>
                                </select>
                                <span class="field-helper">Ao selecionar uma regiao, a lista municipal e refinada automaticamente.</span>
                            </div>
                        </div>

                        <div class="alerta-form-actions multirrisco-filter-actions">
                            <div class="alerta-form-actions-left">
                                <span class="alerta-inline-note" id="resumo-filtros">Filtros ativos: sem recorte adicional.</span>
                            </div>
                            <div class="alerta-form-actions-right">
                                <button type="button" class="btn btn-secondary" id="btnLimparFiltros">Limpar filtros</button>
                                <button type="submit" class="btn btn-primary">Aplicar filtros</button>
                                <button type="button" class="btn btn-secondary" id="btnAbrirAjuda">Como usar</button>
                            </div>
                        </div>
                    </form>
                </section>

                <section class="alerta-form-section">
                    <header class="alerta-section-header">
                        <span class="alerta-section-kicker">Secao 2</span>
                        <h2 class="alerta-section-title">Leitura operacional</h2>
                        <p class="alerta-section-text">Organize a leitura do cenario em um fluxo simples: entenda a carga territorial, identifique o foco e avance para o detalhe no mapa.</p>
                    </header>

                    <div class="public-operational-layout">
                        <div class="painel-kpi-grid multirrisco-kpi-grid">
                            <article class="painel-kpi-card is-active">
                                <span class="painel-kpi-label">Alertas ativos</span>
                                <strong class="painel-kpi-value" id="kpi-ativos">-</strong>
                                <span class="painel-kpi-note">Total de alertas no recorte atual.</span>
                            </article>
                            <article class="painel-kpi-card">
                                <span class="painel-kpi-label">Municipios em risco</span>
                                <strong class="painel-kpi-value" id="kpi-municipios">-</strong>
                                <span class="painel-kpi-note">Municipios com alertas ativos no recorte consultado.</span>
                            </article>
                            <article class="painel-kpi-card is-neutral">
                                <span class="painel-kpi-label">Regioes afetadas</span>
                                <strong class="painel-kpi-value" id="kpi-regioes">-</strong>
                                <span class="painel-kpi-note">Regioes de integracao alcancadas pelo recorte atual.</span>
                            </article>
                            <article class="painel-kpi-card is-warning multirrisco-focus-card">
                                <span class="painel-kpi-label">Territorio em foco</span>
                                <strong class="painel-kpi-value" id="foco-territorial-titulo">Nenhum foco definido</strong>
                                <span class="painel-kpi-note" id="foco-territorial-texto">Clique em uma regiao, municipio ou item do ranking para abrir o detalhe.</span>
                            </article>
                        </div>

                        <div class="public-operational-stack">
                            <article class="public-operational-card">
                                <span class="public-operational-step">Passo 1</span>
                                <h3>Defina o cenario</h3>
                                <p>Use periodo, evento, gravidade, fonte, regiao e municipio para reduzir o mapa ao recorte realmente necessario.</p>
                            </article>

                            <article class="public-operational-card">
                                <span class="public-operational-step">Passo 2</span>
                                <h3>Leia a pressao ativa</h3>
                                <p>Os indicadores mostram rapidamente o volume de alertas, a abrangencia territorial e o foco principal da consulta.</p>
                            </article>

                            <article class="public-operational-card public-operational-card-highlight">
                                <span class="public-operational-step">Passo 3</span>
                                <h3>Abra o detalhe territorial</h3>
                                <p>Depois de identificar a area critica, clique no mapa ou no ranking para abrir na tela com os alertas ativos discriminados.</p>
                            </article>
                        </div>
                    </div>

                    <div class="alerta-callout multirrisco-callout">
                        <strong>Leitura recomendada</strong>
                        Escolha primeiro o recorte territorial. Ao selecionar uma regiao, o mapa destaca a camada regional; ao selecionar um municipio, o destaque migra automaticamente para o territorio municipal.
                    </div>
                </section>
            </div>
        </div>

        <section class="alerta-form-panel multirrisco-map-panel">
            <header class="painel-section-head">
                <div class="alerta-section-header">
                    <span class="alerta-section-kicker">Secao 3</span>
                    <h2 class="alerta-section-title">Mapa territorial e pressao ativa</h2>
                    <p class="alerta-section-text">O mapa ocupa o maior protagonismo da tela para facilitar navegacao, leitura de pressao e abertura do detalhamento territorial.</p>
                </div>

                <div class="painel-map-meta">
                    <span class="painel-chip" id="chip-modo-territorial">Modo: municipios</span>
                    <span class="painel-chip" id="chip-filtro-territorial">Sem recorte territorial</span>
                </div>
            </header>

            <div class="multirrisco-map-layout">
                <div class="map-card multirrisco-map-card">
                    <div class="map-card-header">
                        <div>
                            <span class="map-card-title">Mapa multirriscos do Para</span>
                            <p class="map-card-text">Alertas ativos, pressao territorial, regioes de integracao e municipios se atualizam juntos, com destaque automatico a partir do filtro selecionado.</p>
                        </div>
                        <div class="multirrisco-toolbar-status" id="status-atualizacao">Pronto para consulta publica</div>
                    </div>

                    <div class="multirrisco-toolbar">
                        <div class="multirrisco-toolbar-group">
                            <span class="multirrisco-toolbar-label">Camadas</span>
                            <label class="multirrisco-toggle">
                                <input type="checkbox" id="toggle-alertas" checked>
                                <span>Alertas ativos</span>
                            </label>
                        </div>
                        <div class="multirrisco-toolbar-group multirrisco-toolbar-group-segmented">
                            <span class="multirrisco-toolbar-label">Recorte territorial</span>
                            <label class="multirrisco-segment">
                                <input type="radio" name="modoTerritorial" value="municipios" checked>
                                <span>Municipios</span>
                            </label>
                            <label class="multirrisco-segment">
                                <input type="radio" name="modoTerritorial" value="regioes">
                                <span>Regioes</span>
                            </label>
                        </div>
                    </div>

                    <div class="multirrisco-map-stage public-map-stage">
                        <div id="mapa" class="alerta-map multirrisco-map-canvas"></div>
                        <div class="multirrisco-map-loading" id="mapaLoading" hidden>Atualizando mapa e indicadores...</div>
                    </div>
                </div>

                <aside class="multirrisco-side-stack">
                    <section class="alerta-form-section multirrisco-side-section">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Ranking</span>
                            <h2 class="alerta-section-title">Regioes mais pressionadas</h2>
                            <p class="alerta-section-text">Clique em uma regiao da lista para centralizar o mapa e abrir o detalhamento regional.</p>
                        </header>

                        <div id="lista-regioes" class="lista-regioes">
                            <div class="multirrisco-empty-box">Carregando leitura regional...</div>
                        </div>
                    </section>

                    <section class="alerta-form-section multirrisco-side-section">
                        <div id="filtro-dia-ativo" class="multirrisco-day-filter" hidden>
                            <span>Filtro ativo para o dia: <strong id="diaSelecionadoTxt"></strong></span>
                            <button type="button" class="btn btn-secondary" id="btnLimparFiltroDia">Limpar</button>
                        </div>

                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Serie diaria</span>
                            <h2 class="alerta-section-title">Evolucao do IRP</h2>
                            <p class="alerta-section-text">Clique em um ponto do grafico para refinar o mapa por um dia especifico.</p>
                        </header>

                        <div class="grafico-container multirrisco-chart-container">
                            <canvas id="graficoLinhaTempo"></canvas>
                        </div>

                        <div class="alerta-form-actions multirrisco-chart-actions">
                            <div class="alerta-form-actions-left">
                                <span class="alerta-inline-note">O IRP combina gravidade e abrangencia territorial dos alertas ativos.</span>
                            </div>
                            <div class="alerta-form-actions-right">
                                <button type="button" class="btn btn-secondary" id="btnAbrirIRP">Entender o IRP</button>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
        </section>
    </section>

    <section class="dashboard alerta-form-shell public-section" id="analises-publicas">
        <div class="alerta-form-hero public-section-hero">
            <div class="alerta-form-lead">
                <span class="alerta-form-kicker">Central analitica</span>
                <h2 class="alerta-form-title">Relatorio analitico multirriscos em modal</h2>
                <p class="alerta-form-description">
                    A consulta abaixo abre um relatorio consolidado direto na tela, e sem
                    depender de login. O objetivo aqui e leitura institucional e consulta publica qualificada.
                </p>
            </div>

            <div class="alerta-form-summary">
                <div class="alerta-summary-card">
                    <span class="alerta-summary-label">Modulos integrados</span>
                    <span class="alerta-summary-value"><?= count($analises) ?> leituras</span>
                    <span class="alerta-summary-note">Temporal, severidade, tipologia e indices em um mesmo fluxo.</span>
                </div>
                <div class="alerta-summary-card">
                    <span class="alerta-summary-label">Recorte global</span>
                    <span class="alerta-summary-value">Ano, mes, regiao e municipio</span>
                    <span class="alerta-summary-note">O mesmo recorte alimenta todos os blocos da tela analitico.</span>
                </div>
                <div class="alerta-summary-card">
                    <span class="alerta-summary-label">Experiencia publica</span>
                    <span class="alerta-summary-value">Visual e responsiva</span>
                    <span class="alerta-summary-note">Feita para leitura rapida sem expor funcoes operacionais internas.</span>
                </div>
            </div>
        </div>

        <div class="alerta-form-panel">
            <div class="alerta-form-grid analises-overview-grid">
                <section class="alerta-form-section">
                    <header class="alerta-section-header">
                        <span class="alerta-section-kicker">Secao 1</span>
                        <h2 class="alerta-section-title">Recorte global do relatorio</h2>
                        <p class="alerta-section-text">Defina o recorte desejado para abrir a sintese analitica consolidada na tela.</p>
                    </header>

                    <div class="analises-filter-grid">
                        <div class="form-group">
                            <label for="filtroAno">Ano</label>
                            <select id="filtroAno"></select>
                        </div>
                        <div class="form-group">
                            <label for="filtroMes">Mes</label>
                            <select id="filtroMes"></select>
                        </div>
                        <div class="form-group">
                            <label for="filtroRegiao">Regiao de integracao</label>
                            <select id="filtroRegiao"></select>
                        </div>
                        <div class="form-group">
                            <label for="filtroMunicipio">Municipio</label>
                            <select id="filtroMunicipio">
                                <option value="">Todos</option>
                            </select>
                        </div>

                        <div class="alerta-callout analises-filter-callout form-group field-span-2">
                            <strong>Relatorio publico</strong>
                            A tela abaixo organiza a leitura consolidada para consulta institucional sem funcao de impressao.
                        </div>

                        <div class="alerta-form-actions analises-filter-actions form-group field-span-2">
                            <div class="alerta-form-actions-left">
                                <span class="alerta-inline-note">Use esse recorte para abrir um panorama integrado antes de aprofundar a leitura no mapa.</span>
                            </div>
                            <div class="alerta-form-actions-right">
                                <button id="btnGerarRelatorio" type="button" class="btn btn-primary">Gerar relatorio analitico</button>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="alerta-form-section">
                    <header class="alerta-section-header">
                        <span class="alerta-section-kicker">Secao 2</span>
                        <h2 class="alerta-section-title">Como a analise esta organizada</h2>
                        <p class="alerta-section-text">Clique em um modulo para abrir a previa da pagina correspondente, com filtros, cards e blocos graficos no mesmo desenho da tela analitica real.</p>
                    </header>

                    <div class="analises-insight-grid public-analises-insight-grid">
                        <?php foreach ($analises as $analise): ?>
                            <button
                                type="button"
                                class="alerta-summary-card analises-mini-card public-analise-card"
                                data-analise-preview="<?= htmlspecialchars($analise['slug'], ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <span class="alerta-summary-label"><?= htmlspecialchars($analise['kicker'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="alerta-summary-value"><?= htmlspecialchars($analise['titulo'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="alerta-summary-note"><?= htmlspecialchars($analise['descricao'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="public-analise-card-footer">
                                    <span class="analises-chip"><?= count($analise['graficos']) ?> graficos</span>
                                    <span class="public-analise-card-link">Ver pagina em modal</span>
                                </span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="alerta-callout analises-info-callout">
                        <strong>Uso recomendado</strong>
                        Combine o relatorio consolidado com a previa de cada pagina para decidir rapidamente qual analise detalhada faz mais sentido para o recorte consultado.
                    </div>
                </section>
            </div>
        </div>
    </section>

    <section class="dashboard public-section" id="alertas-ativos">
        <div class="public-section-header">
            <div>
                <span class="alerta-form-kicker">Download publico</span>
                <h2>Alertas ativos disponiveis em PDF</h2>
                <p>Somente alertas com status ativo aparecem nesta vitrine publica. Cada card libera o download direto do PDF oficial do alerta.</p>
            </div>

            <div class="public-section-meta">
                <span class="analises-chip"><?= count($alertasPublicos) ?> alertas destacados</span>
                <span class="analises-chip"><?= $totalAtivos ?> PDFs ativos</span>
            </div>
        </div>

        <?php if (!$alertasPublicos): ?>
            <div class="multirrisco-empty-box">Nao ha alertas ativos disponiveis para download no momento.</div>
        <?php else: ?>
            <div class="public-alert-grid">
                <?php foreach ($alertasPublicos as $alerta): ?>
                    <article class="public-alert-card">
                        <div class="public-alert-head">
                            <div>
                                <span class="public-alert-number"><?= htmlspecialchars((string) $alerta['numero'], ENT_QUOTES, 'UTF-8') ?></span>
                                <h3><?= htmlspecialchars((string) $alerta['tipo_evento'], ENT_QUOTES, 'UTF-8') ?></h3>
                            </div>
                            <span class="public-alert-severity severity-<?= strtolower(str_replace(' ', '-', (string) $alerta['nivel_gravidade'])) ?>">
                                <?= htmlspecialchars((string) $alerta['nivel_gravidade'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>

                        <div class="public-alert-meta">
                            <span><strong>Fonte:</strong> <?= htmlspecialchars((string) $alerta['fonte'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span><strong>Data:</strong> <?= htmlspecialchars(TimeHelper::formatDate((string) ($alerta['data_alerta'] ?? null)), ENT_QUOTES, 'UTF-8') ?></span>
                            <span><strong>Vigencia:</strong> <?= htmlspecialchars(TimeHelper::formatDateTime((string) ($alerta['inicio_alerta'] ?? null)), ENT_QUOTES, 'UTF-8') ?> ate <?= htmlspecialchars(TimeHelper::formatDateTime((string) ($alerta['fim_alerta'] ?? null)), ENT_QUOTES, 'UTF-8') ?></span>
                            <span><strong>Municipios:</strong> <?= (int) ($alerta['total_municipios'] ?? 0) ?></span>
                        </div>

                        <p class="public-alert-region">
                            <strong>Regioes:</strong>
                            <?= htmlspecialchars((string) ($alerta['regioes'] ?: 'Nao informadas'), ENT_QUOTES, 'UTF-8') ?>
                        </p>

                        <div class="public-alert-actions">
                            <a href="/pages/alertas/pdf.php?id=<?= (int) $alerta['id'] ?>&download=1" class="btn btn-primary" target="_blank" rel="noopener">
                                Baixar PDF do alerta
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<footer class="public-footer">
    <div class="public-footer-brand">
        <span class="public-footer-kicker">Painel institucional aberto</span>
        <strong><?= htmlspecialchars($appConfig['organization'], ENT_QUOTES, 'UTF-8') ?></strong>
        <p><?= htmlspecialchars($appConfig['department'], ENT_QUOTES, 'UTF-8') ?> com acesso visual ao mapa multirriscos, relatorio consolidado e PDFs dos alertas ativos.</p>
        <div class="public-footer-pills">
            <span class="analises-chip"><?= htmlspecialchars($appConfig['name'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="analises-chip">Versao <?= htmlspecialchars($appConfig['version'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>
    <div>
        <?= htmlspecialchars($appConfig['name'], ENT_QUOTES, 'UTF-8') ?> · Versao <strong><?= htmlspecialchars($appConfig['version'], ENT_QUOTES, 'UTF-8') ?></strong>
    </div>
    <div>
        Suporte: <a href="mailto:<?= htmlspecialchars($appConfig['support_email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($appConfig['support_email'], ENT_QUOTES, 'UTF-8') ?></a>
    </div>
    <div class="public-footer-grid">
        <section class="public-footer-card">
            <span class="public-footer-kicker">Navegacao rapida</span>
            <a href="#mapa-publico">Mapa ao vivo</a>
            <a href="#analises-publicas">Analises e relatorio</a>
            <a href="#alertas-ativos">Alertas ativos</a>
        </section>

        <section class="public-footer-card">
            <span class="public-footer-kicker">Experiencia publica</span>
            <p>Consulta territorial, leitura analitica e download dos PDFs ativos sem necessidade de autenticacao.</p>
        </section>

        <section class="public-footer-card">
            <span class="public-footer-kicker">Acesso e suporte</span>
            <p>Suporte institucional para acesso autenticado e orientacoes operacionais.</p>
            <a href="mailto:<?= htmlspecialchars($appConfig['support_email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($appConfig['support_email'], ENT_QUOTES, 'UTF-8') ?></a>
        </section>
    </div>
</footer>

<div id="modalTerritorio" class="modal-territorio" aria-hidden="true">
    <div class="modal-territorio-dialog" role="dialog" aria-modal="true" aria-labelledby="modalTerritorioTitulo">
        <div class="modal-territorio-header">
            <div class="modal-territorio-header-copy">
                <span class="modal-territorio-kicker" id="modalTerritorioKicker">Territorio</span>
                <h3 id="modalTerritorioTitulo">Detalhamento territorial</h3>
                <p id="modalTerritorioResumo">Carregando detalhamento do territorio selecionado.</p>
            </div>
            <button type="button" class="modal-territorio-close" data-close-territorio aria-label="Fechar modal">X</button>
        </div>
        <div class="modal-territorio-body" id="modalTerritorioBody"></div>
    </div>
</div>

<div id="modalIRP" class="modal">
    <div class="modal-conteudo">
        <h3>Como o IRP e calculado</h3>
        <p>O indice de pressao de risco mede a carga operacional causada pelos alertas ativos no territorio filtrado.</p>
        <p>A leitura considera o peso da gravidade e a abrangencia municipal de cada alerta ativo.</p>
        <ul>
            <li>Baixo = 1</li>
            <li>Moderado = 2</li>
            <li>Alto = 3</li>
            <li>Muito alto = 4</li>
            <li>Extremo = 5</li>
        </ul>
        <p>Quanto maior o IRP, maior a pressao territorial e a necessidade de resposta.</p>
        <button type="button" data-close-irp>Fechar</button>
    </div>
</div>

<div id="modalAjuda" class="modal-ajuda">
    <div class="modal-ajuda-conteudo">
        <div class="modal-ajuda-header">
            <div class="modal-ajuda-heading">
                <span class="modal-ajuda-kicker">Guia rapido</span>
                <h3>Como usar o mapa multirriscos</h3>
                <p>Um roteiro simples para filtrar, interpretar o mapa e abrir o detalhamento territorial com rapidez.</p>
            </div>
            <button type="button" data-close-ajuda aria-label="Fechar ajuda">X</button>
        </div>

        <div class="modal-ajuda-body">
            <section class="modal-ajuda-hero">
                <div class="modal-ajuda-hero-copy">
                    <strong>Leitura recomendada</strong>
                    <p>Comece pelos filtros, use o mapa como referencia central e abra os modais territoriais para entender cada alerta ativo.</p>
                </div>
                <div class="modal-ajuda-pill-row">
                    <span class="modal-ajuda-pill">1. Filtre o cenario</span>
                    <span class="modal-ajuda-pill">2. Observe o destaque territorial</span>
                    <span class="modal-ajuda-pill">3. Abra o detalhamento</span>
                </div>
            </section>

            <div class="modal-ajuda-grid">
                <article class="modal-ajuda-card modal-ajuda-card-flow">
                    <span class="modal-ajuda-card-kicker">Passo a passo</span>
                    <h4>Fluxo ideal de uso</h4>
                    <ol class="modal-ajuda-sequencia">
                        <li>Defina periodo, evento, gravidade, fonte, regiao ou municipio.</li>
                        <li>Observe o destaque automatico da camada territorial no mapa.</li>
                        <li>Use o ranking regional para priorizar a leitura das areas mais pressionadas.</li>
                        <li>Clique no territorio para abrir o detalhamento com todos os alertas ativos.</li>
                    </ol>
                </article>

                <article class="modal-ajuda-card">
                    <span class="modal-ajuda-card-kicker">Detalhamento</span>
                    <h4>O que aparece nos modais territoriais</h4>
                    <ul class="modal-ajuda-lista">
                        <li>Nome do territorio consultado e pressao acumulada.</li>
                        <li>Quantidade de alertas ativos e tipos de evento presentes no recorte.</li>
                        <li>Numero do alerta, data, vigencia, gravidade, pressao e fonte de cada alerta ativo.</li>
                        <li>Quando houver mais de um alerta ativo, cada alerta aparece discriminado na tela.</li>
                    </ul>
                </article>

                <article class="modal-ajuda-card">
                    <span class="modal-ajuda-card-kicker">Leitura visual</span>
                    <h4>Como interpretar as cores do mapa</h4>
                    <ul class="modal-ajuda-lista">
                        <li>Quanto mais intensa a cor, maior a pressao operacional do territorio.</li>
                        <li>O modo territorial permite alternar entre municipios e regioes.</li>
                        <li>O grafico do IRP ajuda a identificar rapidamente janelas de maior pressao.</li>
                        <li>O ranking regional facilita localizar as areas mais criticas sem perder contexto visual.</li>
                    </ul>
                </article>
            </div>
        </div>

        <div class="modal-ajuda-footer">
            <span class="modal-ajuda-footer-note">Dica: combine os filtros territoriais com o relatorio analitico para uma leitura mais completa.</span>
            <button type="button" data-close-ajuda>Fechar</button>
        </div>
    </div>
</div>

<div id="modalRelatorio" class="modal" style="display:none;">
    <div class="modal-content analises-modal-content">
        <div class="modal-header analises-modal-header">
            <div>
                <span class="analises-modal-kicker">Relatorio consolidado</span>
                <h2>Relatorio analitico multirriscos</h2>
            </div>
            <button id="fecharModal" type="button" class="analises-modal-close" aria-label="Fechar relatorio">&times;</button>
        </div>
        <div id="conteudoRelatorio" class="analises-modal-body"></div>
    </div>
</div>

<div id="modalAnalisePreview" class="modal">
    <div class="modal-content public-analysis-modal">
        <button type="button" class="public-modal-close" data-close-modal="modalAnalisePreview" aria-label="Fechar detalhes da analise">&times;</button>
        <div class="public-analysis-modal-header">
            <div class="public-analysis-modal-heading">
                <span class="analises-modal-kicker" id="modalAnaliseKicker">Modulo analitico</span>
                <h3 id="modalAnaliseTitulo">Painel analitico</h3>
                <p id="modalAnaliseDescricao">Carregando informacoes do modulo selecionado.</p>
            </div>
        </div>
        <div class="public-analysis-modal-body" id="modalAnalisePreviewBody"></div>
        <div class="public-analysis-modal-footer">
            <div class="public-analysis-modal-footer-copy" id="modalAnaliseRodape">Carregando resumo operacional do modulo selecionado.</div>
            <?php if (is_array($usuarioAtivo) && !empty($usuarioAtivo['nome'])): ?>
                <a id="modalAnaliseLink" href="/pages/analises/index.php" class="btn btn-primary">Abrir pagina completa</a>
            <?php else: ?>
                <a href="#acesso-sistema" class="btn btn-primary" data-close-modal="modalAnalisePreview">Ir para acesso protegido</a>
            <?php endif; ?>
            <button type="button" class="btn btn-secondary" data-close-modal="modalAnalisePreview">Fechar</button>
        </div>
    </div>
</div>

<div id="modalInfo" class="modal">
    <div class="modal-content public-info-modal">
        <button type="button" class="public-modal-close" data-close-modal="modalInfo" aria-label="Fechar sobre o sistema">&times;</button>
        <div class="public-info-modal-header">
            <span class="public-info-modal-kicker">Sobre o sistema</span>
            <h3>Sistema Inteligente Multirriscos</h3>
            <p>Plataforma institucional da Defesa Civil do Estado do Para para monitoramento, leitura territorial e apoio a tomada de decisao com base nos alertas ativos.</p>
        </div>

        <div class="public-info-modal-pill-row">
            <span class="public-info-modal-pill">Painel publico</span>
            <span class="public-info-modal-pill">Mapa ao vivo</span>
            <span class="public-info-modal-pill">Relatorio analitico</span>
            <span class="public-info-modal-pill">Versao <?= htmlspecialchars($appConfig['version'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <div class="public-info-modal-grid">
            <article class="public-info-modal-card">
                <span class="public-info-modal-card-kicker">Consulta aberta</span>
                <strong>Leitura publica em tempo real</strong>
                <p>Qualquer visitante consegue visualizar mapa, indicadores e fazer download dos PDFs dos alertas ativos diretamente na pagina inicial.</p>
            </article>

            <article class="public-info-modal-card">
                <span class="public-info-modal-card-kicker">Ambiente protegido</span>
                <strong>Fluxos internos com autenticacao</strong>
                <p>Cadastro, gestao operacional, historico e paginas internas permanecem restritos aos perfis autorizados da Defesa Civil.</p>
            </article>
        </div>

        <div class="public-info-modal-footer">
            <div class="public-info-modal-support">
                <strong>Solicitacao de acesso</strong>
                <a href="mailto:dgr.cedecpa@gmail.com">dgr.cedecpa@gmail.com</a>
            </div>
            <button type="button" class="btn btn-secondary" data-close-modal="modalInfo">Fechar</button>
        </div>
    </div>
</div>

<script nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>" id="multirrisco-bootstrap" type="application/json"><?= json_encode([
    'regioes' => $regioesDisponiveis,
    'municipios' => $municipiosDisponiveis,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<script nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>" id="analises-preview-data" type="application/json"><?= json_encode($analises, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<script nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>">
window.MULTIRRISCO_CONFIG = { enableCompdec: false };
window.ANALISE_GLOBAL_CONFIG = { pdfEnabled: false };

(function () {
    const modalInfo = document.getElementById('modalInfo');
    const modalAnalisePreview = document.getElementById('modalAnalisePreview');
    const loginEmail = document.getElementById('login-email');
    const previewDataNode = document.getElementById('analises-preview-data');
    const previewData = previewDataNode ? JSON.parse(previewDataNode.textContent || '[]') : [];
    const previewMap = new Map(previewData.map(function (item) {
        return [item.slug, item];
    }));
    const previewEls = {
        kicker: document.getElementById('modalAnaliseKicker'),
        titulo: document.getElementById('modalAnaliseTitulo'),
        descricao: document.getElementById('modalAnaliseDescricao'),
        body: document.getElementById('modalAnalisePreviewBody'),
        rodape: document.getElementById('modalAnaliseRodape'),
        link: document.getElementById('modalAnaliseLink')
    };
    const previewLoadState = {
        token: 0
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function modalVisivel(modal) {
        return !!modal && window.getComputedStyle(modal).display !== 'none';
    }

    function atualizarScrollBody() {
        const existeModalAberto = [modalInfo, modalAnalisePreview].some(modalVisivel);
        document.body.classList.toggle('public-modal-open', existeModalAberto);
    }

    function abrirModal(modal) {
        if (!modal) {
            return;
        }

        modal.style.display = 'flex';
        atualizarScrollBody();
    }

    function fecharModalPersonalizado(modal) {
        if (!modal) {
            return;
        }

        modal.style.display = 'none';

        if (modal === modalAnalisePreview && previewEls.body) {
            const iframeAtual = document.getElementById('modalAnaliseFrame');

            if (iframeAtual) {
                iframeAtual.setAttribute('src', 'about:blank');
            }

            previewLoadState.token += 1;
            previewEls.body.innerHTML = '';

            if (previewEls.rodape) {
                previewEls.rodape.textContent = 'Carregando resumo operacional do modulo selecionado.';
            }
        }

        atualizarScrollBody();
    }

    function textoSelecionado(id, fallback) {
        const select = document.getElementById(id);

        if (!select) {
            return fallback;
        }

        const option = select.options[select.selectedIndex];

        if (!option || option.value === '') {
            return fallback;
        }

        return option.textContent || fallback;
    }

    function montarFiltroAtual(label, valor) {
        return [
            '<article class="public-analysis-form-field">',
            '<span>', escapeHtml(label), '</span>',
            '<strong>', escapeHtml(valor), '</strong>',
            '</article>'
        ].join('');
    }

    function valorFiltroPreview(nomeFiltro) {
        switch (String(nomeFiltro || '')) {
            case 'Ano':
                return textoSelecionado('filtroAno', 'Todos');
            case 'Mes':
                return textoSelecionado('filtroMes', 'Todos');
            case 'Regiao':
                return textoSelecionado('filtroRegiao', 'Todas');
            case 'Municipio':
                return textoSelecionado('filtroMunicipio', 'Todos');
            case 'Evento':
                return 'Definido na propria pagina';
            default:
                return 'Disponivel neste painel';
        }
    }

    function montarResumoPreview(item) {
        return [
            '<article class="public-analysis-summary-card">',
            '<span>', escapeHtml(item?.label || 'Destaque'), '</span>',
            '<strong>', escapeHtml(item?.value || 'Leitura analitica'), '</strong>',
            '<p>', escapeHtml(item?.note || 'Resumo operacional do painel.'), '</p>',
            '</article>'
        ].join('');
    }

    function montarInsightPreview(item) {
        return [
            '<article class="public-analysis-insight-card">',
            '<span>', escapeHtml(item?.label || 'Insight'), '</span>',
            '<strong>', escapeHtml(item?.value || 'Leitura rapida'), '</strong>',
            '<p>', escapeHtml(item?.note || 'Destaque operacional do painel.'), '</p>',
            '</article>'
        ].join('');
    }

    function montarGraficoPreview(item) {
        const titulo = typeof item === 'string' ? item : item?.titulo;
        const descricao = typeof item === 'string'
            ? 'Disponivel no painel completo com o mesmo recorte territorial.'
            : (item?.descricao || 'Disponivel no painel completo com o mesmo recorte territorial.');
        const kicker = typeof item === 'string' ? 'Grafico' : (item?.kicker || 'Grafico');
        const variant = typeof item === 'string' ? 'half' : (item?.variant || 'half');

        return [
            '<article class="public-analysis-chart-card public-analysis-chart-card--', escapeHtml(variant), '">',
            '<span class="public-analysis-chart-kicker">', escapeHtml(kicker), '</span>',
            '<strong>', escapeHtml(titulo || 'Leitura grafica'), '</strong>',
            '<p>', escapeHtml(descricao), '</p>',
            '<div class="public-analysis-chart-visual" aria-hidden="true">',
            '<span></span><span></span><span></span><span></span>',
            '</div>',
            '</article>'
        ].join('');
    }

    function acoesPreview(analise) {
        switch (String(analise?.slug || '')) {
            case 'temporal':
                return [
                    { label: 'Voltar para analises', tone: 'primary' },
                    { label: 'Abrir severidade', tone: 'secondary' }
                ];
            case 'severidade':
                return [
                    { label: 'Voltar para analises', tone: 'primary' },
                    { label: 'Abrir tipologia', tone: 'secondary' }
                ];
            case 'tipologia':
                return [
                    { label: 'Voltar para analises', tone: 'primary' },
                    { label: 'Abrir indices', tone: 'secondary' }
                ];
            case 'indices':
                return [
                    { label: 'Entender metodologia', tone: 'secondary' },
                    { label: 'Voltar para analises', tone: 'primary' }
                ];
            default:
                return [
                    { label: 'Abrir pagina completa', tone: 'primary' }
                ];
        }
    }

    function renderizarPreviewAnalise(analise) {
        const preview = analise?.preview || {};
        const filtrosHtml = (analise?.filtros || []).map(function (filtro) {
            return montarFiltroAtual(filtro, valorFiltroPreview(filtro));
        }).join('');
        const resumoHtml = (preview.resumo || []).map(montarResumoPreview).join('');
        const insightsHtml = (preview.insights || []).map(montarInsightPreview).join('');
        const graficosHtml = (preview.graficos_preview || analise?.graficos || []).map(montarGraficoPreview).join('');
        const contextoHtml = (analise?.filtros || []).map(function (filtro) {
            return [
                '<span class="public-preview-context-chip">',
                '<strong>', escapeHtml(filtro), ':</strong> ',
                escapeHtml(valorFiltroPreview(filtro)),
                '</span>'
            ].join('');
        }).join('');
        const acoesHtml = acoesPreview(analise).map(function (acao) {
            return '<span class="public-preview-action public-preview-action--' + escapeHtml(acao.tone || 'secondary') + '">' + escapeHtml(acao.label) + '</span>';
        }).join('');

        return [
            '<div class="public-analysis-page-preview">',
            '<section class="public-analysis-preview-hero">',
            '<div class="public-analysis-preview-hero-copy">',
            '<span class="public-analysis-preview-kicker">', escapeHtml(preview.hero_kicker || analise?.kicker || 'Modulo analitico'), '</span>',
            '<h4>', escapeHtml(preview.pagina_titulo || analise?.titulo || 'Painel analitico'), '</h4>',
            '<p>', escapeHtml(preview.pagina_descricao || analise?.descricao || 'Painel analitico selecionado.'), '</p>',
            '<div class="public-analysis-preview-actions">', acoesHtml, '</div>',
            '<div class="public-analysis-preview-context">', contextoHtml, '</div>',
            '</div>',
            '<div class="public-analysis-preview-summary-grid">', resumoHtml, '</div>',
            '</section>',

            '<div class="public-analysis-preview-main">',
            '<section class="public-analysis-preview-panel">',
            '<div class="public-analysis-preview-head">',
            '<span class="public-analysis-preview-section-kicker">Secao 1</span>',
            '<h4>', escapeHtml(preview.filtros_titulo || 'Filtros do recorte analitico'), '</h4>',
            '<p>', escapeHtml(preview.filtros_texto || 'Ajuste o recorte para recalcular o painel.'), '</p>',
            '</div>',
            '<div class="public-analysis-form-grid">', filtrosHtml, '</div>',
            '</section>',

            '<section class="public-analysis-preview-panel">',
            '<div class="public-analysis-preview-head">',
            '<span class="public-analysis-preview-section-kicker">Secao 2</span>',
            '<h4>', escapeHtml(preview.insights_titulo || 'Leituras rapidas do periodo'), '</h4>',
            '<p>', escapeHtml(preview.insights_texto || 'Destaques operacionais do painel selecionado.'), '</p>',
            '</div>',
            '<div class="public-analysis-insight-grid">', insightsHtml, '</div>',
            '</section>',
            '</div>',

            '<section class="public-analysis-preview-panel">',
            '<div class="public-analysis-preview-head">',
            '<span class="public-analysis-preview-section-kicker">Visual da pagina</span>',
            '<h4>Graficos e blocos analiticos</h4>',
            '<p>Previa estrutural da tela correspondente ao card selecionado, mantendo o mesmo idioma visual do modulo original.</p>',
            '</div>',
            '<div class="public-analysis-chart-grid">', graficosHtml, '</div>',
            '</section>',
            '</div>'
        ].join('');
    }

    function urlPreviewAnalise(analise) {
        const params = new URLSearchParams();
        const ano = document.getElementById('filtroAno')?.value || '';
        const mes = document.getElementById('filtroMes')?.value || '';
        const regiao = document.getElementById('filtroRegiao')?.value || '';
        const municipio = document.getElementById('filtroMunicipio')?.value || '';

        if (ano) {
            params.set('ano', ano);
        }

        if (mes) {
            params.set('mes', mes);
        }

        if (regiao) {
            params.set('regiao', regiao);
        }

        if (municipio) {
            params.set('municipio', municipio);
        }

        params.set('embed', '1');

        return (analise?.href || '/pages/analises/index.php') + '?' + params.toString();
    }

    function montarIframeAnalise(analise) {
        return [
            '<div class="public-analysis-frame-shell">',
            '<div class="public-analysis-frame-loading" id="modalAnaliseLoading">Carregando pagina analitica com filtros e graficos reais...</div>',
            '<iframe',
            ' id="modalAnaliseFrame"',
            ' class="public-analysis-iframe"',
            ' src="about:blank"',
            ' data-src="', escapeHtml(urlPreviewAnalise(analise)), '"',
            ' loading="eager"',
            ' referrerpolicy="same-origin"',
            ' title="', escapeHtml(analise?.titulo || 'Pagina analitica'), '"',
            '></iframe>',
            '</div>'
        ].join('');
    }

    function sincronizarIframeAnalise(iframe) {
        if (!iframe || !iframe.contentWindow) {
            return;
        }

        const emitirResize = function () {
            try {
                const janelaFilha = iframe.contentWindow;
                const EventoResize = janelaFilha.Event || window.Event;
                janelaFilha.dispatchEvent(new EventoResize('resize'));
            } catch (error) {
                console.warn('Nao foi possivel sincronizar o resize da analise embed.', error);
            }
        };

        emitirResize();
        window.setTimeout(emitirResize, 120);
        window.setTimeout(emitirResize, 320);
    }

    function iniciarCarregamentoIframe(iframe, tokenAtual) {
        if (!iframe || tokenAtual !== previewLoadState.token) {
            return;
        }

        const urlDestino = iframe.getAttribute('data-src');

        if (!urlDestino) {
            return;
        }

        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                if (tokenAtual !== previewLoadState.token) {
                    return;
                }

                iframe.setAttribute('src', urlDestino);
            });
        });
    }

    function abrirPreviewAnalise(slug) {
        const analise = previewMap.get(slug);
        const preview = analise?.preview || {};

        if (!analise || !previewEls.titulo || !previewEls.body) {
            return;
        }

        previewEls.kicker.textContent = preview.hero_kicker || analise.kicker || 'Modulo analitico';
        previewEls.titulo.textContent = preview.pagina_titulo || analise.titulo || 'Painel analitico';
        previewEls.descricao.textContent = preview.pagina_descricao || analise.descricao || 'Painel analitico selecionado.';

        if (previewEls.link) {
            previewEls.link.href = analise.href || '/pages/analises/index.php';
            previewEls.link.textContent = 'Abrir ' + (preview.pagina_titulo || analise.titulo || 'pagina completa');
        }

        previewLoadState.token += 1;
        const tokenAtual = previewLoadState.token;
        previewEls.body.innerHTML = montarIframeAnalise(analise);

        if (previewEls.rodape) {
            previewEls.rodape.textContent = preview.rodape || 'A pagina abaixo e a propria analise em modo de visualizacao, com filtros e graficos reais no mesmo layout do sistema.';
        }

        abrirModal(modalAnalisePreview);

        const iframe = document.getElementById('modalAnaliseFrame');
        const loading = document.getElementById('modalAnaliseLoading');

        if (iframe && loading) {
            const timeoutCarregamento = window.setTimeout(function () {
                if (tokenAtual === previewLoadState.token && !iframe.classList.contains('is-ready')) {
                    loading.textContent = 'Carregando a pagina analitica real com filtros e graficos. Se demorar, aguarde mais alguns instantes.';
                }
            }, 2400);

            const timeoutCarregamentoLento = window.setTimeout(function () {
                if (tokenAtual === previewLoadState.token && !iframe.classList.contains('is-ready')) {
                    loading.textContent = 'A visualizacao esta sendo preparada. O carregamento so comeca depois que o modal fica visivel para garantir que os graficos aparecam corretamente.';
                }
            }, 5200);

            iframe.addEventListener('load', function () {
                if (tokenAtual !== previewLoadState.token) {
                    return;
                }

                window.clearTimeout(timeoutCarregamento);
                window.clearTimeout(timeoutCarregamentoLento);
                loading.hidden = true;
                iframe.classList.add('is-ready');
                sincronizarIframeAnalise(iframe);
            }, { once: true });

            iniciarCarregamentoIframe(iframe, tokenAtual);
        }
    }

    document.addEventListener('click', function (event) {
        const openInfo = event.target.closest('[data-open-info]');
        const previewButton = event.target.closest('[data-analise-preview]');
        const closeButton = event.target.closest('[data-close-modal]');

        if (openInfo) {
            abrirModal(modalInfo);
            return;
        }

        if (previewButton) {
            abrirPreviewAnalise(previewButton.getAttribute('data-analise-preview') || '');
            return;
        }

        if (closeButton) {
            fecharModalPersonalizado(document.getElementById(closeButton.getAttribute('data-close-modal')));
            return;
        }

        if (event.target === modalInfo) {
            fecharModalPersonalizado(modalInfo);
        }

        if (event.target === modalAnalisePreview) {
            fecharModalPersonalizado(modalAnalisePreview);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        if (modalVisivel(modalAnalisePreview)) {
            fecharModalPersonalizado(modalAnalisePreview);
            return;
        }

        if (modalVisivel(modalInfo)) {
            fecharModalPersonalizado(modalInfo);
        }
    });

    <?php if ($erro !== '' && (!is_array($usuarioAtivo) || empty($usuarioAtivo['nome']))): ?>
    const acessoSistema = document.getElementById('acesso-sistema');
    if (acessoSistema) {
        acessoSistema.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    if (loginEmail) {
        window.setTimeout(function () {
            loginEmail.focus();
        }, 120);
    }
    <?php endif; ?>
})();
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/assets/vendor/chartjs/chart-lite.js"></script>
<script src="/assets/js/pages/mapas-mapa_multirriscos.js"></script>
<script src="/assets/js/analise-global.js"></script>
</body>
</html>
