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

        Env::loadFromCandidates([
            $projectRoot . '/.env',
            $publicRoot . '/.env',
        ]);

        $config = require $configPath;
        $config = self::applyEnvOverrides($config);

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
            error_log('[Database] ' . self::formatConnectionError($e->getMessage(), $config));
            http_response_code(500);
            die('Erro interno ao conectar com o banco de dados.');
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
