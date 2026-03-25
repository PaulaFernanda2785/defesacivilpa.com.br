<?php

class TimeHelper
{
    public const APP_TIMEZONE = 'America/Belem';
    public const UTC_TIMEZONE = 'UTC';

    private static bool $bootstrapped = false;
    private static ?DateTimeZone $appTimezone = null;
    private static ?DateTimeZone $utcTimezone = null;

    public static function bootstrap(): void
    {
        if (self::$bootstrapped) {
            return;
        }

        date_default_timezone_set(self::APP_TIMEZONE);
        self::$bootstrapped = true;
    }

    public static function appTimezone(): DateTimeZone
    {
        self::bootstrap();

        if (self::$appTimezone === null) {
            self::$appTimezone = new DateTimeZone(self::APP_TIMEZONE);
        }

        return self::$appTimezone;
    }

    public static function utcTimezone(): DateTimeZone
    {
        if (self::$utcTimezone === null) {
            self::$utcTimezone = new DateTimeZone(self::UTC_TIMEZONE);
        }

        return self::$utcTimezone;
    }

    public static function now(string $format = 'Y-m-d H:i:s'): string
    {
        return (new DateTimeImmutable('now', self::appTimezone()))->format($format);
    }

    public static function currentYear(): int
    {
        return (int) self::now('Y');
    }

    public static function currentMonth(): int
    {
        return (int) self::now('n');
    }

    public static function formatDate(?string $value, string $fallback = '-'): string
    {
        return self::formatLocal($value, 'd/m/Y', $fallback);
    }

    public static function formatDateTime(?string $value, string $fallback = '-'): string
    {
        return self::formatLocal($value, 'd/m/Y H:i', $fallback);
    }

    public static function formatUtcDate(?string $value, string $fallback = '-'): string
    {
        return self::formatUtc($value, 'd/m/Y', $fallback);
    }

    public static function formatUtcDateTime(?string $value, string $fallback = '-'): string
    {
        return self::formatUtc($value, 'd/m/Y H:i', $fallback);
    }

    public static function formatLocal(?string $value, string $format, string $fallback = '-'): string
    {
        $date = self::parseLocal($value);

        return $date ? $date->format($format) : $fallback;
    }

    public static function formatUtc(?string $value, string $format, string $fallback = '-'): string
    {
        $date = self::parseUtc($value);

        return $date ? $date->setTimezone(self::appTimezone())->format($format) : $fallback;
    }

    public static function toHtmlDateTimeLocal(?string $value): string
    {
        $date = self::parseLocal($value);

        return $date ? $date->format('Y-m-d\TH:i') : '';
    }

    public static function isPastLocal(?string $value): bool
    {
        $date = self::parseLocal($value);

        if ($date === null) {
            return false;
        }

        return $date < new DateTimeImmutable('now', self::appTimezone());
    }

    public static function localDateStartToUtc(string $date): ?string
    {
        $date = trim($date);

        if ($date === '') {
            return null;
        }

        $local = DateTimeImmutable::createFromFormat('!Y-m-d', $date, self::appTimezone());

        if (!$local || $local->format('Y-m-d') !== $date) {
            return null;
        }

        return $local->setTimezone(self::utcTimezone())->format('Y-m-d H:i:s');
    }

    public static function localDateEndToUtc(string $date): ?string
    {
        $date = trim($date);

        if ($date === '') {
            return null;
        }

        $local = DateTimeImmutable::createFromFormat('!Y-m-d', $date, self::appTimezone());

        if (!$local || $local->format('Y-m-d') !== $date) {
            return null;
        }

        return $local
            ->setTime(23, 59, 59)
            ->setTimezone(self::utcTimezone())
            ->format('Y-m-d H:i:s');
    }

    private static function parseLocal(?string $value): ?DateTimeImmutable
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value, self::appTimezone());
        } catch (Exception) {
            return null;
        }
    }

    private static function parseUtc(?string $value): ?DateTimeImmutable
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value, self::utcTimezone());
        } catch (Exception) {
            return null;
        }
    }
}
