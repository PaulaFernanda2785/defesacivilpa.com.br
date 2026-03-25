<?php

class AnaliseIndicePressaoService
{
    public static function calcular(PDO $db, array $filtro, int $limite = 10): array
    {
        $sql = "
            SELECT 
                mr.municipio,
                SUM(
                    CASE a.nivel_gravidade
                        WHEN 'BAIXO' THEN 1
                        WHEN 'MODERADO' THEN 2
                        WHEN 'ALTO' THEN 3
                        WHEN 'EXTREMO' THEN 5
                        WHEN 'MUITO ALTO' THEN 4
                        ELSE 1
                    END
                ) AS indice_pressao
            FROM alerta_municipios am
            JOIN alertas a ON a.id = am.alerta_id
            JOIN municipios_regioes_pa mr
                ON mr.cod_ibge = am.municipio_codigo
            WHERE 1=1
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
            GROUP BY mr.municipio
            ORDER BY indice_pressao DESC
            LIMIT {$limite}
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
    
    public static function calcularPorRegiao(PDO $db, array $filtro, int $limite = 10): array
    {
        $sql = "
            SELECT 
                mr.regiao_integracao,
                SUM(
                    CASE a.nivel_gravidade
                        WHEN 'BAIXO' THEN 1
                        WHEN 'MODERADO' THEN 2
                        WHEN 'ALTO' THEN 3
                        WHEN 'EXTREMO' THEN 5
                        WHEN 'MUITO ALTO' THEN 4
                        ELSE 1
                    END
                ) AS indice_pressao
            FROM alerta_municipios am
            JOIN alertas a ON a.id = am.alerta_id
            JOIN municipios_regioes_pa mr
                ON mr.cod_ibge = am.municipio_codigo
            WHERE 1=1
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
            GROUP BY mr.regiao_integracao
            ORDER BY indice_pressao DESC
            LIMIT {$limite}
        ";
    
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    
        return $stmt->fetchAll();
    }
    
    public static function heatmapMensalPorRegiao(PDO $db, array $filtro): array
    {
        $sql = "
            SELECT
                mr.regiao_integracao,
                MONTH(a.inicio_alerta) AS mes,
                SUM(
                    CASE a.nivel_gravidade
                        WHEN 'BAIXO' THEN 1
                        WHEN 'MODERADO' THEN 2
                        WHEN 'ALTO' THEN 3
                        WHEN 'EXTREMO' THEN 5
                        WHEN 'MUITO ALTO' THEN 4
                        ELSE 1
                    END
                ) AS indice_pressao
            FROM alerta_municipios am
            JOIN alertas a ON a.id = am.alerta_id
            JOIN municipios_regioes_pa mr
                ON mr.cod_ibge = am.municipio_codigo
            WHERE a.inicio_alerta IS NOT NULL
        ";
    
        $params = [];
    
        if (!empty($filtro['ano'])) {
            $sql .= " AND YEAR(a.inicio_alerta) = :ano";
            $params[':ano'] = $filtro['ano'];
        }
    
        if (!empty($filtro['regiao'])) {
            $sql .= " AND mr.regiao_integracao = :regiao";
            $params[':regiao'] = $filtro['regiao'];
        }
    
        $sql .= "
            GROUP BY mr.regiao_integracao, mes
            ORDER BY mr.regiao_integracao, mes
        ";
    
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    
        return $stmt->fetchAll();
    }
    


    public static function evolucaoAnualIRP(PDO $db, array $filtro): array
    {
        $sql = "
            SELECT
                YEAR(a.inicio_alerta) AS ano,
                SUM(
                    CASE a.nivel_gravidade
                        WHEN 'BAIXO' THEN 1
                        WHEN 'MODERADO' THEN 2
                        WHEN 'ALTO' THEN 3
                        WHEN 'EXTREMO' THEN 5
                        WHEN 'MUITO ALTO' THEN 4
                        ELSE 1
                    END
                    *
                    (
                        SELECT COUNT(DISTINCT am2.municipio_codigo)
                        FROM alerta_municipios am2
                        WHERE am2.alerta_id = a.id
                    )
                ) AS irp
            FROM alertas a
            WHERE a.inicio_alerta IS NOT NULL
        ";
    
        $params = [];
    
        if (!empty($filtro['regiao'])) {
            $sql .= "
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
            $sql .= "
                AND EXISTS (
                    SELECT 1
                    FROM alerta_municipios am
                    WHERE am.alerta_id = a.id
                      AND am.municipio_codigo = (
                          SELECT cod_ibge
                          FROM municipios_regioes_pa
                          WHERE municipio = :municipio
                          LIMIT 1
                      )
                )
            ";
            $params[':municipio'] = $filtro['municipio'];
        }
    
        $sql .= "
            GROUP BY ano
            ORDER BY ano
        ";
    
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    
        return $stmt->fetchAll();
    }  




}
