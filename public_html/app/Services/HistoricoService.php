<?php
require_once __DIR__ . '/../Core/Database.php';

class HistoricoService
{
    private const PAGE_VIEW_CODES = [
        'ACESSAR_PAINEL',
        'VISUALIZAR_ALERTAS',
        'ACESSAR_CADASTRO_ALERTA',
        'ACESSAR_EDICAO_ALERTA',
        'VISUALIZAR_ALERTA',
        'ACESSAR_IMPORTACAO_INMET',
        'VISUALIZAR_USUARIOS',
        'ACESSAR_CADASTRO_USUARIO',
        'ACESSAR_EDICAO_USUARIO',
        'ACESSAR_ALTERACAO_SENHA_USUARIO',
        'VISUALIZAR_HISTORICO',
        'VISUALIZAR_ANALISES',
        'VISUALIZAR_ANALISE_TEMPORAL',
        'VISUALIZAR_ANALISE_SEVERIDADE',
        'VISUALIZAR_ANALISE_TIPOLOGIA',
        'VISUALIZAR_INDICES_RISCO',
        'VISUALIZAR_MAPA_MULTIRRISCOS',
    ];

    private const LABELS = [
        'ACESSAR_PAINEL' => 'Acessar painel',
        'VISUALIZAR_ALERTAS' => 'Visualizar alertas',
        'ACESSAR_CADASTRO_ALERTA' => 'Acessar cadastro de alerta',
        'ACESSAR_EDICAO_ALERTA' => 'Acessar edicao de alerta',
        'VISUALIZAR_ALERTA' => 'Visualizar detalhe do alerta',
        'ACESSAR_IMPORTACAO_INMET' => 'Acessar importacao INMET',
        'PREPARAR_IMPORTACAO_INMET' => 'Preparar importacao INMET',
        'CADASTRAR_ALERTA' => 'Cadastrar alerta',
        'EDIT_ALERTA' => 'Editar alerta',
        'ENC_ALERTA' => 'Encerrar alerta',
        'CAN_ALERTA' => 'Cancelar alerta',
        'IMPORTAR_INMET' => 'Importar alerta do INMET',
        'ENVIO_ALERTA' => 'Enviar alerta',
        'GERAR_MAPA_ALERTA' => 'Gerar imagem do mapa do alerta',
        'BAIXAR_PDF' => 'Baixar PDF do alerta',
        'BAIXAR_KML' => 'Baixar KML do alerta',
        'BAIXAR_CSV' => 'Baixar CSV',
        'VISUALIZAR_USUARIOS' => 'Visualizar usuarios',
        'ACESSAR_CADASTRO_USUARIO' => 'Acessar cadastro de usuario',
        'ACESSAR_EDICAO_USUARIO' => 'Acessar edicao de usuario',
        'ACESSAR_ALTERACAO_SENHA_USUARIO' => 'Acessar alteracao de senha',
        'CADASTRAR_USUARIO' => 'Cadastrar usuario',
        'ATUALIZAR_USUARIO' => 'Atualizar usuario',
        'ALTERAR_STATUS_USUARIO' => 'Alterar status de usuario',
        'ALTERAR_SENHA_USUARIO' => 'Alterar senha de usuario',
        'VISUALIZAR_HISTORICO' => 'Visualizar historico do usuario',
        'BAIXAR_RELATORIO_HISTORICO' => 'Baixar relatorio do historico',
        'VISUALIZAR_ANALISES' => 'Visualizar central de analises',
        'VISUALIZAR_ANALISE_TEMPORAL' => 'Visualizar analise temporal',
        'VISUALIZAR_ANALISE_SEVERIDADE' => 'Visualizar analise de severidade',
        'VISUALIZAR_ANALISE_TIPOLOGIA' => 'Visualizar analise de tipologia',
        'VISUALIZAR_INDICES_RISCO' => 'Visualizar indices de risco',
        'GERAR_RELATORIO_ANALITICO' => 'Gerar relatorio analitico',
        'BAIXAR_RELATORIO_ANALITICO' => 'Baixar relatorio analitico',
        'VISUALIZAR_MAPA_MULTIRRISCOS' => 'Visualizar mapa multirriscos',
        'LOGIN_SISTEMA' => 'Entrar no sistema',
        'LOGOUT_SISTEMA' => 'Sair do sistema',
    ];

