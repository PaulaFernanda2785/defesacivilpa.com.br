<?php
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Csrf.php';
require_once __DIR__ . '/../Helpers/TimeHelper.php';

class Protect {

    public static function check(array $perfisPermitidos = []) {
        TimeHelper::bootstrap();

        if (!Auth::check()) {
            self::respondUnauthorized('Sessao expirada. Faca login novamente.');
        }

        if (Session::isExpiredByInactivity()) {
            Session::destroy();
            self::respondUnauthorized('Sessao expirada por inatividade.', true);
        }

        if (self::requiresCsrfValidation()) {
            Csrf::validateRequestOrFail();
        }

        Session::touchActivity();

        if (!empty($perfisPermitidos)) {
            $perfil = $_SESSION['usuario']['perfil'] ?? '';
            if (!in_array($perfil, $perfisPermitidos)) {
                http_response_code(403);
                die('Acesso negado.');
            }
        }

    }

    private static function requiresCsrfValidation(): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        return in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    private static function respondUnauthorized(string $message, bool $expiredByInactivity = false): void
    {
        if (self::expectsJsonRequest()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'erro' => $message,
                'motivo' => $expiredByInactivity ? 'inatividade' : 'autenticacao',
                'redirect' => $expiredByInactivity ? '/index.php?motivo=inatividade' : '/index.php',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Location: ' . ($expiredByInactivity ? '/index.php?motivo=inatividade' : '/index.php'));
        exit;
    }

    private static function expectsJsonRequest(): bool
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

        return str_contains($contentType, 'application/json') ||
            str_contains($accept, 'application/json') ||
            str_starts_with($uri, '/api/') ||
            str_contains($requestedWith, 'xmlhttprequest');
    }
}
