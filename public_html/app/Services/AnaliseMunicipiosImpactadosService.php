<?php

class AnaliseMunicipiosImpactadosService
{
    public static function ranking(PDO $db, array $filtro, ?int $limite = 10): array
    {
        $sql = "
            SELECT 
                mr.municipio,
                COUNT(DISTINCT a.id) AS total_alertas
            FROM alerta_municipios am
            JOIN alertas a ON a.id = am.alerta_id
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
            GROUP BY mr.municipio
            ORDER BY total_alertas DESC
        ";

        if ($limite !== null && $limite > 0) {
            $sql .= " LIMIT " . (int) $limite;
        }
    
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}
