<?php
require_once __DIR__ . '/../Core/Database.php';

class AnaliseTemporalService
{
    /* ==========================================
       BASE DE FILTROS (PADRÃO GLOBAL DO SISTEMA)
    ========================================== */
    private static function aplicarFiltros(array $filtro, array &$params, string $statusSql = "a.status IN ('ATIVO','ENCERRADO')"): string
    {
        $where = "
            WHERE a.data_alerta IS NOT NULL
        ";

        if ($statusSql !== '') {
            $where .= " AND {$statusSql}";
        }

        $ano = isset($filtro['ano']) && $filtro['ano'] !== ''
            ? (int) $filtro['ano']
            : null;
        $mes = isset($filtro['mes']) && $filtro['mes'] !== ''
            ? (int) $filtro['mes']
            : null;

        if ($ano !== null && $ano > 0) {
            if ($mes !== null && $mes >= 1 && $mes <= 12) {
                $inicioMes = DateTimeImmutable::createFromFormat('!Y-m-d', sprintf('%04d-%02d-01', $ano, $mes));

                if ($inicioMes instanceof DateTimeImmutable) {
                    $params[':data_inicio'] = $inicioMes->format('Y-m-d');
                    $params[':data_fim'] = $inicioMes->modify('+1 month')->format('Y-m-d');
                    $where .= " AND a.data_alerta >= :data_inicio AND a.data_alerta < :data_fim";
                }
            } else {
                $params[':data_inicio_ano'] = sprintf('%04d-01-01', $ano);
                $params[':data_fim_ano'] = sprintf('%04d-01-01', $ano + 1);
                $where .= " AND a.data_alerta >= :data_inicio_ano AND a.data_alerta < :data_fim_ano";
            }
        } elseif ($mes !== null && $mes >= 1 && $mes <= 12) {
            $where .= " AND MONTH(a.data_alerta) = :mes";
            $params[':mes'] = $mes;
        }

        if (!empty($filtro['regiao']) || !empty($filtro['municipio'])) {

            $where .= "
                AND EXISTS (
                    SELECT 1
                    FROM alerta_municipios am
                    JOIN municipios_regioes_pa mr
                      ON mr.cod_ibge = am.municipio_codigo
                    WHERE am.alerta_id = a.id
            ";

            if (!empty($filtro['regiao'])) {
                $where .= " AND mr.regiao_integracao = :regiao";
                $params[':regiao'] = $filtro['regiao'];
            }

            if (!empty($filtro['municipio'])) {
                $where .= " AND mr.municipio = :municipio";
                $params[':municipio'] = $filtro['municipio'];
            }

            $where .= ")";
        }

        return $where;
    }

    /* ==========================================
       SAZONALIDADE MENSAL (FILTRÁVEL)
    ========================================== */
    public static function sazonalidadeMensal(array $filtro): array
    {
        $db = Database::getConnection();

        $params = [];
        $where = self::aplicarFiltros($filtro, $params);

        $sql = "
            SELECT MONTH(a.data_alerta) mes, COUNT(*) total
            FROM alertas a
            $where
            GROUP BY mes
            ORDER BY mes
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return self::formatarMeses($stmt);
    }

    /* ==========================================
       FREQUÊNCIA POR HORA (FILTRÁVEL)
    ========================================== */
    public static function frequenciaPorHora(array $filtro): array
    {
        $db = Database::getConnection();

        $params = [];
        $where = self::aplicarFiltros($filtro, $params);

        $sql = "
            SELECT HOUR(a.inicio_alerta) hora
            FROM alertas a
            $where
            AND a.inicio_alerta IS NOT NULL
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $faixas = [
            'Madrugada (00–05)' => 0,
            'Manhã (06–11)'     => 0,
            'Tarde (12–17)'     => 0,
            'Noite (18–23)'     => 0
        ];

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $h = (int)$r['hora'];

            if ($h < 6) $faixas['Madrugada (00–05)']++;
            elseif ($h < 12) $faixas['Manhã (06–11)']++;
            elseif ($h < 18) $faixas['Tarde (12–17)']++;
            else $faixas['Noite (18–23)']++;
        }

        return $faixas;
    }

    /* ==========================================
       MULTI EVENTO MENSAL (FILTRÁVEL)
    ========================================== */
    public static function sazonalidadeMensalMultiEvento(array $filtro): array
    {
        $db = Database::getConnection();

        $params = [];
        $where = self::aplicarFiltros($filtro, $params);

        $sql = "
            SELECT 
                a.tipo_evento,
                MONTH(a.data_alerta) mes,
                COUNT(*) total
            FROM alertas a
            $where
            GROUP BY a.tipo_evento, mes
            ORDER BY a.tipo_evento, mes
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $meses = self::mesesBase();
        $dados = [];

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

            if (!isset($dados[$r['tipo_evento']])) {
                $dados[$r['tipo_evento']] = array_fill_keys($meses, 0);
            }

            $dados[$r['tipo_evento']][$meses[$r['mes'] - 1]] = (int)$r['total'];
        }

