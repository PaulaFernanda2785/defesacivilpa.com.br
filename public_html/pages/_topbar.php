<?php
require_once __DIR__ . '/../app/Core/AppConfig.php';

$appConfig = AppConfig::get();
$usuarioTopo = $_SESSION['usuario'] ?? [];
?>
<header class="topbar">
    <button
        type="button"
        class="sidebar-toggle"
        data-sidebar-toggle
        aria-label="Abrir menu de navegacao"
        aria-expanded="false"
    >
        <span></span>
        <span></span>
        <span></span>
    </button>

    <div class="topbar-brand">
        <span class="topbar-kicker"><?= htmlspecialchars($appConfig['name']) ?></span>
        <strong class="topbar-institution topbar-institution-desktop"><?= htmlspecialchars($appConfig['institution']) ?></strong>
        <strong class="topbar-institution topbar-institution-mobile">Defesa Civil do Par&aacute;</strong>
        <small><?= htmlspecialchars($appConfig['department']) ?></small>
    </div>

    <div class="topbar-meta" aria-label="Status do ambiente">
        <span class="topbar-pill topbar-pill-neutral"><?= htmlspecialchars($appConfig['version']) ?></span>
        <span class="topbar-pill topbar-pill-<?= htmlspecialchars($appConfig['environment_class']) ?>">
            <?= htmlspecialchars($appConfig['environment_label']) ?>
        </span>
    </div>

    <div class="user-info">
        <span><?= htmlspecialchars($usuarioTopo['nome'] ?? 'Usuario autenticado') ?></span>
        <small><?= htmlspecialchars($usuarioTopo['perfil'] ?? '') ?></small>
        <a href="/logout.php" class="logout">Sair</a>
    </div>
</header>
