<?php
require_once __DIR__ . '/Env.php';
require_once __DIR__ . '/../Helpers/TimeHelper.php';

class Database
{
    public static function getConnection()
    {
        TimeHelper::bootstrap();

        $publicRoot = dirname(__DIR__, 2);
        $projectRoot = dirname($publicRoot);
        $configPath = $publicRoot . '/config/database.php';

        if (!file_exists($configPath)) {
            error_log('[Database] Arquivo de configuracao nao encontrado em ' . $configPath);
            http_response_code(500);
            die('Erro interno ao iniciar a conexao com o banco.');
        }

        // Compatibilidade de layout:
        // - legado: app dentro de public_html (prioriza ../.env, fora da raiz publica);
        // - V2: app em multirriscos_app (prioriza multirriscos_app/.env).
        $publicBaseName = strtolower(basename(str_replace('\\', '/', $publicRoot)));
        $isLegacyPublicLayout = $publicBaseName === 'public_html';

        $envCandidates = $isLegacyPublicLayout
            ? [$projectRoot . '/.env', $publicRoot . '/.env']
            : [$publicRoot . '/.env', $projectRoot . '/.env'];

        Env::loadFromCandidates($envCandidates);

        $config = require $configPath;
        $config = self::applyEnvOverrides($config);

        $configValidationError = self::validateConfiguration($config);
        if ($configValidationError !== null) {
            error_log('[Database] ' . $configValidationError . ' | config=' . self::sanitizedConfig($config));
            http_response_code(500);
            die('Configuracao do banco incompleta. Revise DB_HOST, DB_NAME, DB_USER e DB_PASS no arquivo .env.');
        }

        $charset = $config['charset'] ?? 'utf8mb4';
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$charset}";

        if (!empty($config['port'])) {
            $dsn .= ";port={$config['port']}";
        }

        try {
            $pdo = new PDO(
                $dsn,
                $config['user'],
                $config['pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 10,
                ]
            );

            $pdo->exec("SET time_zone = '+00:00'");

            return $pdo;
        } catch (PDOException $e) {
            error_log('[Database] ' . self::formatConnectionError($e->getMessage(), $config) . ' | config=' . self::sanitizedConfig($config));
            http_response_code(500);
            die(self::publicConnectionError($e->getMessage(), $config));
        }
    }

    private static function applyEnvOverrides(array $config): array
    {
        $map = [
            'DB_HOST' => 'host',
            'DB_PORT' => 'port',
            'DB_NAME' => 'dbname',
            'DB_USER' => 'user',
            'DB_PASS' => 'pass',
            'DB_CHARSET' => 'charset',
        ];

        foreach ($map as $envKey => $configKey) {
            $value = self::env($envKey);

            if ($value !== null) {
                $config[$configKey] = $value;
            }
        }

        return $config;
    }

    private static function env(string $key): ?string
    {
        $value = Env::get($key);

        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function validateConfiguration(array $config): ?string
    {
        foreach (['host', 'dbname', 'user'] as $requiredKey) {
            $value = trim((string) ($config[$requiredKey] ?? ''));

            if ($value === '') {
                return "configuracao ausente: '$requiredKey' nao foi preenchido.";
            }

            if (self::isPlaceholderValue($value)) {
                return "configuracao invalida: '$requiredKey' ainda esta com valor de exemplo ('$value').";
            }
        }

        return null;
    }

    private static function isPlaceholderValue(string $value): bool
    {
        $normalized = strtolower(trim($value));

        if ($normalized === '') {
            return true;
        }

        if (str_starts_with($normalized, 'seu_') || str_starts_with($normalized, 'sua_')) {
            return true;
        }

        return in_array($normalized, [
            'nome_do_banco',
            'usuario_do_banco',
            'senha_do_banco',
            'seu_banco',
            'seu_usuario',
            'sua_senha',
        ], true);
    }

    private static function publicConnectionError(string $message, array $config): string
    {
        $normalizedMessage = strtolower($message);

        if (str_contains($normalizedMessage, 'could not find driver')) {
            return 'Erro de ambiente: extensao pdo_mysql nao habilitada no servidor.';
        }

        if (str_contains($normalizedMessage, 'access denied')) {
            return 'Falha de autenticacao no banco. Revise DB_USER e DB_PASS do .env e permissoes do usuario no banco.';
        }

        if (str_contains($normalizedMessage, 'unknown database')) {
            return "Banco '{$config['dbname']}' nao encontrado. Importe o banco e confira DB_NAME.";
        }

        if (
            str_contains($normalizedMessage, 'connection refused') ||
            str_contains($normalizedMessage, 'no such file or directory') ||
            str_contains($normalizedMessage, 'php_network_getaddresses') ||
            str_contains($normalizedMessage, 'getaddrinfo') ||
            str_contains($normalizedMessage, '[2002]')
        ) {
            return 'Nao foi possivel conectar ao servidor MySQL. Revise DB_HOST/DB_PORT e confirme o acesso remoto liberado.';
        }

        if (str_contains($message, "Plugin 'mysql_native_password' is not loaded")) {
            return 'Usuario MySQL usa plugin nao suportado no servidor atual. Ajuste o plugin de autenticacao do usuario.';
        }

        return 'Erro interno ao conectar com o banco de dados. Revise DB_HOST, DB_PORT, DB_NAME, DB_USER e DB_PASS no .env.';
    }

    private static function sanitizedConfig(array $config): string
    {
        $safe = [
            'host' => (string) ($config['host'] ?? ''),
            'port' => (string) ($config['port'] ?? ''),
            'dbname' => (string) ($config['dbname'] ?? ''),
            'user' => (string) ($config['user'] ?? ''),
            'charset' => (string) ($config['charset'] ?? ''),
        ];

        $encoded = json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '{}' : $encoded;
    }

    private static function formatConnectionError(string $message, array $config): string
    {
        if (str_contains($message, "Plugin 'mysql_native_password' is not loaded")) {
            return "o usuario '{$config['user']}' usa mysql_native_password, que nao vem ativo no MySQL 8.4 do Wamp. Recrie o usuario com caching_sha2_password ou habilite o plugin legado no MySQL; se estiver rodando local, confirme tambem que o banco '{$config['dbname']}' foi importado.";
        }

        if (str_contains($message, 'Unknown database')) {
            return "o banco '{$config['dbname']}' nao existe em {$config['host']}. Importe o dump localmente ou ajuste DB_HOST/DB_NAME para o servidor correto.";
        }

        return $message;
    }
}