    public static function registrar(
        int $usuarioId,
        string $usuarioNome,
        string $acaoCodigo,
        string $acaoDescricao,
        ?string $referencia = null,
        ?string $hashAcao = null
    ): void {
        static $hashesRegistrados = [];

        try {
            $db = Database::getConnection();

            if ($hashAcao === null) {
                $hashAcao = hash(
                    'sha256',
                    $usuarioId .
                    $acaoCodigo .
                    $acaoDescricao .
                    ($referencia ?? '') .
                    ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))
                );
            }

            if (isset($hashesRegistrados[$hashAcao])) {
                return;
            }

            $hashesRegistrados[$hashAcao] = true;

            $stmt = $db->prepare("
                INSERT INTO historico_usuarios
                (
                    usuario_id,
                    usuario_nome,
                    acao_codigo,
                    acao_descricao,
                    referencia,
                    hash_acao,
                    ip_usuario,
                    user_agent
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $usuarioId,
                $usuarioNome,
                $acaoCodigo,
                $acaoDescricao,
                $referencia,
                $hashAcao,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (Throwable $e) {
            error_log('[HistoricoService] ' . $e->getMessage());
        }
    }

    public static function catalogoAcoes(PDO $db): array
    {
        $exclusao = self::montarClausulaExclusaoAcoesPagina('acao_codigo', 'catalogo_oculto_');

        $sql = "
            SELECT
                acao_codigo,
                MAX(acao_descricao) AS acao_descricao
            FROM historico_usuarios
        ";

        if ($exclusao['sql'] !== '') {
            $sql .= "\nWHERE {$exclusao['sql']}";
        }

        $sql .= "
            GROUP BY acao_codigo
            ORDER BY acao_codigo
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($exclusao['params']);

        $acoes = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $codigo = trim((string) ($item['acao_codigo'] ?? ''));

            if ($codigo === '') {
                continue;
            }

            $acoes[] = [
                'codigo' => $codigo,
                'label' => self::labelAcao($codigo, (string) ($item['acao_descricao'] ?? '')),
            ];
        }

        return $acoes;
    }

    public static function labelAcao(string $acaoCodigo, ?string $fallbackDescricao = null): string
    {
        $acaoCodigo = trim($acaoCodigo);

        if ($acaoCodigo !== '' && isset(self::LABELS[$acaoCodigo])) {
            return self::LABELS[$acaoCodigo];
        }

        $fallbackDescricao = trim((string) $fallbackDescricao);
        if ($fallbackDescricao !== '') {
            return $fallbackDescricao;
        }

        if ($acaoCodigo === '') {
            return 'Acao nao informada';
        }

        $texto = str_replace('_', ' ', mb_strtolower($acaoCodigo));
        return mb_convert_case($texto, MB_CASE_TITLE, 'UTF-8');
    }

    public static function montarClausulaExclusaoAcoesPagina(string $coluna = 'acao_codigo', string $prefixo = 'acao_oculta_'): array
    {
        if (self::PAGE_VIEW_CODES === []) {
            return ['sql' => '', 'params' => []];
        }

        $placeholders = [];
        $params = [];

        foreach (array_values(self::PAGE_VIEW_CODES) as $indice => $codigo) {
            $placeholder = ':' . $prefixo . $indice;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $codigo;
        }

        return [
            'sql' => $coluna . ' NOT IN (' . implode(', ', $placeholders) . ')',
            'params' => $params,
        ];
    }
}
