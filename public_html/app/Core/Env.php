<?php

class Env
{
    private static array $loadedPaths = [];

    public static function load(string $path): bool
    {
        $realPath = realpath($path);

        if ($realPath === false || !file_exists($realPath)) {
            return false;
        }

        if (isset(self::$loadedPaths[$realPath])) {
            return true;
        }

        $lines = file($realPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return false;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);

            $key = trim($key);
            $value = self::stripWrappingQuotes(trim($value));

            if ($key === '') {
                continue;
            }

            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
            }

            if (getenv($key) === false) {
                putenv("$key=$value");
            }
        }

        self::$loadedPaths[$realPath] = true;

        return true;
    }

    public static function loadFromCandidates(array $paths): ?string
    {
        foreach ($paths as $path) {
            if (self::load($path)) {
                return realpath($path) ?: $path;
            }
        }

        return null;
    }

    public static function get(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        return $value;
    }

    private static function stripWrappingQuotes(string $value): string
    {
        $length = strlen($value);

        if ($length >= 2) {
            $first = $value[0];
            $last = $value[$length - 1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, -1);
            }
        }

        return $value;
    }
}