        return $dados;
    }

    /* ==========================================
       EVOLUÇÃO ANUAL (GLOBAL — NÃO FILTRA)
    ========================================== */
    public static function evolucaoAnual(array $filtro = []): array
    {
        $db = Database::getConnection();

        $filtroAnual = $filtro;
        $filtroAnual['ano'] = null;
        $params = [];
        $where = self::aplicarFiltros($filtroAnual, $params);

        $sql = "
            SELECT YEAR(a.data_alerta) ano, COUNT(*) total
            FROM alertas a
            $where
            GROUP BY ano
            ORDER BY ano
        ";

        $dados = [];

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dados[$r['ano']] = (int)$r['total'];
        }

        return $dados;
    }

    /* ==========================================
       ALERTAS CANCELADOS POR ANO (NOVO)
    ========================================== */
    public static function canceladosPorAno(array $filtro = []): array
    {
        $db = Database::getConnection();

        $filtroAnual = $filtro;
        $filtroAnual['ano'] = null;
        $params = [];
        $where = self::aplicarFiltros($filtroAnual, $params, "a.status = 'CANCELADO'");

        $sql = "
            SELECT YEAR(a.data_alerta) ano, COUNT(*) total
            FROM alertas a
            $where
            GROUP BY ano
            ORDER BY ano
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $dados = [];

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dados[$r['ano']] = (int)$r['total'];
        }

        return $dados;
    }

    /* ==========================================
       HELPERS
    ========================================== */
    private static function mesesBase(): array
    {
        return [
            'Jan','Fev','Mar','Abr','Mai','Jun',
            'Jul','Ago','Set','Out','Nov','Dez'
        ];
    }

    private static function formatarMeses($stmt): array
    {
        $meses = self::mesesBase();
        $resultado = array_fill_keys($meses, 0);

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[$meses[$r['mes'] - 1]] = (int)$r['total'];
        }

        return $resultado;
    }
    
    public static function evolucaoAnualPorEvento(array $filtro = []): array
    {
        $db = Database::getConnection();

        $filtroAnual = $filtro;
        $filtroAnual['ano'] = null;
        $params = [];
        $where = self::aplicarFiltros($filtroAnual, $params);
    
        $sql = "
            SELECT
                YEAR(a.data_alerta) AS ano,
                a.tipo_evento,
                COUNT(*) AS total
            FROM alertas a
            $where
            GROUP BY ano, a.tipo_evento
            ORDER BY ano, a.tipo_evento
        ";
    
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    
        $dados = [];
    
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    
            $ano    = $r['ano'];
            $evento = $r['tipo_evento'];
    
            if (!isset($dados[$evento])) {
                $dados[$evento] = [];
            }
    
            $dados[$evento][$ano] = (int)$r['total'];
        }
    
        return $dados;
    }
    
    /* ==========================================
       LISTA DE EVENTOS (PARA O FILTRO DA TELA)
       NÃO SOFRE FILTROS
    ========================================== */
    public static function listaEventos(): array
    {
        $db = Database::getConnection();
    
        return $db->query("
            SELECT DISTINCT tipo_evento
            FROM alertas
            WHERE tipo_evento IS NOT NULL
              AND tipo_evento <> ''
            ORDER BY tipo_evento
        ")->fetchAll(PDO::FETCH_COLUMN);
    }


    /* ==========================================
       SAZONALIDADE MENSAL POR EVENTO (TELA TEMPORAL)
       (USA APENAS ANO — PADRÃO DA VIEW)
    ========================================== */
    public static function sazonalidadeMensalPorEvento($filtroOuAno, string $evento): array
    {
        $db = Database::getConnection();

        $filtro = is_array($filtroOuAno)
            ? $filtroOuAno
            : [
                'ano' => (int) $filtroOuAno,
                'mes' => null,
                'regiao' => null,
                'municipio' => null,
            ];

        $params = [
            ':evento' => $evento,
        ];
        $where = self::aplicarFiltros($filtro, $params);
    
        $stmt = $db->prepare("
            SELECT MONTH(a.data_alerta) mes, COUNT(*) total
            FROM alertas a
            $where
              AND a.tipo_evento = :evento
            GROUP BY mes
            ORDER BY mes
        ");
    
        $stmt->execute($params);
    
        $meses = [
            'Jan','Fev','Mar','Abr','Mai','Jun',
            'Jul','Ago','Set','Out','Nov','Dez'
        ];
    
        $resultado = array_fill_keys($meses, 0);
    
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $resultado[$meses[$r['mes'] - 1]] = (int)$r['total'];
        }
    
        return $resultado;
    }


}
