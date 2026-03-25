<?php

class AnaliseAlertasEmitidosService
{
   public static function porTipoEvento(PDO $db, array $filtro): array
    {
        $sql = "
            SELECT 
                a.tipo_evento,
                COUNT(DISTINCT a.id) AS total
            FROM alertas a
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
    
        if (!empty($filtro['regiao']) || !empty($filtro['municipio'])) {
    
            $sql .= "
                AND EXISTS (
                    SELECT 1
                    FROM alerta_municipios am
                    JOIN municipios_regioes_pa mr
                      ON mr.cod_ibge = am.municipio_codigo
                    WHERE am.alerta_id = a.id
            ";
    
            if (!empty($filtro['regiao'])) {
                $sql .= " AND mr.regiao_integracao = :regiao";
                $params[':regiao'] = $filtro['regiao'];
            }
    
            if (!empty($filtro['municipio'])) {
                $sql .= " AND mr.municipio = :municipio";
                $params[':municipio'] = $filtro['municipio'];
            }
    
            $sql .= ")";
        }
    
        $sql .= " GROUP BY a.tipo_evento ORDER BY total DESC";
    
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}
