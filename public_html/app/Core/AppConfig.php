<?php
require_once __DIR__ . '/Env.php';

class AppConfig
{
    private static ?array $config = null;

    public static function get(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $appRoot = dirname(__DIR__, 2);
        $projectRoot = dirname($appRoot);
        $appRootBaseName = strtolower(basename(str_replace('\\', '/', $appRoot)));
        $isLegacyPublicLayout = $appRootBaseName === 'public_html';

        // Compatibilidade de layout legado e V2 (fora da raiz publica).
        $envCandidates = $isLegacyPublicLayout
            ? [$projectRoot . '/.env', $appRoot . '/.env']
            : [$appRoot . '/.env', $projectRoot . '/.env'];

        Env::loadFromCandidates($envCandidates);

        $environment = strtolower(trim((string) Env::get('APP_ENV', 'local')));

        self::$config = [
            'name' => self::value('APP_NAME', 'Sistema Inteligente Multirriscos'),
            'version' => self::value('APP_VERSION', '1.1.0'),
            'organization' => self::value('APP_ORG_NAME', 'Defesa Civil do Estado do Pará'),
            'institution' => self::value('APP_INSTITUTION', 'Defesa Civil do Estado do Pará'),
            'department' => self::value('APP_DEPARTMENT', 'Coordenadoria Estadual de Protecao e Defesa Civil'),
            'support_email' => self::value('SUPPORT_EMAIL', 'suporte@defesacivil.pa.gov.br'),
            'environment' => $environment,
            'environment_label' => self::value('APP_ENV_LABEL', self::defaultEnvironmentLabel($environment)),
            'environment_class' => self::environmentClass($environment),
        ];

        return self::$config;
    }

    private static function value(string $key, string $default): string
    {
        $value = Env::get($key);

        if ($value === null) {
            return $default;
        }

        $value = trim((string) $value);

        return $value === '' ? $default : $value;
    }

    private static function environmentClass(string $environment): string
    {
        return match ($environment) {
            'production', 'producao', 'prod' => 'producao',
            'staging', 'stage', 'homolog', 'homologacao' => 'homologacao',
            default => 'local',
        };
    }

    private static function defaultEnvironmentLabel(string $environment): string
    {
        return match (self::environmentClass($environment)) {
            'producao' => 'Producao',
            'homologacao' => 'Homologacao',
            default => 'Local Wamp',
        };
    }
}
