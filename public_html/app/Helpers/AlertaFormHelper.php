<?php

class AlertaFormHelper
{
    public const RISCOS_MAX = 5000;
    public const RECOMENDACOES_MAX = 5000;

    public static function eventos(): array
    {
        return [
            'Chuvas Intensas',
            'Tempestade',
            'Vento Costeiros',
            'Movimento de Massa',
            'Inundação',
            'Alagamento',
            'Baixa Umidade',
            'Ventania',
            'Acumulado de Chuva',
            'Incêndio Florestal',
            'Qualidade do Ar',
            'Ondas de Calor',
            'Maré Alta',
        ];
    }

    public static function niveis(): array
    {
        return ['BAIXO', 'MODERADO', 'ALTO', 'MUITO ALTO', 'EXTREMO'];
    }

    public static function fontes(): array
    {
        return [
            'INMET',
            'CEMADEN',
            'MARINHA',
            'SGB',
            'SEMAS',
            'CENSIPAM',
            'SIMD',
            'ANA',
            'BDQUEIMADAS',
        ];
    }

    public static function normalizeExistingOption(?string $value, array $allowed): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach ($allowed as $option) {
            if (self::fold($option) === self::fold($value)) {
                return $option;
            }
        }

        return $value;
    }

    public static function validateTipoEvento(string $value): string
    {
        return self::validateChoice($value, self::eventos(), 'Tipo de evento invalido.');
    }

    public static function validateNivelGravidade(string $value): string
    {
        return self::validateChoice($value, self::niveis(), 'Nivel de gravidade invalido.');
    }

    public static function validateFonte(string $value): string
    {
        return self::validateChoice(
            $value,
            array_merge(self::fontes(), ['MANUAL']),
            'Fonte do alerta invalida.'
        );
    }

    public static function validateTexto(string $value, string $fieldLabel, int $maxLength): string
    {
        $value = trim(str_replace(["\r\n", "\r"], "\n", (string) $value));
        $value = str_replace("\0", '', $value);

        if ($value === '') {
            throw new RuntimeException($fieldLabel . ' e obrigatorio.');
        }

        $length = function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);

        if ($length > $maxLength) {
            throw new RuntimeException($fieldLabel . ' excede o limite permitido.');
        }

        return $value;
    }

    public static function validateDate(string $value, string $fieldLabel): string
    {
        $value = trim($value);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new RuntimeException($fieldLabel . ' invalida.');
        }

        return $date->format('Y-m-d');
    }

    public static function validateDateTimeLocal(?string $value, string $fieldLabel, bool $required = true): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            if ($required) {
                throw new RuntimeException($fieldLabel . ' e obrigatorio.');
            }

            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);

        if (!$date || $date->format('Y-m-d\TH:i') !== $value) {
            throw new RuntimeException($fieldLabel . ' invalida.');
        }

        return $date->format('Y-m-d H:i:s');
    }

    public static function validatePeriodo(?string $inicio, ?string $fim): void
    {
        if ($inicio !== null && $fim !== null && strtotime($inicio) > strtotime($fim)) {
            throw new RuntimeException('A vigencia inicial nao pode ser maior que a vigencia final.');
        }
    }

    public static function normalizeAreaOrigem(?string $value): string
    {
        return strtoupper(trim((string) $value)) === 'KML' ? 'KML' : 'DESENHO';
    }

    public static function normalizeAreaGeojson(string $rawGeojson): array
    {
        $decoded = json_decode($rawGeojson, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Area geografica invalida.');
        }

        $features = [];
        self::collectAreaFeatures($decoded, $features);

        if ($features === []) {
            throw new RuntimeException('Area geografica invalida. Desenhe no mapa ou carregue um KML com geometria de area.');
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }

    public static function encodeAreaGeojson(array $geojson): string
    {
        return (string) json_encode(
            $geojson,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }

    private static function validateChoice(string $value, array $allowed, string $message): string
    {
        $value = trim($value);

        foreach ($allowed as $option) {
            if (self::fold($option) === self::fold($value)) {
                return $option;
            }
        }

        throw new RuntimeException($message);
    }

    private static function collectAreaFeatures(array $node, array &$features): void
    {
        $type = $node['type'] ?? null;

        if ($type === 'FeatureCollection') {
            foreach ($node['features'] ?? [] as $feature) {
                if (is_array($feature)) {
                    self::collectAreaFeatures($feature, $features);
                }
            }

            return;
        }

        if ($type === 'Feature') {
            $geometry = $node['geometry'] ?? null;

            if (is_array($geometry)) {
                self::collectAreaFeatures($geometry, $features);
            }

            return;
        }

        if ($type === 'GeometryCollection') {
            foreach ($node['geometries'] ?? [] as $geometry) {
                if (is_array($geometry)) {
                    self::collectAreaFeatures($geometry, $features);
                }
            }

            return;
        }

        if (!in_array($type, ['Polygon', 'MultiPolygon'], true)) {
            return;
        }

        $features[] = [
            'type' => 'Feature',
            'properties' => (object) [],
            'geometry' => [
                'type' => $type,
                'coordinates' => $node['coordinates'] ?? [],
            ],
        ];
    }

    private static function fold(string $value): string
    {
        $value = trim($value);
        $normalized = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        if (is_string($normalized) && $normalized !== '') {
            $value = $normalized;
        }

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }

        return strtolower($value);
    }
}
