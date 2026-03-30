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
$supportEmail = trim((string) ($appConfig['support_email'] ?? 'suporte@defesacivil.pa.gov.br'));

if ($supportEmail === '') {
    $supportEmail = 'suporte@defesacivil.pa.gov.br';
}

$supportEmailSafe = htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8');

if (is_array($usuarioAtivo)) {
    Session::touchActivity();
}

$erro = '';
$mensagemSessao = '';
$emailInformado = '';
$motivo = trim((string) ($_GET['motivo'] ?? ''));

if ($motivo === 'inatividade') {
    $mensagemSessao = 'Sua sessão foi encerrada por inatividade. Entre novamente para continuar.';
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
                'Origem: tela inicial pública'
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
            $erro = 'Usuário ou senha inválidos.';
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
        'descricao' => 'Avalia sazonalidade, frequência por período do dia e variação anual dos alertas.',
        'nivel' => 'Operacional / Tático',
        'filtros' => ['Ano', 'Mês', 'Região', 'Município', 'Evento'],
        'href' => '/pages/analises/temporal.php',
        'graficos' => [
            'Sazonalidade mensal',
            'Comparativo multievento',
            'Distribuição mensal por evento',
            'Evolução do período',
            'Evolução anual',
            'Alertas cancelados por ano',
            'Frequência por período do dia',
        ],
    ],
    [
        'slug' => 'severidade',
        'kicker' => 'Severidade',
        'titulo' => 'Severidade e impacto',
        'descricao' => 'Resume distribuição por gravidade, duração média e concentração territorial.',
        'nivel' => 'Operacional',
        'filtros' => ['Ano', 'Mês', 'Região', 'Município'],
        'href' => '/pages/analises/severidade.php',
        'graficos' => [
            'Distribuição por gravidade',
            'Proporção entre faixas de severidade',
            'Duração média por evento',
            'Quantidade de alertas por evento',
            'Municípios mais impactados',
        ],
    ],
    [
        'slug' => 'tipologia',
        'kicker' => 'Tipologia',
        'titulo' => 'Tipologia de eventos',
        'descricao' => 'Mostra quais eventos predominam por região e sua correlação com severidade.',
        'nivel' => 'Operacional',
        'filtros' => ['Ano', 'Mês', 'Região', 'Município'],
        'href' => '/pages/analises/tipologia.php',
        'graficos' => [
            'Distribuição por tipo de evento',
            'Participação percentual dos eventos',
            'Correlação entre tipologia e severidade',
            'Tipologia por região',
            'Sazonalidade dos eventos',
            'Recorrência municipal por tipologia',
        ],
    ],
    [
        'slug' => 'indices',
        'kicker' => 'Índices',
        'titulo' => 'IRP e IPT',
        'descricao' => 'Prioriza territórios com maior pressão operacional e maior intensidade territorial.',
        'nivel' => 'Estratégico',
        'filtros' => ['Ano', 'Mês', 'Região', 'Município'],
        'href' => '/pages/analises/indice_risco.php',
        'graficos' => [
            'Ranking regional do IRP',
            'Ranking municipal do IPT',
            'Gráfico comparativo do IRP',
            'Gráfico comparativo do IPT',
        ],
    ],
];

