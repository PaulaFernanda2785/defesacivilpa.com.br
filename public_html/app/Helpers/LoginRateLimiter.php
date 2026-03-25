<?php

class LoginRateLimiter
{
    private const WINDOW_SECONDS = 600;
    private const MAX_ATTEMPTS = 8;
    private const BLOCK_SECONDS = 900;

    public static function keyForRequest(): string
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

        return hash('sha256', $ip);
    }

    public static function isBlocked(string $key): bool
    {
        $state = self::read($key);
        $blockedUntil = (int) ($state['blocked_until'] ?? 0);

        if ($blockedUntil <= time()) {
            if ($blockedUntil > 0 || !empty($state['attempts'])) {
                self::write($key, [
                    'blocked_until' => 0,
                    'attempts' => self::freshAttempts((array) ($state['attempts'] ?? [])),
                ]);
            }

            return false;
        }

        return true;
    }

    public static function retryAfterSeconds(string $key): int
    {
        $state = self::read($key);
        $blockedUntil = (int) ($state['blocked_until'] ?? 0);

        return max(0, $blockedUntil - time());
    }

    public static function registerFailure(string $key): void
    {
        $state = self::read($key);
        $attempts = self::freshAttempts((array) ($state['attempts'] ?? []));
        $attempts[] = time();
        $blockedUntil = 0;

        if (count($attempts) >= self::MAX_ATTEMPTS) {
            $blockedUntil = time() + self::BLOCK_SECONDS;
        }

        self::write($key, [
            'blocked_until' => $blockedUntil,
            'attempts' => $attempts,
        ]);
    }

    public static function clear(string $key): void
    {
        $path = self::pathForKey($key);

        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function read(string $key): array
    {
        $path = self::pathForKey($key);

        if (!is_file($path)) {
            return ['blocked_until' => 0, 'attempts' => []];
        }

        $contents = @file_get_contents($path);
        $decoded = is_string($contents) ? json_decode($contents, true) : null;

        if (!is_array($decoded)) {
            return ['blocked_until' => 0, 'attempts' => []];
        }

        return [
            'blocked_until' => (int) ($decoded['blocked_until'] ?? 0),
            'attempts' => array_values(array_filter(
                array_map('intval', (array) ($decoded['attempts'] ?? [])),
                static fn (int $attempt): bool => $attempt > 0
            )),
        ];
    }

    private static function write(string $key, array $state): void
    {
        $path = self::pathForKey($key);
        @file_put_contents($path, json_encode($state, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    private static function freshAttempts(array $attempts): array
    {
        $threshold = time() - self::WINDOW_SECONDS;

        return array_values(array_filter(
            array_map('intval', $attempts),
            static fn (int $attempt): bool => $attempt >= $threshold
        ));
    }

    private static function pathForKey(string $key): string
    {
        $base = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);

        return $base . DIRECTORY_SEPARATOR . 'sim_multirriscos_login_' . preg_replace('/[^a-f0-9]/i', '', $key) . '.json';
    }
}
