<?php
require_once __DIR__ . '/Session.php';

class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        Session::start();

        if (
            empty($_SESSION[self::SESSION_KEY]) ||
            !is_string($_SESSION[self::SESSION_KEY])
        ) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function inputField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' .
            htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') .
            '">';
    }

    public static function validateRequestOrFail(): void
    {
        $requestToken = self::requestToken();

        if (!is_string($requestToken) || !hash_equals(self::token(), $requestToken)) {
            http_response_code(419);

            if (self::expectsJson()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok' => false,
                    'erro' => 'Token CSRF invalido ou expirado. Recarregue a pagina e tente novamente.',
                ], JSON_UNESCAPED_UNICODE);
            } else {
                die('Token CSRF invalido ou expirado. Recarregue a pagina e tente novamente.');
            }

            exit;
        }
    }

    private static function requestToken(): ?string
    {
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (is_string($headerToken) && trim($headerToken) !== '') {
            return trim($headerToken);
        }

        $postToken = $_POST['csrf_token'] ?? null;

        if (is_string($postToken) && trim($postToken) !== '') {
            return trim($postToken);
        }

        return null;
    }

    private static function expectsJson(): bool
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');

        return str_contains($contentType, 'application/json') ||
            str_contains($accept, 'application/json') ||
            str_starts_with($uri, '/api/');
    }
}
