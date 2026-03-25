<?php
require_once __DIR__ . '/../app/Core/AppConfig.php';
require_once __DIR__ . '/../app/Core/Csrf.php';

$appConfig = AppConfig::get();
?>
<footer class="footer-sistema">
    <div class="footer-container">

        <div class="footer-identidade">
            <strong><?= htmlspecialchars($appConfig['institution']) ?></strong><br>
            <?= htmlspecialchars($appConfig['department']) ?>
        </div>

        <div class="footer-sistema-info">
            <?= htmlspecialchars($appConfig['name']) ?> &bull;
            Versao <strong><?= htmlspecialchars($appConfig['version']) ?></strong> &bull;
            <span class="ambiente <?= htmlspecialchars($appConfig['environment_class']) ?>">
                <?= htmlspecialchars($appConfig['environment_label']) ?>
            </span>
        </div>

        <div class="footer-direitos">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($appConfig['organization']) ?>
        </div>

    </div>
</footer>

<script>
window.APP_CSRF_TOKEN = <?= json_encode(Csrf::token()) ?>;
window.APP_SESSION_IS_AUTHENTICATED = <?= json_encode(!empty($_SESSION['usuario'])) ?>;
window.APP_SESSION_TIMEOUT = <?= json_encode(Session::inactivityTimeout()) ?>;
window.APP_SESSION_WARNING = 120;
</script>
<script src="/assets/js/app-shell.js"></script>
