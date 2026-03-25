<?php

class AnaliseImpactoTerritorialService
{
    public static function impactoPorMunicipio(PDO $db, array $filtro): array
    {
        $sql = "
            SELECT 
                mr.municipio,
                COUNT(*) AS total_impactos
            FROM alerta_municipios am
            JOIN municipios_regioes_pa mr
                ON mr.cod_ibge = am.municipio_codigo
            JOIN alertas a
                ON a.id = am.alerta_id
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

        $sql .= "
            GROUP BY mr.municipio
            ORDER BY total_impactos DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
