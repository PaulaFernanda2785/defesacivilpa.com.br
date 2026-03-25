<?php

class SecurityHeaders
{
    private static ?string $scriptNonce = null;

    public static function applyHtmlPage(): void
    {
        self::applyCommon();
        header('Content-Type: text/html; charset=utf-8');
    }

    public static function scriptNonce(): string
    {
        if (self::$scriptNonce === null) {
            self::$scriptNonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        }

        return self::$scriptNonce;
    }

    public static function applyPublicCsp(array $options = []): void
    {
        if (headers_sent()) {
            return;
        }

        $scriptSources = $options['script_src'] ?? ["'self'"];
        $styleSources = $options['style_src'] ?? ["'self'", "'unsafe-inline'"];
        $imgSources = $options['img_src'] ?? ["'self'", 'data:', 'blob:'];
        $fontSources = $options['font_src'] ?? ["'self'", 'data:'];
        $connectSources = $options['connect_src'] ?? ["'self'"];
        $frameSources = $options['frame_src'] ?? ["'self'"];
        $frameAncestors = $options['frame_ancestors'] ?? ["'self'"];
        $includeNonce = !empty($options['include_nonce']);

        if ($includeNonce) {
            $scriptSources[] = "'nonce-" . self::scriptNonce() . "'";
        }

        $directives = [
            "default-src 'self'",
            'base-uri ' . self::joinSources(["'self'"]),
            'form-action ' . self::joinSources(["'self'"]),
            "object-src 'none'",
            'frame-ancestors ' . self::joinSources($frameAncestors),
            'script-src ' . self::joinSources($scriptSources),
            'style-src ' . self::joinSources($styleSources),
            'img-src ' . self::joinSources($imgSources),
            'font-src ' . self::joinSources($fontSources),
            'connect-src ' . self::joinSources($connectSources),
            'frame-src ' . self::joinSources($frameSources),
        ];

        header('Content-Security-Policy: ' . implode('; ', $directives));
    }

    public static function applyJson(int $maxAgeSeconds = 0, bool $publicCache = false): void
    {
        self::applyCommon();
        header('Content-Type: application/json; charset=utf-8');

        if ($maxAgeSeconds > 0) {
            $scope = $publicCache ? 'public' : 'private';
            $staleWindow = min(30, $maxAgeSeconds);
            header("Cache-Control: {$scope}, max-age={$maxAgeSeconds}, stale-while-revalidate={$staleWindow}");
            return;
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    public static function applyDownload(): void
    {
        self::applyCommon();
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    private static function applyCommon(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }

    private static function joinSources(array $sources): string
    {
        $sources = array_values(array_unique(array_filter(array_map(
            static fn ($source) => trim((string) $source),
            $sources
        ))));

        return $sources !== [] ? implode(' ', $sources) : "'self'";
    }
}
