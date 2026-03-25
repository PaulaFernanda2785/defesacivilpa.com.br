<?php

require_once __DIR__ . '/AnaliseSeveridadeService.php';
require_once __DIR__ . '/AnaliseMunicipiosImpactadosService.php';
require_once __DIR__ . '/AnaliseAlertasEmitidosService.php';
require_once __DIR__ . '/AnaliseTemporalService.php';
require_once __DIR__ . '/AnaliseTipologiaService.php';
require_once __DIR__ . '/AnaliseIndiceRiscoService.php';


class AnaliseGlobalService
{
    private static function aplicarFiltroData(array $filtro, array &$params): string
    {
        $ano = isset($filtro['ano']) && $filtro['ano'] !== '' ? (int) $filtro['ano'] : null;
        $mes = isset($filtro['mes']) && $filtro['mes'] !== '' ? (int) $filtro['mes'] : null;

        if ($ano !== null && $ano > 0) {
            if ($mes !== null && $mes >= 1 && $mes <= 12) {
                $inicioMes = DateTimeImmutable::createFromFormat('!Y-m-d', sprintf('%04d-%02d-01', $ano, $mes));

                if ($inicioMes instanceof DateTimeImmutable) {
                    $params[':data_inicio'] = $inicioMes->format('Y-m-d');
                    $params[':data_fim'] = $inicioMes->modify('+1 month')->format('Y-m-d');
                    return " AND a.data_alerta >= :data_inicio AND a.data_alerta < :data_fim";
                }
            }

            $params[':data_inicio_ano'] = sprintf('%04d-01-01', $ano);
            $params[':data_fim_ano'] = sprintf('%04d-01-01', $ano + 1);

            return " AND a.data_alerta >= :data_inicio_ano AND a.data_alerta < :data_fim_ano";
        }

        if ($mes !== null && $mes >= 1 && $mes <= 12) {
            $params[':mes'] = $mes;
            return " AND MONTH(a.data_alerta) = :mes";
        }

        return '';
    }

    private static function canceladosPorAno(PDO $db, array $filtro): array
    {
        $params = [];
    
        $sql = "
            SELECT
                YEAR(a.data_alerta) AS ano,
                COUNT(DISTINCT a.id) AS total
            FROM alertas a
            JOIN alerta_municipios am ON am.alerta_id = a.id
            JOIN municipios_regioes_pa mr ON mr.cod_ibge = am.municipio_codigo
            WHERE a.status = 'CANCELADO'
        ";

        $sql .= self::aplicarFiltroData($filtro, $params);
    
        if (!empty($filtro['regiao'])) {
            $sql .= " AND mr.regiao_integracao = :regiao";
            $params[':regiao'] = $filtro['regiao'];
        }
    
        if (!empty($filtro['municipio'])) {
            $sql .= " AND mr.municipio = :municipio";
            $params[':municipio'] = $filtro['municipio'];
        }
    
        $sql .= " GROUP BY ano ORDER BY ano";
    
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    
        $dados = [];
    
        foreach ($stmt as $r) {
            $dados[$r['ano']] = (int)$r['total'];
        }
    
        return $dados;
    }

    
    
    
    
    public static function gerar($db, $filtro)
    {
        $ano = !empty($filtro['ano']) ? $filtro['ano'] : null;

        return [

            // ===============================
            // SEVERIDADE
            // ===============================
            'severidade' => AnaliseSeveridadeService::proporcaoPorSeveridade($db, $filtro),

            'duracao_media' => AnaliseSeveridadeService::duracaoMediaPorEvento($db, $filtro),

            'eventos_qtd' => AnaliseSeveridadeService::quantidadePorEvento($db, $filtro),

            // ===============================
            // MUNICÍPIOS MAIS IMPACTADOS
            // ===============================
            'municipios' => AnaliseMunicipiosImpactadosService::ranking($db, $filtro, 144),

            // ===============================
            // ALERTAS POR EVENTO
            // ===============================
            'alertas_evento' => AnaliseAlertasEmitidosService::porTipoEvento($db, $filtro),

            // ===============================
            // TEMPORAL
            // ===============================
           'temporal' => [

                    'sazonalidade' =>
                        $ano ? AnaliseTemporalService::sazonalidadeMensal($filtro) : [],
                
                    'frequencia_hora' =>
                        $ano ? AnaliseTemporalService::frequenciaPorHora($filtro) : [],
                
                    'evolucao_anual' =>
                        AnaliseTemporalService::evolucaoAnual($filtro),
                
                    'cancelados_por_ano' =>
                        AnaliseTemporalService::canceladosPorAno($filtro),
                
                    'multi_evento' =>
                        $ano ? AnaliseTemporalService::sazonalidadeMensalMultiEvento($filtro) : [],
                ],





            
            // ===============================
            // TIPOLOGIA
            // ===============================
            'tipologia' => [
                'correlacao' => AnaliseTipologiaService::correlacaoEventoSeveridade($db, $filtro),
                'por_regiao' => AnaliseTipologiaService::tipologiaPorRegiao($db, $filtro),
            ],

            // ===============================
            // ÍNDICES DE RISCO
            // ===============================
            'indice_risco' => [
                'ranking_irp' => AnaliseIndiceRiscoService::rankingIRP($db, $filtro),
                'ranking_ipt' => AnaliseIndiceRiscoService::rankingIPT($db, $filtro),
            ],
            
            'indice' => [
                'irp' => AnaliseIndiceRiscoService::rankingIRP($db, $filtro),
                'ipt' => AnaliseIndiceRiscoService::rankingIPT($db, $filtro),
            ],


        ];
    }
}

