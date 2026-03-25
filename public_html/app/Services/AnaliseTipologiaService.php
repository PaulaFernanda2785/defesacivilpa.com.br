<?php

class AnaliseTipologiaService
{
    /* =====================================================
       1. QUANTIDADE DE ALERTAS POR TIPO DE EVENTO
    ===================================================== */
    public static function quantidadePorEvento(PDO $db, array $filtro): array
    {
        $sql = "
            SELECT 
                a.tipo_evento,
                COUNT(DISTINCT a.id) AS total
            FROM alertas a
            JOIN alerta_municipios am
                ON am.alerta_id = a.id
            JOIN municipios_regioes_pa mr
                ON mr.cod_ibge = am.municipio_codigo
            WHERE a.inicio_alerta IS NOT NULL
            AND a.status IN ('ATIVO','ENCERRADO')
        ";

        $params = [];

        /* FILTRO TEMPORAL */
        if (!empty($filtro['ano'])) {
            $sql .= " AND YEAR(a.inicio_alerta) = :ano";
            $params[':ano'] = $filtro['ano'];
        }

        if (!empty($filtro['mes'])) {
            $sql .= " AND MONTH(a.inicio_alerta) = :mes";
            $params[':mes'] = $filtro['mes'];
        }

        /* FILTRO TERRITORIAL */
        if (!empty($filtro['regiao'])) {
            $sql .= " AND mr.regiao_integracao = :regiao";
            $params[':regiao'] = $filtro['regiao'];
        }

        if (!empty($filtro['municipio'])) {
            $sql .= " AND mr.municipio = :municipio";
            $params[':municipio'] = $filtro['municipio'];
        }

        $sql .= "
            GROUP BY a.tipo_evento
            ORDER BY total DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =====================================================
       4.1 — CORRELAÇÃO TIPOLOGIA × SEVERIDADE
    ===================================================== */
    public static function correlacaoEventoSeveridade(PDO $db, array $filtro): array
    {
        $sql = "
            SELECT
                a.tipo_evento,
                a.nivel_gravidade,
                COUNT(DISTINCT a.id) AS total
            FROM alertas a
            JOIN alerta_municipios am
                ON am.alerta_id = a.id
            JOIN municipios_regioes_pa mr
                ON mr.cod_ibge = am.municipio_codigo
            WHERE a.inicio_alerta IS NOT NULL
            AND a.status IN ('ATIVO','ENCERRADO')
        ";

        $params = [];

        if (!empty($filtro['ano'])) {
            $sql .= " AND YEAR(a.inicio_alerta) = :ano";
            $params[':ano'] = $filtro['ano'];
        }

        if (!empty($filtro['mes'])) {
            $sql .= " AND MONTH(a.inicio_alerta) = :mes";
            $params[':mes'] = $filtro['mes'];
        }

        if (!empty($filtro['regiao'])) {
            $sql .= " AND mr.regiao_integracao = :regiao";
            $params[':regiao'] = $filtro['regiao'];
        }

        if (!empty($filtro['municipio'])) {
            $sql .= " AND mr.municipio = :municipio";
            $params[':municipio'] = $filtro['municipio'];
        }

        $sql .= "
            GROUP BY a.tipo_evento, a.nivel_gravidade
            ORDER BY a.tipo_evento
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =====================================================
       4.2 — TIPOLOGIA POR REGIÃO DE INTEGRAÇÃO
    ===================================================== */
    public static function tipologiaPorRegiao(PDO $db, array $filtro): array
    {
        $sql = "
            SELECT
                mr.regiao_integracao,
                a.tipo_evento,
                COUNT(DISTINCT a.id) AS total
            FROM alertas a
            JOIN alerta_municipios am
                ON am.alerta_id = a.id
            JOIN municipios_regioes_pa mr
                ON mr.cod_ibge = am.municipio_codigo
            WHERE a.inicio_alerta IS NOT NULL
            AND a.status IN ('ATIVO','ENCERRADO')
        ";

        $params = [];

        if (!empty($filtro['ano'])) {
            $sql .= " AND YEAR(a.inicio_alerta) = :ano";
            $params[':ano'] = $filtro['ano'];
        }

        if (!empty($filtro['mes'])) {
            $sql .= " AND MONTH(a.inicio_alerta) = :mes";
            $params[':mes'] = $filtro['mes'];
        }

        if (!empty($filtro['regiao'])) {
            $sql .= " AND mr.regiao_integracao = :regiao";
            $params[':regiao'] = $filtro['regiao'];
        }

        $sql .= "
            GROUP BY mr.regiao_integracao, a.tipo_evento
            ORDER BY mr.regiao_integracao, total DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
   /* =====================================================
   4.3 — TIPOLOGIA POR MUNICÍPIO
    ===================================================== */
    public static function tipologiaPorMunicipio(PDO $db, array $filtro): array
    {
        $sql = "
            SELECT
                mr.municipio,
                a.tipo_evento,
                COUNT(DISTINCT a.id) AS total
            FROM alertas a
            JOIN alerta_municipios am
                ON am.alerta_id = a.id
            JOIN municipios_regioes_pa mr
                ON mr.cod_ibge = am.municipio_codigo
           WHERE a.inicio_alerta IS NOT NULL
           AND a.status IN ('ATIVO','ENCERRADO')
        ";
    
        $params = [];
    
        if (!empty($filtro['ano'])) {
            $sql .= " AND YEAR(a.inicio_alerta) = :ano";
            $params[':ano'] = $filtro['ano'];
        }
    
        if (!empty($filtro['mes'])) {
            $sql .= " AND MONTH(a.inicio_alerta) = :mes";
            $params[':mes'] = $filtro['mes'];
        }
    
        if (!empty($filtro['regiao'])) {
            $sql .= " AND mr.regiao_integracao = :regiao";
            $params[':regiao'] = $filtro['regiao'];
        }
    
        if (!empty($filtro['municipio'])) {
            $sql .= " AND mr.municipio = :municipio";
            $params[':municipio'] = $filtro['municipio'];
        }
    
        $sql .= "
            GROUP BY mr.municipio, a.tipo_evento
            ORDER BY mr.municipio, total DESC
        ";
    
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }



}
