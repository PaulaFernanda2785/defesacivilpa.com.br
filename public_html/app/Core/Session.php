<?php
class Session {
    private const LAST_ACTIVITY_KEY = '_last_activity_at';
    private const INACTIVITY_TIMEOUT_SECONDS = 1800;

    public static function start() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (PHP_SAPI !== 'cli') {
            $isHttps = (
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
            );

            session_name(self::resolveSessionName());
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');

        session_start();
    }

    private static function resolveSessionName(): string
    {
        $configuredName = $_ENV['SESSION_NAME'] ?? getenv('SESSION_NAME');

        if (is_string($configuredName)) {
            $configuredName = trim($configuredName);

            if (preg_match('/^[A-Za-z][A-Za-z0-9_]{2,64}$/', $configuredName) === 1) {
                return $configuredName;
            }
        }

        $appPublicRoot = realpath(dirname(__DIR__, 2)) ?: dirname(__DIR__, 2);
        $fingerprint = substr(hash('sha256', (string) $appPublicRoot), 0, 12);

        return 'sim_multirriscos_' . $fingerprint;
    }

    public static function inactivityTimeout(): int
    {
        return self::INACTIVITY_TIMEOUT_SECONDS;
    }

    public static function regenerate(bool $deleteOldSession = true): void
    {
        self::start();
        session_regenerate_id($deleteOldSession);
    }

    public static function touchActivity(): void
    {
        self::start();

        if (isset($_SESSION['usuario'])) {
            $_SESSION[self::LAST_ACTIVITY_KEY] = time();
        }
    }

    public static function isExpiredByInactivity(?int $timeoutSeconds = null): bool
    {
        self::start();

        if (!isset($_SESSION['usuario'])) {
            return false;
        }

        $timeout = max(60, (int) ($timeoutSeconds ?? self::INACTIVITY_TIMEOUT_SECONDS));
        $lastActivity = (int) ($_SESSION[self::LAST_ACTIVITY_KEY] ?? 0);

        if ($lastActivity <= 0) {
            $_SESSION[self::LAST_ACTIVITY_KEY] = time();
            return false;
        }

        return (time() - $lastActivity) >= $timeout;
    }

    public static function destroy() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();

                setcookie(session_name(), '', [
                    'expires' => time() - 42000,
                    'path' => $params['path'] ?? '/',
                    'domain' => $params['domain'] ?? '',
                    'secure' => (bool) ($params['secure'] ?? false),
                    'httponly' => (bool) ($params['httponly'] ?? true),
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]);
            }

            session_destroy();
        }
    }
}
