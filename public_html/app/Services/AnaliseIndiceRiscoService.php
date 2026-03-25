<?php

class AnaliseIndiceRiscoService
{
    /* ==========================================
       PESOS DE SEVERIDADE (PADRÃO OFICIAL)
    ========================================== */
    private static function pesoSeveridade(): string
    {
        return "
            CASE a.nivel_gravidade
                WHEN 'BAIXO' THEN 1
                WHEN 'MODERADO' THEN 2
                WHEN 'ALTO' THEN 3
                WHEN 'EXTREMO' THEN 5
                WHEN 'MUITO ALTO' THEN 4
                ELSE 1
            END
        ";
    }

    /* ==========================================
       IRP — Índice Regional de Pressão
       IRP = Σ (alertas × peso × municípios_afetados)
    ========================================== */
    public static function rankingIRP(PDO $db, array $filtro = []): array
    {
        $params = [];
    
        $sql = "
            SELECT 
                mr.regiao_integracao,
    
                SUM(
                    " . self::pesoSeveridade() . " *
    
                    (
                        SELECT COUNT(DISTINCT am2.municipio_codigo)
                        FROM alerta_municipios am2
                        JOIN municipios_regioes_pa mr2
                          ON mr2.cod_ibge = am2.municipio_codigo
                        WHERE am2.alerta_id = a.id
        ";
    
        if (!empty($filtro['regiao'])) {
            $sql .= " AND mr2.regiao_integracao = :regiao_sub ";
            $params[':regiao_sub'] = $filtro['regiao'];
        }

        if (!empty($filtro['municipio'])) {
            $sql .= " AND mr2.municipio = :municipio_sub ";
            $params[':municipio_sub'] = $filtro['municipio'];
        }
    
        $sql .= ")
                ) AS irp
    
            FROM alertas a
            JOIN alerta_municipios am ON am.alerta_id = a.id
            JOIN municipios_regioes_pa mr ON mr.cod_ibge = am.municipio_codigo
    
            WHERE a.inicio_alerta IS NOT NULL
              AND a.status IN ('ATIVO','ENCERRADO')
        ";
    
        if (!empty($filtro['ano'])) {
            $sql .= " AND YEAR(a.inicio_alerta) = :ano";
            $params[':ano'] = $filtro['ano'];
        }
    
        if (!empty($filtro['mes'])) {
            $sql .= " AND MONTH(a.inicio_alerta) = :mes";
            $params[':mes'] = $filtro['mes'];
        }
    
        if (!empty($filtro['regiao'])) {
            $sql .= " AND mr.regiao_integracao = :regiao ";
            $params[':regiao'] = $filtro['regiao'];
        }

        if (!empty($filtro['municipio'])) {
            $sql .= " AND mr.municipio = :municipio ";
            $params[':municipio'] = $filtro['municipio'];
        }
    
        $sql .= "
            GROUP BY mr.regiao_integracao
            ORDER BY irp DESC
        ";
    
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /* ==========================================
       IPT — Índice de Pressão Territorial
       IPT = Σ (alertas × peso × duração)
    ========================================== */
    public static function rankingIPT(PDO $db, array $filtro = []): array
    {
        $params = [];

        $sql = "
            SELECT 
                mr.municipio,
                SUM(
                    " . self::pesoSeveridade() . " *
                    (
                        TIMESTAMPDIFF(
                            HOUR,
                            a.inicio_alerta,
                            a.fim_alerta
                        )
                    )
                ) AS ipt
            FROM alertas a
            JOIN alerta_municipios am ON am.alerta_id = a.id
            JOIN municipios_regioes_pa mr ON mr.cod_ibge = am.municipio_codigo
            WHERE a.inicio_alerta IS NOT NULL
          AND a.fim_alerta IS NOT NULL
          AND a.fim_alerta > a.inicio_alerta
          AND a.status IN ('ATIVO','ENCERRADO')
        ";

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
            ORDER BY ipt DESC
            LIMIT 144
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