$analisesPreview = [
    'temporal' => [
        'hero_kicker' => 'Análise operacional',
        'pagina_titulo' => 'Análise temporal de alertas',
        'pagina_descricao' => 'Organize a leitura de sazonalidade, recorrência por período do dia e evolução histórica dos alertas no mesmo padrão visual das telas analíticas.',
        'resumo' => [
            ['label' => 'Sazonalidade', 'value' => 'Meses críticos', 'note' => 'Resume os períodos com maior concentração de alertas no recorte.'],
            ['label' => 'Recorrência diária', 'value' => 'Turno predominante', 'note' => 'Mostra a faixa horária mais frequente nas ocorrências monitoradas.'],
            ['label' => 'Histórico', 'value' => 'Comparativo anual', 'note' => 'Cruza a evolução temporal com os anos anteriores do recorte selecionado.'],
        ],
        'filtros_titulo' => 'Filtros do recorte temporal',
        'filtros_texto' => 'Ano, mês, região, município e evento recalculam a leitura temporal e mantêm a comparação histórica coerente.',
        'insights_titulo' => 'Leituras rápidas do período',
        'insights_texto' => 'A página destaca pico sazonal, período do dia predominante, evento dominante e ano historicamente mais ativo.',
        'insights' => [
            ['label' => 'Pico sazonal', 'value' => 'Mês com maior volume', 'note' => 'Ajuda a localizar a janela de maior recorrência no recorte.'],
            ['label' => 'Período do dia', 'value' => 'Faixa horária líder', 'note' => 'Indica o turno em que os alertas mais se concentram.'],
            ['label' => 'Evento dominante', 'value' => 'Tipologia principal', 'note' => 'Aponta o evento mais recorrente da leitura temporal.'],
            ['label' => 'Base histórica', 'value' => 'Ano mais ativo', 'note' => 'Mostra a referência anual com maior carga de alertas.'],
        ],
        'graficos_preview' => [
            ['kicker' => 'Seção 3', 'titulo' => 'Sazonalidade mensal do recorte', 'descricao' => 'Gráfico principal com a distribuição mensal dos alertas.', 'variant' => 'wide'],
            ['kicker' => 'Seção 4', 'titulo' => 'Comparativo mensal de eventos', 'descricao' => 'Compara o comportamento das tipologias ao longo do ano.', 'variant' => 'half'],
            ['kicker' => 'Seção 5', 'titulo' => 'Sazonalidade do evento selecionado', 'descricao' => 'Detalha a distribuição mensal de um evento específico.', 'variant' => 'half'],
            ['kicker' => 'Seção 6', 'titulo' => 'Evolução anual e alertas cancelados', 'descricao' => 'Consolida histórico anual, cancelamentos e recorrência por hora.', 'variant' => 'wide'],
        ],
        'rodape' => 'Ideal para entender janelas críticas antes de aprofundar a leitura territorial no mapa.',
    ],
    'severidade' => [
        'hero_kicker' => 'Análise operacional',
        'pagina_titulo' => 'Severidade e impacto dos alertas',
        'pagina_descricao' => 'Consolide distribuição de gravidade, duração média dos eventos e impacto territorial no mesmo padrão visual das páginas analíticas recentes.',
        'resumo' => [
            ['label' => 'Severidade', 'value' => 'Faixas de gravidade', 'note' => 'Mostra como o recorte se distribui entre baixo, moderado, alto, muito alto e extremo.'],
            ['label' => 'Impacto', 'value' => 'Abrangência territorial', 'note' => 'Resume a carga operacional observada por região e município.'],
            ['label' => 'Contexto', 'value' => 'Eventos e duração', 'note' => 'Cruza severidade com tempo de permanência dos alertas ativos.'],
        ],
        'filtros_titulo' => 'Filtros do recorte analítico',
        'filtros_texto' => 'Ano, mês, região e município recalculam automaticamente a leitura de severidade e impacto.',
        'insights_titulo' => 'Leituras rápidas do período',
        'insights_texto' => 'A página evidencia a severidade predominante, o evento mais recorrente, a maior duração média e o município mais impactado.',
        'insights' => [
            ['label' => 'Severidade predominante', 'value' => 'Faixa líder', 'note' => 'Sinaliza a faixa de gravidade com maior concentração no recorte.'],
            ['label' => 'Evento recorrente', 'value' => 'Tipologia dominante', 'note' => 'Mostra qual evento mais pressiona a operação no período.'],
            ['label' => 'Duração média', 'value' => 'Evento mais duradouro', 'note' => 'Indica os eventos que tendem a permanecer ativos por mais tempo.'],
            ['label' => 'Impacto municipal', 'value' => 'Município mais afetado', 'note' => 'Destaca o território com maior concentração de alertas.'],
        ],
        'graficos_preview' => [
            ['kicker' => 'Seção 3', 'titulo' => 'Distribuição final de severidade', 'descricao' => 'Gráfico principal com a quantidade absoluta por gravidade.', 'variant' => 'wide'],
            ['kicker' => 'Seção 4', 'titulo' => 'Proporção por grau de severidade', 'descricao' => 'Leitura percentual da composição do recorte.', 'variant' => 'half'],
            ['kicker' => 'Seção 5', 'titulo' => 'Duração média por tipo de evento', 'descricao' => 'Compara em horas os eventos com maior permanência.', 'variant' => 'half'],
            ['kicker' => 'Seção 6', 'titulo' => 'Alertas por evento e municípios impactados', 'descricao' => 'Cruza volume de emissões com os territórios mais pressionados.', 'variant' => 'wide'],
        ],
        'rodape' => 'Recomendado para medir carga operacional e identificar territórios onde a resposta pode ficar mais pressionada.',
    ],
    'tipologia' => [
        'hero_kicker' => 'Análise operacional',
        'pagina_titulo' => 'Tipologia de alertas',
        'pagina_descricao' => 'Observe os tipos de evento mais recorrentes, sua correlação com a severidade e a distribuição territorial por regiões e municípios.',
        'resumo' => [
            ['label' => 'Tipologias', 'value' => 'Eventos no recorte', 'note' => 'Resume as tipologias de alerta presentes no período analisado.'],
            ['label' => 'Território', 'value' => 'Regiões e municípios', 'note' => 'Mostra onde cada evento aparece com maior recorrência.'],
            ['label' => 'Severidade', 'value' => 'Correlação ativa', 'note' => 'Conecta tipologias com as faixas de gravidade observadas.'],
        ],
        'filtros_titulo' => 'Filtros do recorte analítico',
        'filtros_texto' => 'Ano, mês, região e município recalculam automaticamente a leitura tipológica.',
        'insights_titulo' => 'Leituras rápidas do período',
        'insights_texto' => 'A página destaca a tipologia dominante e os territórios com maior recorrência para cada padrão de evento.',
        'insights' => [
            ['label' => 'Evento mais recorrente', 'value' => 'Tipologia principal', 'note' => 'Aponta o tipo de evento com maior volume no recorte.'],
            ['label' => 'Recorrência regional', 'value' => 'Região líder', 'note' => 'Mostra a região com maior concentração tipológica.'],
            ['label' => 'Recorrência municipal', 'value' => 'Município líder', 'note' => 'Evidencia o município mais impactado por aquele padrão de evento.'],
        ],
        'graficos_preview' => [
            ['kicker' => 'Seção 3', 'titulo' => 'Correlação entre evento e severidade', 'descricao' => 'Matriz visual para comparar tipologias e faixas de gravidade.', 'variant' => 'wide'],
            ['kicker' => 'Seção 4', 'titulo' => 'Distribuição das tipologias', 'descricao' => 'Mostra a composição total por tipo de evento.', 'variant' => 'half'],
            ['kicker' => 'Seção 5', 'titulo' => 'Tipologia por região e sazonalidade', 'descricao' => 'Cruza regiões, eventos e comportamento temporal.', 'variant' => 'half'],
            ['kicker' => 'Seção 6', 'titulo' => 'Recorrência municipal por tipologia', 'descricao' => 'Indica onde cada evento se concentra com maior intensidade.', 'variant' => 'wide'],
        ],
        'rodape' => 'Use essa página quando precisar entender padrões de evento e sua distribuição territorial com mais profundidade.',
    ],
    'indices' => [
        'hero_kicker' => 'Inteligência estratégica',
        'pagina_titulo' => 'Índices de risco (IRP / IPT)',
        'pagina_descricao' => 'Acompanhe a pressão regional e territorial provocada pelos alertas multirriscos, com leitura consolidada por período e metodologia em modal.',
        'resumo' => [
            ['label' => 'IRP', 'value' => 'Pressão regional', 'note' => 'Mostra a carga operacional média nas regiões de integração.'],
            ['label' => 'IPT', 'value' => 'Carga municipal', 'note' => 'Resume a intensidade territorial acumulada nos municípios.'],
            ['label' => 'Contexto', 'value' => 'Priorização territorial', 'note' => 'Facilita decidir onde monitorar e responder primeiro.'],
        ],
        'filtros_titulo' => 'Filtros do recorte analítico',
        'filtros_texto' => 'Ano, mês, região e município recalculam automaticamente o IRP e o IPT no mesmo painel.',
        'insights_titulo' => 'Leituras rápidas do período',
        'insights_texto' => 'A página destaca líderes de pressão, comportamento do recorte atual e acesso imediato à metodologia dos índices.',
        'insights' => [
            ['label' => 'Região com maior IRP', 'value' => 'Líder regional', 'note' => 'Indica a região com maior pressão operacional no recorte.'],
            ['label' => 'Município com maior IPT', 'value' => 'Líder territorial', 'note' => 'Mostra o município com maior carga acumulada.'],
            ['label' => 'Metodologia', 'value' => 'IRP e IPT explicados', 'note' => 'O painel possui modal próprio para interpretar os cálculos.'],
        ],
        'graficos_preview' => [
            ['kicker' => 'Seção 3', 'titulo' => 'Ranking regional de pressão', 'descricao' => 'Gráfico principal comparando as regiões pelo IRP.', 'variant' => 'half'],
            ['kicker' => 'Seção 4', 'titulo' => 'Ranking territorial de pressão', 'descricao' => 'Gráfico principal comparando municípios pelo IPT.', 'variant' => 'half'],
            ['kicker' => 'Destaque', 'titulo' => 'Metodologia em modal', 'descricao' => 'A página traz explicação detalhada sobre como os índices são calculados.', 'variant' => 'wide'],
        ],
        'rodape' => 'Recomendado para priorização estratégica e decisão sobre onde a pressão multirriscos exige resposta mais imediata.',
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
$jsMapaMultirriscosPath = __DIR__ . '/assets/js/pages/mapas-mapa_multirriscos.js';
$jsAnaliseGlobalPath = __DIR__ . '/assets/js/analise-global.js';
$cssPainelBaseVersion = (string) ((int) @filemtime($cssPainelBasePath));
$cssPainelPageVersion = (string) ((int) @filemtime($cssPainelPagePath));
$cssAlertasFormVersion = (string) ((int) @filemtime($cssAlertasFormPath));
$cssAnalisesIndexVersion = (string) ((int) @filemtime($cssAnalisesIndexPath));
$cssMapaMultirriscosVersion = (string) ((int) @filemtime($cssMapaMultirriscosPath));
$cssLoginVersion = (string) ((int) @filemtime($cssLoginPath));
$jsMapaMultirriscosVersion = (string) ((int) @filemtime($jsMapaMultirriscosPath));
$jsAnaliseGlobalVersion = (string) ((int) @filemtime($jsAnaliseGlobalPath));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($appConfig['name'], ENT_QUOTES, 'UTF-8') ?> - Defesa Civil do Estado do Pará</title>
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

    <nav class="public-nav" aria-label="Navegação pública">
        <a href="#mapa-publico">Mapa ao vivo</a>
        <a href="#analises-publicas">Relatório analítico</a>
        <a href="#alertas-ativos">Alertas ativos</a>
    </nav>

    <div class="public-topbar-actions">
        <span class="topbar-pill topbar-pill-neutral">Versão <?= htmlspecialchars($appConfig['version'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php if (is_array($usuarioAtivo) && !empty($usuarioAtivo['nome'])): ?>
            <a href="/pages/painel.php" class="btn btn-primary">Acessar painel</a>
        <?php endif; ?>
    </div>
</header>

<main class="public-home" id="inicio">
    <section class="public-hero">
        <div class="public-hero-copy">
            <span class="alerta-form-kicker">Painel institucional aberto</span>
            <h1>Monitoramento multirriscos público<br>Defesa Civil do Pará</h1>
            <p>
                Consulte o mapa multirriscos em destaque, leia os indicadores territoriais, gere o relatório analítico na tela e baixe os PDFs dos alertas ativos sem precisar estar logado.
            </p>

            <div class="public-hero-actions">
                <a href="#mapa-publico" class="btn btn-primary">Explorar o mapa</a>
                <a href="#alertas-ativos" class="btn btn-secondary">Baixar alertas ativos</a>
                <?php if (is_array($usuarioAtivo) && !empty($usuarioAtivo['nome'])): ?>
                    <a href="/pages/painel.php" class="btn btn-secondary">Abrir ambiente operacional</a>
                <?php else: ?>
                    <a href="#analises-publicas" class="btn btn-secondary">Ver análise</a>
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
                    <span class="alerta-summary-note">Alertas vigentes e disponíveis para consulta e download em PDF.</span>
                </article>

                <article class="public-highlight-card">
                    <span class="alerta-summary-label">Cobertura territorial</span>
                    <strong class="alerta-summary-value"><?= count($municipiosDisponiveis) ?> municípios</strong>
                    <span class="alerta-summary-note"><?= count($regioesDisponiveis) ?> regiões de integração monitoradas.</span>
                </article>

                <article class="public-highlight-card">
                    <span class="alerta-summary-label">Mapa pronto</span>
                    <strong class="alerta-summary-value"><?= $totalAlertasMapeados ?> áreas mapeadas</strong>
                    <span class="alerta-summary-note">Leitura cartográfica com foco territorial e filtros analíticos.</span>
                </article>

                <article class="public-highlight-card">
                    <span class="alerta-summary-label">Último alerta ativo</span>
                    <strong class="alerta-summary-value"><?= htmlspecialchars((string) ($alertaMaisRecente['numero'] ?? 'Sem alerta ativo'), ENT_QUOTES, 'UTF-8') ?></strong>
                    <span class="alerta-summary-note">
                        <?= $alertaMaisRecente
                            ? htmlspecialchars((string) ($alertaMaisRecente['tipo_evento'] ?? ''), ENT_QUOTES, 'UTF-8') . ' em ' . htmlspecialchars(TimeHelper::formatDate((string) ($alertaMaisRecente['data_alerta'] ?? null)), ENT_QUOTES, 'UTF-8')
                            : 'Não há registros ativos no momento.' ?>
                    </span>
                </article>
            </div>
        </div>

        <aside class="public-access-card public-access-summary-card" id="acesso-sistema">
            <?php if (is_array($usuarioAtivo) && !empty($usuarioAtivo['nome'])): ?>
                <span class="public-access-kicker">Sessão ativa</span>
                <h2><?= htmlspecialchars((string) $usuarioAtivo['nome'], ENT_QUOTES, 'UTF-8') ?></h2>
                <p>Seu acesso autenticado está ativo. Use os atalhos abaixo para entrar no ambiente operacional ou encerrar a sessão.</p>
                <div class="public-access-meta">
                    <span class="analises-chip">Perfil: <?= htmlspecialchars((string) ($usuarioAtivo['perfil'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="analises-chip">Timeout: <?= (int) (Session::inactivityTimeout() / 60) ?> min</span>
                </div>
                <div class="public-access-actions">
                    <a href="/pages/painel.php" class="btn btn-primary">Ir para o painel</a>
                    <a href="/logout.php" class="btn btn-secondary">Encerrar sessão</a>
                </div>
            <?php else: ?>
                <span class="public-access-kicker">Acesso protegido</span>
                <h2 class="public-access-title-protected">Ambiente operacional com autenticação</h2>
                <p>O visitante consulta o painel aberto. O usuário autenticado acessa cadastro, gestão, histórico e os painéis analíticos completos do sistema.</p>
                <div class="public-access-meta">
                    <span class="analises-chip">Mapa ao vivo</span>
                    <span class="analises-chip">Relatório em modal</span>
                    <span class="analises-chip">PDFs ativos</span>
                </div>
                <div class="public-access-feature-list">
                    <article class="public-access-feature">
                        <strong>Consulta pública</strong>
                        <span>Mapa multirriscos, leitura territorial e relatório consolidado liberados para qualquer visitante.</span>
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
                    <strong>Solicitação de acesso</strong>
                    <a href="mailto:<?= $supportEmailSafe ?>"><?= $supportEmailSafe ?></a>
                </div>
            <?php endif; ?>
        </aside>
    </section>

    <section class="dashboard alerta-form-shell public-section" id="mapa-publico">
        <div class="alerta-form-hero public-section-hero">
            <div class="alerta-form-lead">
                <span class="alerta-form-kicker">Mapa em destaque</span>
                <h2 class="alerta-form-title">Mapa multirriscos com leitura territorial aberta ao público</h2>
                <p class="alerta-form-description">
                    A tela abaixo segue o mesmo idioma visual das áreas internas de painel e listagem, com o mapa como elemento central, filtros sincronizados, ranking regional e detalhamento territorial na tela.
                </p>
            </div>

            <div class="alerta-form-summary">
                <div class="alerta-summary-card">
                    <span class="alerta-summary-label">Monitoramento ativo</span>
                    <span class="alerta-summary-value" id="hero-alertas-ativos"><?= $totalAtivos ?> alertas</span>
                    <span class="alerta-summary-note">Total de alertas ativos disponíveis para leitura territorial nesta tela pública.</span>
                </div>
                <div class="alerta-summary-card">
                    <span class="alerta-summary-label">Base territorial</span>
                    <span class="alerta-summary-value"><?= count($municipiosDisponiveis) ?> municípios</span>
                    <span class="alerta-summary-note">Cobertura completa em <?= count($regioesDisponiveis) ?> regiões de integração.</span>
                </div>
                <div class="alerta-summary-card">
                    <span class="alerta-summary-label">Foco atual</span>
                    <span class="alerta-summary-value" id="hero-foco-value">Sem recorte territorial</span>
                    <span class="alerta-summary-note" id="hero-foco-note">Selecione região ou município para destacar automaticamente o recorte.</span>
                </div>
            </div>
        </div>

        <div class="alerta-form-panel">
            <div class="alerta-form-grid multirrisco-overview-grid">
                <section class="alerta-form-section multirrisco-overview-section multirrisco-overview-section-filtros">
                    <header class="alerta-section-header">
                        <span class="alerta-section-kicker">Seção 1</span>
                        <h2 class="alerta-section-title">Filtros e recorte territorial</h2>
                        <p class="alerta-section-text">Os filtros abaixo sincronizam mapa, indicadores, ranking regional, gráfico de pressão e detalhamento territorial.</p>
                    </header>

                    <form id="multirrisco-form" class="multirrisco-filter-form">
                        <div class="multirrisco-filter-grid">
                            <div class="form-group">
                                <label for="data_inicio">Período inicial</label>
                                <input type="date" id="data_inicio" name="data_inicio">
                            </div>
                            <div class="form-group">
                                <label for="data_fim">Período final</label>
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
                                <label for="regiao">Região</label>
                                <select id="regiao" name="regiao">
                                    <option value="">Todas as regiões</option>
                                    <?php foreach ($regioesDisponiveis as $regiao): ?>
                                        <option value="<?= htmlspecialchars($regiao, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($regiao, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="municipio">Município</label>
                                <select id="municipio" name="municipio">
                                    <option value="">Todos os municípios</option>
                                </select>
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

                <section class="alerta-form-section multirrisco-overview-section multirrisco-overview-section-operacional">
                    <header class="alerta-section-header">
                        <span class="alerta-section-kicker">Seção 2</span>
                        <h2 class="alerta-section-title">Leitura operacional</h2>
                        <p class="alerta-section-text">Organize a leitura do cenário em um fluxo simples: entenda a carga territorial, identifique o foco e avance para o detalhe no mapa.</p>
                    </header>

                    <div class="public-operational-layout">
                        <div class="painel-kpi-grid multirrisco-kpi-grid">
                            <article class="painel-kpi-card is-active">
                                <span class="painel-kpi-label">Alertas ativos</span>
                                <strong class="painel-kpi-value" id="kpi-ativos">-</strong>
                                <span class="painel-kpi-note">Total de alertas no recorte atual.</span>
                            </article>
                            <article class="painel-kpi-card">
                                <span class="painel-kpi-label">Municípios em risco</span>
                                <strong class="painel-kpi-value" id="kpi-municipios">-</strong>
                                <span class="painel-kpi-note">Municípios com alertas ativos no recorte consultado.</span>
                            </article>
                            <article class="painel-kpi-card is-neutral">
                                <span class="painel-kpi-label">Regiões afetadas</span>
                                <strong class="painel-kpi-value" id="kpi-regioes">-</strong>
                                <span class="painel-kpi-note">Regiões de integração alcançadas pelo recorte atual.</span>
                            </article>
                            <article class="painel-kpi-card is-warning multirrisco-focus-card">
                                <span class="painel-kpi-label">Território em foco</span>
                                <strong class="painel-kpi-value" id="foco-territorial-titulo">Nenhum foco definido</strong>
                                <span class="painel-kpi-note" id="foco-territorial-texto">Clique em uma região, município ou item do ranking para abrir o detalhe.</span>
                            </article>
                        </div>

                        <div class="public-operational-stack">
                            <article class="public-operational-card">
                                <span class="public-operational-step">Passo 1</span>
                                <h3>Defina o cenário</h3>
                                <p>Use período, evento, gravidade, fonte, região e município para reduzir o mapa ao recorte realmente necessário.</p>
                            </article>

                            <article class="public-operational-card">
                                <span class="public-operational-step">Passo 2</span>
                                <h3>Leia a pressão ativa</h3>
                                <p>Os indicadores mostram rapidamente o volume de alertas, a abrangência territorial e o foco principal da consulta.</p>
                            </article>

                            <article class="public-operational-card public-operational-card-highlight">
                                <span class="public-operational-step">Passo 3</span>
                                <h3>Abra o detalhe territorial</h3>
                                <p>Depois de identificar a área crítica, clique no mapa ou no ranking para abrir na tela com os alertas ativos discriminados.</p>
                            </article>
                        </div>
                    </div>

                    <div class="alerta-callout multirrisco-callout">
                        <strong>Leitura recomendada</strong>
                        Escolha primeiro o recorte territorial. Ao selecionar uma região, o mapa destaca a camada regional; ao selecionar um município, o destaque migra automaticamente para o território municipal.
                    </div>
                </section>
            </div>
        </div>

        <section class="alerta-form-panel multirrisco-map-panel">
            <header class="painel-section-head">
                <div class="alerta-section-header">
                    <span class="alerta-section-kicker">Seção 3</span>
                    <h2 class="alerta-section-title">Mapa territorial e pressão ativa</h2>
                    <p class="alerta-section-text">O mapa ocupa o maior protagonismo da tela para facilitar navegação, leitura de pressão e abertura do detalhamento territorial.</p>
                </div>

                <div class="painel-map-meta">
                    <span class="painel-chip" id="chip-modo-territorial">Modo: municípios</span>
                    <span class="painel-chip" id="chip-filtro-territorial">Sem recorte territorial</span>
                </div>
            </header>

            <div class="multirrisco-map-layout">
                <div class="map-card multirrisco-map-card">
                    <div class="map-card-header">
                        <div>
                            <span class="map-card-title">Mapa multirriscos do Pará</span>
                            <p class="map-card-text">Alertas ativos, pressão territorial, regiões de integração e municípios se atualizam juntos, com destaque automático a partir do filtro selecionado.</p>
                        </div>
                        <div class="multirrisco-toolbar-status" id="status-atualizacao">Pronto para consulta pública</div>
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
                                <span>Municípios</span>
                            </label>
                            <label class="multirrisco-segment">
                                <input type="radio" name="modoTerritorial" value="regioes">
                                <span>Regiões</span>
                            </label>
                        </div>
                    </div>

                    <div class="multirrisco-map-stage public-map-stage">
                        <div id="mapa" class="alerta-map multirrisco-map-canvas"></div>
                        <div class="multirrisco-map-loading" id="mapaLoading" hidden>Atualizando mapa e indicadores...</div>
                    </div>
                </div>

                <aside class="multirrisco-side-stack">
                    <section class="alerta-form-section multirrisco-side-section multirrisco-side-section-ranking">
                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Ranking</span>
                            <h2 class="alerta-section-title">Regiões mais pressionadas</h2>
                            <p class="alerta-section-text">Clique em uma região da lista para centralizar o mapa e abrir o detalhamento regional.</p>
                        </header>

                        <div id="lista-regioes" class="lista-regioes">
                            <div class="multirrisco-empty-box">Carregando leitura regional...</div>
                        </div>
                    </section>

                    <section class="alerta-form-section multirrisco-side-section multirrisco-side-section-serie">
                        <div id="filtro-dia-ativo" class="multirrisco-day-filter" hidden>
                            <span>Filtro ativo para o dia: <strong id="diaSelecionadoTxt"></strong></span>
                            <button type="button" class="btn btn-secondary" id="btnLimparFiltroDia">Limpar</button>
                        </div>

                        <header class="alerta-section-header">
                            <span class="alerta-section-kicker">Série diária</span>
                            <h2 class="alerta-section-title">Evolução do IRP</h2>
                            <p class="alerta-section-text">Clique em um ponto do gráfico para refinar o mapa por um dia específico.</p>
                        </header>

                        <div class="grafico-container multirrisco-chart-container">
                            <canvas id="graficoLinhaTempo"></canvas>
                        </div>

                        <div class="alerta-form-actions multirrisco-chart-actions">
                            <div class="alerta-form-actions-left">
                                <span class="alerta-inline-note">O IRP combina gravidade e abrangência territorial dos alertas ativos.</span>
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
                <span class="alerta-form-kicker">Central analítica</span>
                <h2 class="alerta-form-title">Relatório analítico multirriscos</h2>
                <p class="alerta-form-description">
                    A consulta abaixo abre um relatório consolidado direto na tela, e sem
                    depender de login. O objetivo aqui é leitura institucional e consulta pública qualificada.
                </p>
            </div>

            <div class="alerta-form-summary">
                <div class="alerta-summary-card">
                    <span class="alerta-summary-label">Módulos integrados</span>
                    <span class="alerta-summary-value"><?= count($analises) ?> leituras</span>
                    <span class="alerta-summary-note">Temporal, severidade, tipologia e índices em um mesmo fluxo.</span>
                </div>
                <div class="alerta-summary-card">
                    <span class="alerta-summary-label">Recorte global</span>
                    <span class="alerta-summary-value">Ano, mês, região e município</span>
                    <span class="alerta-summary-note">O mesmo recorte alimenta todos os blocos da tela analítica.</span>
                </div>
                <div class="alerta-summary-card">
                    <span class="alerta-summary-label">Experiência pública</span>
                    <span class="alerta-summary-value">Visual e responsiva</span>
                    <span class="alerta-summary-note">Feita para leitura rápida sem expor funções operacionais internas.</span>
                </div>
            </div>
        </div>

        <div class="alerta-form-panel">
            <div class="alerta-form-grid analises-overview-grid">
                <section class="alerta-form-section">
                    <header class="alerta-section-header">
                        <span class="alerta-section-kicker">Seção 1</span>
                        <h2 class="alerta-section-title">Recorte global do relatório</h2>
                        <p class="alerta-section-text">Defina o recorte desejado para abrir a síntese analítica consolidada na tela.</p>
                    </header>

                    <div class="analises-filter-grid">
                        <div class="form-group">
                            <label for="filtroAno">Ano</label>
                            <select id="filtroAno"></select>
                        </div>
                        <div class="form-group">
                            <label for="filtroMes">Mês</label>
                            <select id="filtroMes"></select>
                        </div>
                        <div class="form-group">
                            <label for="filtroRegiao">Região de integração</label>
                            <select id="filtroRegiao"></select>
                        </div>
                        <div class="form-group">
                            <label for="filtroMunicipio">Município</label>
                            <select id="filtroMunicipio">
                                <option value="">Todos</option>
                            </select>
                        </div>

                        <div class="alerta-callout analises-filter-callout form-group field-span-2">
                            <strong>Relatório público</strong>
                            A tela abaixo organiza a leitura consolidada para consulta institucional sem função de impressão.
                        </div>

                        <div class="alerta-form-actions analises-filter-actions form-group field-span-2">
                            <div class="alerta-form-actions-left">
                                <span class="alerta-inline-note">Use esse recorte para abrir um panorama integrado antes de aprofundar a leitura no mapa.</span>
                            </div>
                            <div class="alerta-form-actions-right">
                                <button id="btnGerarRelatorio" type="button" class="btn btn-primary">Gerar relatório analítico</button>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="alerta-form-section">
                    <header class="alerta-section-header">
                        <span class="alerta-section-kicker">Seção 2</span>
                        <h2 class="alerta-section-title">Como a análise está organizada</h2>
                        <p class="alerta-section-text">Clique em um módulo para abrir a página pública correspondente com filtros e gráficos reais no mesmo padrão das telas internas.</p>
                    </header>

                    <div class="analises-insight-grid public-analises-insight-grid">
                        <?php foreach ($analises as $analise): ?>
                            <a
                                href="<?= htmlspecialchars((string) $analise['href'], ENT_QUOTES, 'UTF-8') ?>?embed=1"
                                data-href-base="<?= htmlspecialchars((string) $analise['href'], ENT_QUOTES, 'UTF-8') ?>"
                                data-analise-link="1"
                                class="alerta-summary-card analises-mini-card public-analise-card"
                            >
                                <span class="alerta-summary-label"><?= htmlspecialchars($analise['kicker'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="alerta-summary-value"><?= htmlspecialchars($analise['titulo'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="alerta-summary-note"><?= htmlspecialchars($analise['descricao'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="public-analise-card-footer">
                                    <span class="analises-chip"><?= count($analise['graficos']) ?> gráficos</span>
                                    <span class="public-analise-card-link">Abrir página pública</span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <div class="alerta-callout analises-info-callout">
                        <strong>Uso recomendado</strong>
                        Combine o relatório consolidado com a prévia de cada página para decidir rapidamente qual análise detalhada faz mais sentido para o recorte consultado.
                    </div>
                </section>
            </div>
        </div>
    </section>

    <section class="dashboard public-section" id="alertas-ativos">
        <div class="public-section-header">
            <div>
                <span class="alerta-form-kicker">Download público</span>
                <h2>Alertas ativos disponíveis em PDF</h2>
                <p>Somente alertas com status ativo aparecem nesta vitrine pública. Cada card libera o download direto do PDF oficial do alerta.</p>
            </div>

            <div class="public-section-meta">
                <span class="analises-chip"><?= count($alertasPublicos) ?> alertas destacados</span>
                <span class="analises-chip"><?= $totalAtivos ?> PDFs ativos</span>
            </div>
        </div>

        <?php if (!$alertasPublicos): ?>
            <div class="multirrisco-empty-box">Não há alertas ativos disponíveis para download no momento.</div>
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
                            <span><strong>Vigência:</strong> <?= htmlspecialchars(TimeHelper::formatDateTime((string) ($alerta['inicio_alerta'] ?? null)), ENT_QUOTES, 'UTF-8') ?> até <?= htmlspecialchars(TimeHelper::formatDateTime((string) ($alerta['fim_alerta'] ?? null)), ENT_QUOTES, 'UTF-8') ?></span>
                            <span><strong>Municípios:</strong> <?= (int) ($alerta['total_municipios'] ?? 0) ?></span>
                        </div>

                        <p class="public-alert-region">
                            <strong>Regiões:</strong>
                            <?= htmlspecialchars((string) ($alerta['regioes'] ?: 'Não informadas'), ENT_QUOTES, 'UTF-8') ?>
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
        <p><?= htmlspecialchars($appConfig['department'], ENT_QUOTES, 'UTF-8') ?> com acesso visual ao mapa multirriscos, relatório consolidado e PDFs dos alertas ativos.</p>
        <div class="public-footer-pills">
            <span class="analises-chip"><?= htmlspecialchars($appConfig['name'], ENT_QUOTES, 'UTF-8') ?></span>
            <span class="analises-chip">Versão <?= htmlspecialchars($appConfig['version'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>
    <div>
        <?= htmlspecialchars($appConfig['name'], ENT_QUOTES, 'UTF-8') ?> · Versão <strong><?= htmlspecialchars($appConfig['version'], ENT_QUOTES, 'UTF-8') ?></strong>
    </div>
    <div>
        Suporte: <a href="mailto:<?= htmlspecialchars($appConfig['support_email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($appConfig['support_email'], ENT_QUOTES, 'UTF-8') ?></a>
    </div>
    <div class="public-footer-grid">
        <section class="public-footer-card">
            <span class="public-footer-kicker">Navegação rápida</span>
            <a href="#mapa-publico">Mapa ao vivo</a>
            <a href="#analises-publicas">Análises e relatório</a>
            <a href="#alertas-ativos">Alertas ativos</a>
        </section>

        <section class="public-footer-card">
            <span class="public-footer-kicker">Experiência pública</span>
            <p>Consulta territorial, leitura analítica e download dos PDFs ativos sem necessidade de autenticação.</p>
        </section>

        <section class="public-footer-card">
            <span class="public-footer-kicker">Acesso e suporte</span>
            <p>Suporte institucional para acesso autenticado e orientações operacionais.</p>
            <a href="mailto:<?= htmlspecialchars($appConfig['support_email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($appConfig['support_email'], ENT_QUOTES, 'UTF-8') ?></a>
        </section>
    </div>
</footer>

<div id="modalTerritorio" class="modal-territorio" aria-hidden="true">
    <div class="modal-territorio-dialog" role="dialog" aria-modal="true" aria-labelledby="modalTerritorioTitulo">
        <div class="modal-territorio-header">
            <div class="modal-territorio-header-copy">
                <span class="modal-territorio-kicker" id="modalTerritorioKicker">Território</span>
                <h3 id="modalTerritorioTitulo">Detalhamento territorial</h3>
                <p id="modalTerritorioResumo">Carregando detalhamento do território selecionado.</p>
            </div>
            <button type="button" class="modal-territorio-close" data-close-territorio aria-label="Fechar modal">X</button>
        </div>
        <div class="modal-territorio-body" id="modalTerritorioBody"></div>
    </div>
</div>

<div id="modalIRP" class="modal modal-irp" aria-hidden="true">
    <div class="modal-conteudo irp-modal-conteudo" role="dialog" aria-modal="true" aria-labelledby="modalIrpTitulo">
        <div class="irp-modal-header">
            <div class="irp-modal-heading">
                <span class="irp-modal-kicker">Índice de pressão</span>
                <h3 id="modalIrpTitulo">Como o IRP é calculado</h3>
                <p>O índice de pressão de risco mede a carga operacional causada pelos alertas ativos no território filtrado.</p>
            </div>
            <button type="button" class="irp-modal-close" data-close-irp aria-label="Fechar modal">X</button>
        </div>

        <div class="irp-modal-body">
            <p>A leitura considera o peso da gravidade e a abrangência municipal de cada alerta ativo.</p>

            <div class="irp-modal-peso-grid">
                <article class="irp-peso-item">
                    <strong>Baixo</strong>
                    <span>Peso 1</span>
                </article>
                <article class="irp-peso-item">
                    <strong>Moderado</strong>
                    <span>Peso 2</span>
                </article>
                <article class="irp-peso-item">
                    <strong>Alto</strong>
                    <span>Peso 3</span>
                </article>
                <article class="irp-peso-item">
                    <strong>Muito alto</strong>
                    <span>Peso 4</span>
                </article>
                <article class="irp-peso-item">
                    <strong>Extremo</strong>
                    <span>Peso 5</span>
                </article>
            </div>

            <div class="alerta-callout irp-modal-callout">
                <strong>Interpretação</strong>
                Quanto maior o IRP, maior a pressão territorial e a necessidade de resposta.
            </div>
        </div>

        <div class="irp-modal-footer">
            <button type="button" class="btn btn-secondary" data-close-irp>Fechar</button>
        </div>
    </div>
</div>

<div id="modalAjuda" class="modal-ajuda">
    <div class="modal-ajuda-conteudo">
        <div class="modal-ajuda-header">
            <div class="modal-ajuda-heading">
                <span class="modal-ajuda-kicker">Guia rápido</span>
                <h3>Como usar o mapa multirriscos</h3>
                <p>Um roteiro simples para filtrar, interpretar o mapa e abrir o detalhamento territorial com rapidez.</p>
            </div>
            <button type="button" data-close-ajuda aria-label="Fechar ajuda">X</button>
        </div>

        <div class="modal-ajuda-body">
            <section class="modal-ajuda-hero">
                <div class="modal-ajuda-hero-copy">
                    <strong>Leitura recomendada</strong>
                    <p>Comece pelos filtros, use o mapa como referência central e abra os modais territoriais para entender cada alerta ativo.</p>
                </div>
                <div class="modal-ajuda-pill-row">
                    <span class="modal-ajuda-pill">1. Filtre o cenário</span>
                    <span class="modal-ajuda-pill">2. Observe o destaque territorial</span>
                    <span class="modal-ajuda-pill">3. Abra o detalhamento</span>
                </div>
            </section>

            <div class="modal-ajuda-grid">
                <article class="modal-ajuda-card modal-ajuda-card-flow">
                    <span class="modal-ajuda-card-kicker">Passo a passo</span>
                    <h4>Fluxo ideal de uso</h4>
                    <ol class="modal-ajuda-sequencia">
                        <li>Defina período, evento, gravidade, fonte, região ou município.</li>
                        <li>Observe o destaque automático da camada territorial no mapa.</li>
                        <li>Use o ranking regional para priorizar a leitura das áreas mais pressionadas.</li>
                        <li>Clique no território para abrir o detalhamento com todos os alertas ativos.</li>
                    </ol>
                </article>

                <article class="modal-ajuda-card">
                    <span class="modal-ajuda-card-kicker">Detalhamento</span>
                    <h4>O que aparece nos modais territoriais</h4>
                    <ul class="modal-ajuda-lista">
                        <li>Nome do território consultado e pressão acumulada.</li>
                        <li>Quantidade de alertas ativos e tipos de evento presentes no recorte.</li>
                        <li>Número do alerta, data, vigência, gravidade, pressão e fonte de cada alerta ativo.</li>
                        <li>Quando houver mais de um alerta ativo, cada alerta aparece discriminado na tela.</li>
                    </ul>
                </article>

                <article class="modal-ajuda-card">
                    <span class="modal-ajuda-card-kicker">Leitura visual</span>
                    <h4>Como interpretar as cores do mapa</h4>
                    <ul class="modal-ajuda-lista">
                        <li>Quanto mais intensa a cor, maior a pressão operacional do território.</li>
                        <li>O modo territorial permite alternar entre municípios e regiões.</li>
                        <li>O gráfico do IRP ajuda a identificar rapidamente janelas de maior pressão.</li>
                        <li>O ranking regional facilita localizar as áreas mais críticas sem perder contexto visual.</li>
                    </ul>
                </article>
            </div>
        </div>

        <div class="modal-ajuda-footer">
            <span class="modal-ajuda-footer-note">Dica: combine os filtros territoriais com o relatório analítico para uma leitura mais completa.</span>
            <button type="button" data-close-ajuda>Fechar</button>
        </div>
    </div>
</div>

<div id="modalRelatorio" class="modal" style="display:none;">
    <div class="modal-content analises-modal-content">
        <div class="modal-header analises-modal-header">
            <div>
                <span class="analises-modal-kicker">Relatório consolidado</span>
                <h2>Relatório analítico multirriscos</h2>
            </div>
            <button id="fecharModal" type="button" class="analises-modal-close" aria-label="Fechar relatório">&times;</button>
        </div>
        <div id="conteudoRelatorio" class="analises-modal-body"></div>
    </div>
</div>

<div id="modalInfo" class="modal">
    <div class="modal-content public-info-modal">
        <button type="button" class="public-modal-close" data-close-modal="modalInfo" aria-label="Fechar sobre o sistema">&times;</button>
        <div class="public-info-modal-header">
            <span class="public-info-modal-kicker">Sobre o sistema</span>
            <h3>Sistema Inteligente Multirriscos</h3>
            <p>Plataforma institucional da Defesa Civil do Estado do Pará para monitoramento, leitura territorial e apoio à tomada de decisão com base nos alertas ativos.</p>
        </div>

        <div class="public-info-modal-pill-row">
            <span class="public-info-modal-pill">Painel público</span>
            <span class="public-info-modal-pill">Mapa ao vivo</span>
            <span class="public-info-modal-pill">Relatório analítico</span>
            <span class="public-info-modal-pill">Versão <?= htmlspecialchars($appConfig['version'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <div class="public-info-modal-grid">
            <article class="public-info-modal-card">
                <span class="public-info-modal-card-kicker">Consulta aberta</span>
                <strong>Leitura pública em tempo real</strong>
                <p>Qualquer visitante consegue visualizar mapa, indicadores e fazer download dos PDFs dos alertas ativos diretamente na página inicial.</p>
            </article>

            <article class="public-info-modal-card">
                <span class="public-info-modal-card-kicker">Ambiente protegido</span>
                <strong>Fluxos internos com autenticação</strong>
                <p>Cadastro, gestão operacional, histórico e páginas internas permanecem restritos aos perfis autorizados da Defesa Civil.</p>
            </article>
        </div>

        <div class="public-info-modal-footer">
            <div class="public-info-modal-support">
                <strong>Solicitação de acesso</strong>
                <a href="mailto:<?= $supportEmailSafe ?>"><?= $supportEmailSafe ?></a>
            </div>
            <button type="button" class="btn btn-secondary" data-close-modal="modalInfo">Fechar</button>
        </div>
    </div>
</div>

<script nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>" id="multirrisco-bootstrap" type="application/json"><?= json_encode([
    'regioes' => $regioesDisponiveis,
    'municipios' => $municipiosDisponiveis,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<script nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>">
window.MULTIRRISCO_CONFIG = { enableCompdec: false };
window.ANALISE_GLOBAL_CONFIG = { pdfEnabled: false };

(function () {
    const modalInfo = document.getElementById('modalInfo');
    const loginEmail = document.getElementById('login-email');

    function modalVisivel(modal) {
        return !!modal && window.getComputedStyle(modal).display !== 'none';
    }

    function atualizarScrollBody() {
        const existeModalAberto = [modalInfo].some(modalVisivel);
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
        atualizarScrollBody();
    }

    function valorSelecionado(id) {
        const select = document.getElementById(id);
        if (!select) {
            return '';
        }

        return String(select.value || '').trim();
    }

    function urlAnalisePublica(baseHref) {
        const url = new URL(baseHref || '/pages/analises/index.php', window.location.origin);
        const params = new URLSearchParams();
        const ano = valorSelecionado('filtroAno');
        const mes = valorSelecionado('filtroMes');
        const regiao = valorSelecionado('filtroRegiao');
        const municipio = valorSelecionado('filtroMunicipio');

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

        url.search = params.toString();

        return url.pathname + url.search;
    }

    function atualizarLinksAnaliticosPublicos() {
        const links = document.querySelectorAll('[data-analise-link]');

        links.forEach(function (link) {
            const baseHref = link.getAttribute('data-href-base') || link.getAttribute('href') || '/pages/analises/index.php';
            link.setAttribute('href', urlAnalisePublica(baseHref));
        });
    }

    document.addEventListener('click', function (event) {
        const openInfo = event.target.closest('[data-open-info]');
        const closeButton = event.target.closest('[data-close-modal]');

        if (openInfo) {
            abrirModal(modalInfo);
            return;
        }

        if (closeButton) {
            fecharModalPersonalizado(document.getElementById(closeButton.getAttribute('data-close-modal')));
            return;
        }

        if (event.target === modalInfo) {
            fecharModalPersonalizado(modalInfo);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        if (modalVisivel(modalInfo)) {
            fecharModalPersonalizado(modalInfo);
        }
    });

    ['filtroAno', 'filtroMes', 'filtroRegiao', 'filtroMunicipio'].forEach(function (id) {
        const select = document.getElementById(id);

        if (select) {
            select.addEventListener('change', atualizarLinksAnaliticosPublicos);
        }
    });

    atualizarLinksAnaliticosPublicos();
    window.setTimeout(atualizarLinksAnaliticosPublicos, 300);
    window.setTimeout(atualizarLinksAnaliticosPublicos, 1200);

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
<script src="/assets/js/app-shell.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/assets/vendor/chartjs/chart-lite.js"></script>
<script src="/assets/js/pages/mapas-mapa_multirriscos.js?v=<?= htmlspecialchars($jsMapaMultirriscosVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="/assets/js/analise-global.js?v=<?= htmlspecialchars($jsAnaliseGlobalVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
