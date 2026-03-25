<?php
require_once __DIR__ . '/../app/Core/AppConfig.php';

$usuario = $_SESSION['usuario'] ?? [];
$appConfig = AppConfig::get();
$currentPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$matchesCurrentPath = static function (string $path, array $item): bool {
    foreach (($item['exclude_prefixes'] ?? []) as $excludePrefix) {
        if ($excludePrefix !== '' && str_starts_with($path, $excludePrefix)) {
            return false;
        }
    }

    if ($path === ($item['href'] ?? '')) {
        return true;
    }

    $prefix = (string) ($item['prefix'] ?? '');

    return $prefix !== '' && str_starts_with($path, $prefix);
};

$navSections = [
    [
        'title' => 'Operacao',
        'items' => [
            [
                'href' => '/pages/painel.php',
                'prefix' => '/pages/painel.php',
                'label' => 'Painel',
                'caption' => 'Visao geral e indicadores operacionais',
            ],
            [
                'href' => '/pages/alertas/listar.php',
                'prefix' => '/pages/alertas/',
                'label' => 'Alertas',
                'caption' => 'Cadastro, controle e acompanhamento',
            ],
            [
                'href' => '/pages/analises/index.php',
                'prefix' => '/pages/analises/',
                'label' => 'Analises multirriscos',
                'caption' => 'Leituras analiticas e series historicas',
            ],
            [
                'href' => '/pages/mapas/mapa_multirriscos.php',
                'prefix' => '/pages/mapas/',
                'label' => 'Mapa multirriscos',
                'caption' => 'Camadas territoriais e pressao de risco',
            ],
        ],
    ],
    [
        'title' => 'Conta',
        'items' => [
            [
                'href' => '/pages/usuarios/senha.php',
                'prefix' => '/pages/usuarios/senha.php',
                'label' => 'Alterar senha',
                'caption' => 'Redefina sua credencial de acesso',
            ],
        ],
    ],
];

if (in_array($usuario['perfil'] ?? '', ['ADMIN', 'GESTOR'], true)) {
    $navSections[] = [
        'title' => 'Gestao',
        'items' => [
            [
                'href' => '/pages/historico/index.php',
                'prefix' => '/pages/historico/',
                'label' => 'Historico do usuario',
                'caption' => 'Rastreabilidade das operacoes do sistema',
            ],
        ],
    ];
}

if (($usuario['perfil'] ?? '') === 'ADMIN') {
    $ultimaSecao = array_key_last($navSections);

    if ($ultimaSecao === null || $navSections[$ultimaSecao]['title'] !== 'Gestao') {
        $navSections[] = [
            'title' => 'Gestao',
            'items' => [],
        ];
        $ultimaSecao = array_key_last($navSections);
    }

    $navSections[$ultimaSecao]['items'][] = [
        'href' => '/pages/usuarios/listar.php',
        'prefix' => '/pages/usuarios/',
        'exclude_prefixes' => ['/pages/usuarios/senha.php'],
        'label' => 'Usuarios',
        'caption' => 'Perfis, acessos e administracao interna',
    ];
}

$paginaAtualLabel = 'Painel';

foreach ($navSections as $section) {
    foreach ($section['items'] as $item) {
        $isActive = $matchesCurrentPath($currentPath, $item);

        if ($isActive) {
            $paginaAtualLabel = $item['label'];
            break 2;
        }
    }
}
?>
<aside class="sidebar" id="appSidebar" aria-label="Menu principal">
    <button type="button" class="sidebar-close" data-sidebar-close aria-label="Fechar menu">
        <span></span>
        <span></span>
    </button>

    <div class="sidebar-shell">
        <div class="sidebar-head">
            <div class="logo">
                <img src="/assets/images/logo-cedec.png" alt="CEDEC-PA">
                <div class="logo-copy">
                    <span class="logo-kicker">Painel integrado</span>
                    <h2><?= htmlspecialchars($appConfig['name']) ?></h2>
                </div>
            </div>

            <div class="sidebar-highlight">
                <span class="sidebar-highlight-kicker">Modulo atual</span>
                <strong><?= htmlspecialchars($paginaAtualLabel) ?></strong>
                <p>Navegacao principal com acesso rapido aos modulos operacionais e de gestao.</p>
            </div>
        </div>

        <nav class="sidebar-nav" aria-label="Atalhos do sistema">
            <?php foreach ($navSections as $section): ?>
                <div class="sidebar-nav-group">
                    <span class="sidebar-nav-title"><?= htmlspecialchars($section['title']) ?></span>

                    <?php foreach ($section['items'] as $item): ?>
                        <?php $isActive = $matchesCurrentPath($currentPath, $item); ?>
                        <a
                            href="<?= htmlspecialchars($item['href']) ?>"
                            class="<?= $isActive ? 'active' : '' ?>"
                            <?= $isActive ? 'aria-current="page"' : '' ?>
                        >
                            <span class="nav-text-wrap">
                                <span class="nav-text"><?= htmlspecialchars($item['label']) ?></span>
                                <small class="nav-caption"><?= htmlspecialchars($item['caption']) ?></small>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="sistema-nome"><?= htmlspecialchars($appConfig['name']) ?></div>

            <div class="sistema-info">
                Versao <strong><?= htmlspecialchars($appConfig['version']) ?></strong><br>
                Ambiente:
                <span class="ambiente <?= htmlspecialchars($appConfig['environment_class']) ?>">
                    <?= htmlspecialchars($appConfig['environment_label']) ?>
                </span>
            </div>

            <div class="sidebar-footer-note">
                Navegacao otimizada para acompanhamento, cadastro e resposta operacional.
            </div>
        </div>
    </div>
</aside>
<div class="sidebar-backdrop" data-sidebar-backdrop hidden></div>
