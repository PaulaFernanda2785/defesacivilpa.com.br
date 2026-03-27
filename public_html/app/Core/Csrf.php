<?php
require_once __DIR__ . '/Session.php';

class Csrf
{
    private const SESSION_KEY = '_csrf_token';
    private const IDEMPOTENCY_SESSION_KEY = '_idempotency_tokens';
    private const IDEMPOTENCY_FIELD = 'request_token';
    private const IDEMPOTENCY_HEADER = 'HTTP_X_IDEMPOTENCY_KEY';
    private const IDEMPOTENCY_DUPLICATE_WINDOW_SECONDS = 5;
    private const IDEMPOTENCY_TOKEN_MAX_AGE_SECONDS = 1800;
    private const IDEMPOTENCY_MAX_TOKENS = 1000;
    private static bool $requestValidated = false;

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
        $csrfToken = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        $requestToken = htmlspecialchars(self::issueIdempotencyToken(), ENT_QUOTES, 'UTF-8');

        return '<input type="hidden" name="csrf_token" value="' . $csrfToken . '">' .
            PHP_EOL .
            '<input type="hidden" name="' . self::IDEMPOTENCY_FIELD . '" value="' . $requestToken . '">';
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

        self::validateIdempotencyOrFail();
        self::$requestValidated = true;
    }

    public static function currentRequestIsValidated(): bool
    {
        return self::$requestValidated;
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

    private static function validateIdempotencyOrFail(): void
    {
        $requestToken = self::requestIdempotencyToken();

        if (!is_string($requestToken) || trim($requestToken) === '') {
            return;
        }

        $status = self::consumeIdempotencyToken($requestToken);

        if ($status === 'ok') {
            return;
        }

        http_response_code($status === 'duplicate' ? 409 : 419);

        if (self::expectsJson()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'erro' => $status === 'duplicate'
                    ? 'Esta solicitacao ja foi processada ha instantes. Aguarde e tente novamente.'
                    : 'Token de envio invalido ou expirado. Recarregue a pagina e tente novamente.',
                'duplicado' => $status === 'duplicate',
            ], JSON_UNESCAPED_UNICODE);
        } else {
            if ($status === 'duplicate') {
                die('Esta solicitacao ja foi processada ha instantes. Aguarde alguns segundos e tente novamente.');
            }

            die('Token de envio invalido ou expirado. Recarregue a pagina e tente novamente.');
        }

        exit;
    }

    private static function requestIdempotencyToken(): ?string
    {
        $headerToken = $_SERVER[self::IDEMPOTENCY_HEADER] ?? null;

        if (is_string($headerToken) && trim($headerToken) !== '') {
            return trim($headerToken);
        }

        $postToken = $_POST[self::IDEMPOTENCY_FIELD] ?? null;

        if (is_string($postToken) && trim($postToken) !== '') {
            return trim($postToken);
        }

        return null;
    }

    private static function issueIdempotencyToken(): string
    {
        Session::start();
        self::pruneIdempotencyStore();

        $store = self::idempotencyStore();
        $token = bin2hex(random_bytes(32));
        $store['issued'][$token] = time();

        self::trimIdempotencyStore($store['issued']);
        $_SESSION[self::IDEMPOTENCY_SESSION_KEY] = $store;

        return $token;
    }

    private static function consumeIdempotencyToken(string $token): string
    {
        Session::start();
        self::pruneIdempotencyStore();

        $store = self::idempotencyStore();
        $now = time();
        $lastProcessedAt = $store['processed'][$token] ?? null;

        if (is_int($lastProcessedAt)) {
            if (($now - $lastProcessedAt) <= self::IDEMPOTENCY_DUPLICATE_WINDOW_SECONDS) {
                return 'duplicate';
            }

            return 'invalid';
        }

        $issuedAt = $store['issued'][$token] ?? null;

        if (!is_int($issuedAt)) {
            return 'invalid';
        }

        if (($now - $issuedAt) > self::IDEMPOTENCY_TOKEN_MAX_AGE_SECONDS) {
            unset($store['issued'][$token]);
            $_SESSION[self::IDEMPOTENCY_SESSION_KEY] = $store;
            return 'invalid';
        }

        unset($store['issued'][$token]);
        $store['processed'][$token] = $now;
        self::trimIdempotencyStore($store['processed']);
        $_SESSION[self::IDEMPOTENCY_SESSION_KEY] = $store;

        return 'ok';
    }

    private static function pruneIdempotencyStore(): void
    {
        $store = self::idempotencyStore();
        $cutoff = time() - self::IDEMPOTENCY_TOKEN_MAX_AGE_SECONDS;

        foreach ($store['issued'] as $token => $issuedAt) {
            if (!is_int($issuedAt) || $issuedAt < $cutoff) {
                unset($store['issued'][$token]);
            }
        }

        foreach ($store['processed'] as $token => $processedAt) {
            if (!is_int($processedAt) || $processedAt < $cutoff) {
                unset($store['processed'][$token]);
            }
        }

        self::trimIdempotencyStore($store['issued']);
        self::trimIdempotencyStore($store['processed']);
        $_SESSION[self::IDEMPOTENCY_SESSION_KEY] = $store;
    }

    private static function trimIdempotencyStore(array &$bucket): void
    {
        if (count($bucket) <= self::IDEMPOTENCY_MAX_TOKENS) {
            return;
        }

        asort($bucket, SORT_NUMERIC);

        while (count($bucket) > self::IDEMPOTENCY_MAX_TOKENS) {
            array_shift($bucket);
        }
    }

    private static function idempotencyStore(): array
    {
        $store = $_SESSION[self::IDEMPOTENCY_SESSION_KEY] ?? null;

        if (!is_array($store)) {
            $store = [];
        }

        $issued = $store['issued'] ?? [];
        $processed = $store['processed'] ?? [];

        if (!is_array($issued)) {
            $issued = [];
        }

        if (!is_array($processed)) {
            $processed = [];
        }

        return [
            'issued' => $issued,
            'processed' => $processed,
        ];
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
