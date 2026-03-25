<?php

class AnaliseSeveridadeService
{
    /* =====================================================
       FILTROS BASE (PADRÃO ÚNICO)
       Referência temporal: inicio_alerta
    ===================================================== */
    private static function aplicarFiltros(array $filtro, array &$params): string
    {
        $where = "WHERE a.inicio_alerta IS NOT NULL
            AND a.status IN ('ATIVO','ENCERRADO')
        ";


        if (!empty($filtro['ano'])) {
            $where .= " AND YEAR(a.inicio_alerta) = :ano ";
            $params[':ano'] = $filtro['ano'];
        }

        if (!empty($filtro['mes'])) {
            $where .= " AND MONTH(a.inicio_alerta) = :mes ";
            $params[':mes'] = $filtro['mes'];
        }

        if (!empty($filtro['regiao'])) {
            $where .= "
                AND EXISTS (
                    SELECT 1
                    FROM alerta_municipios am
                    JOIN municipios_regioes_pa mr
                        ON mr.cod_ibge = am.municipio_codigo
                    WHERE am.alerta_id = a.id
                      AND mr.regiao_integracao = :regiao
                )
            ";
            $params[':regiao'] = $filtro['regiao'];
        }

        if (!empty($filtro['municipio'])) {
            $where .= "
                AND EXISTS (
                    SELECT 1
                    FROM alerta_municipios am
                    JOIN municipios_regioes_pa mr
                        ON mr.cod_ibge = am.municipio_codigo
                    WHERE am.alerta_id = a.id
                      AND mr.municipio = :municipio
                )
            ";
            $params[':municipio'] = $filtro['municipio'];
        }

        return $where;
    }

    /* =====================================================
       3.1 — PROPORÇÃO DE ALERTAS POR GRAU DE SEVERIDADE
    ===================================================== */
    public static function proporcaoPorSeveridade(PDO $db, array $filtro): array
    {
        $params = [];
        $where  = self::aplicarFiltros($filtro, $params);

        $sql = "
            SELECT 
                COALESCE(NULLIF(TRIM(a.nivel_gravidade), ''), 'NAO INFORMADO') AS nivel_gravidade,
                COUNT(DISTINCT a.id) AS total
            FROM alertas a
            $where
            GROUP BY nivel_gravidade
            ORDER BY total DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =====================================================
       3.2 — DURAÇÃO MÉDIA POR TIPO DE EVENTO (HORAS)
    ===================================================== */
    public static function duracaoMediaPorEvento(PDO $db, array $filtro): array
    {
        $params = [];
        $where  = self::aplicarFiltros($filtro, $params);

        $sql = "
            SELECT 
                COALESCE(NULLIF(TRIM(a.tipo_evento), ''), 'NAO INFORMADO') AS tipo_evento,
                ROUND(
                    AVG(
                        TIMESTAMPDIFF(
                            MINUTE,
                            a.inicio_alerta,
                            a.fim_alerta
                        )
                    ) / 60,
                    2
                ) AS duracao_media_horas
            FROM alertas a
            $where
              AND a.fim_alerta IS NOT NULL
              AND a.fim_alerta > a.inicio_alerta
            GROUP BY tipo_evento
            ORDER BY duracao_media_horas DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =====================================================
       3.3 — DISTRIBUIÇÃO FINAL DE SEVERIDADE
    ===================================================== */
    public static function distribuicaoFinal(PDO $db, array $filtro): array
    {
        $params = [];
        $where  = self::aplicarFiltros($filtro, $params);

        $sql = "
            SELECT 
                COALESCE(NULLIF(TRIM(a.nivel_gravidade), ''), 'NAO INFORMADO') AS nivel_gravidade,
                COUNT(DISTINCT a.id) AS total
            FROM alertas a
            $where
            GROUP BY nivel_gravidade
            ORDER BY total DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =====================================================
       3.4 — QUANTIDADE DE ALERTAS POR TIPO DE EVENTO
    ===================================================== */
    public static function quantidadePorEvento(PDO $db, array $filtro): array
    {
        $params = [];
        $where  = self::aplicarFiltros($filtro, $params);

        $sql = "
            SELECT 
                COALESCE(NULLIF(TRIM(a.tipo_evento), ''), 'NAO INFORMADO') AS tipo_evento,
                COUNT(DISTINCT a.id) AS total
            FROM alertas a
            $where
            GROUP BY tipo_evento
            ORDER BY total DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =====================================================
       APOIO A FILTROS (SELECTS)
    ===================================================== */
    public static function regioes(PDO $db): array
    {
        $stmt = $db->query("
            SELECT DISTINCT regiao_integracao
            FROM municipios_regioes_pa
            WHERE regiao_integracao <> ''
            ORDER BY regiao_integracao
        ");

        return array_column($stmt->fetchAll(), 'regiao_integracao');
    }

    public static function municipios(PDO $db): array
    {
        $stmt = $db->query("
            SELECT DISTINCT municipio
            FROM municipios_regioes_pa
            WHERE municipio <> ''
            ORDER BY municipio
        ");

        return array_column($stmt->fetchAll(), 'municipio');
    }
}
