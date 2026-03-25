<?php

use Dompdf\Dompdf;

require_once __DIR__ . '/../Lib/dompdf/autoload.inc.php';
require_once __DIR__ . '/../Helpers/TimeHelper.php';

class RelatorioAnaliticoPdfService
{
    public static function gerar(array $dados, array $filtros)
    {
        if (ob_get_length()) ob_end_clean();
        ini_set('memory_limit', '1024M');

    
        $html = self::html($dados, $filtros);
    
        $dompdf = new Dompdf([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => false,
            'isPhpEnabled' => false,
            'defaultFont' => 'Helvetica'
        ]);

    
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();
    
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('Helvetica');
    
        $canvas->page_text(
            480,   // ← mais para dentro
            805,   // ↑ um pouco mais alto
            "Página {PAGE_NUM} de {PAGE_COUNT}",
            $font,
            9,
            [0,0,0]
        );

        $dataHora = TimeHelper::now('d-m-Y_H-i');
        $nomeArquivo = "relatorio_analitico_multirriscos_{$dataHora}_(h).pdf";
        
        header('Content-Type: application/pdf');
        header("Content-Disposition: inline; filename=\"{$nomeArquivo}\"");

    
        echo $dompdf->output();
        exit;
    }


    
    private static function html($d, $f, $sections = [])
    {
        $logoGov   = self::img('/assets/images/logo.gov.pa.png');
        $logoCedec = self::img('/assets/images/logo-cedec.png');
        
        $logoSIMD  = self::img('/assets/images/SIMD.png');
        $logoANA   = self::img('/assets/images/ana.png');
        $logoCEN   = self::img('/assets/images/censipam.png');
        $logoINMET = self::img('/assets/images/INMET.png');
        $logoSGB   = self::img('/assets/images/sgb.png');
        $logoCEM   = self::img('/assets/images/Cemaden.png');
        $logoSEM   = self::img('/assets/images/semas.png');
        $logoCENAD = self::img('/assets/images/cenad.jpeg');



        $ano       = $f['ano'] ?: 'Todos';
        $meses = [
        1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',
        5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',
        9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'
        ];
        
        $mes = !empty($f['mes']) && isset($meses[(int)$f['mes']])
              ? $meses[(int)$f['mes']]
              : 'Todos';


        $regiao    = $f['regiao'] ?: 'Todas';
        $municipio = $f['municipio'] ?: 'Todos';
        $data = TimeHelper::now('d/m/Y H:i');


        $sev1 = self::tabela($d['severidade'], 'nivel_gravidade', 'total');
        $sev2 = self::tabelaComOrdem($d['municipios'], 'municipio', 'total_alertas');
        $sev3 = self::tabela($d['eventos_qtd'], 'tipo_evento', 'total');
        $sev4 = self::tabela($d['duracao_media'], 'tipo_evento', 'duracao_media_horas');

        $temp1 = self::tabelaObjeto($d['temporal']['evolucao_anual']);
        $tempCancelados = self::tabelaObjeto($d['temporal']['cancelados_por_ano'] ?? []);
        $temp2 = self::tabelaObjeto($d['temporal']['sazonalidade']);

        $temp3 = self::tabelaObjeto($d['temporal']['frequencia_hora']);
        $temp4 = self::tabelaMulti($d['temporal']['multi_evento']);

        $tip1 = self::tabelaCorrelacaoTipologia($d['tipologia']['correlacao'] ?? []);
        $tip2 = self::tabelaTipologiaRegiao($d['tipologia']['por_regiao'] ?? []);



        $ind1 = self::tabelaComOrdem($d['indice']['irp'], 'regiao_integracao', 'irp');
        $ind2 = self::tabelaComOrdem($d['indice']['ipt'], 'municipio', 'ipt');

        
        


        return <<<HTML
<html>
<head>
<meta charset="UTF-8">

<style>
@page { 
  margin:120px 40px 160px 40px; 
}
@page:first {
    margin: 0;
}

@page:first {
    @top-center { content: none; }
    @bottom-center { content: none; }
}



body { 
    font-family: Helvetica, Arial, sans-serif;
    font-size:12px; 
    
}

.header{
    position:fixed;
    top:-100px;
    left:0;
    right:0;
    border-bottom:3px solid #0b3c68;
}

.capa-page{
    page-break-after: always;
    text-align: center;
}

.capa-conteudo{
    margin-top: 0;
}


.capa-topo{
    position: fixed;
    top: 20px;
    left: 0;
    right: 0;

    border-bottom:3px solid #0b3c68;
    padding-bottom:12px;
}


.capa-topo img{
    height:70px;
}

.capa-titulo{
    font-size:26px;
    font-weight:bold;
    color:#0b3c68;
    margin-top:280px;
   
}

.capa-subtitulo{
    font-size:18px;
    margin-top:10px;
    margin-bottom:180px;
    color:#444;
}

.capa-box{
    margin-top:60px;
    border-top:2px solid #0b3c68;
    border-bottom:2px solid #0b3c68;
    padding:18px 0;
    font-size:13px;
}

.capa-rodape{
    position: fixed;
    bottom: 60px;
    left: 0;
    right: 0;
}

.capa-rodape img{
    height:28px;
    margin:0 6px;
}

.capa-footer-texto{
    position: fixed;
    bottom: 35px;
    left: 0;
    right: 0;
    font-size:14px;
}



.footer{
    position: fixed;
    bottom:-120px;
    left:0;
    right:0;
    border-top:2px solid #ccc;
    text-align:center;
    font-size:14px;
}

.footer-logos{
    margin-bottom:6px;
}

.footer-logos img{
    height:22px;
    margin:6px 6px 2px 6px;
}

.footer-texto{
    font-size:14px;
   
}


/* título dinâmico da seção */
.section-title{
    position: running(sectionTitle);
    font-weight:bold;
    font-size:13px;
    color:#0b3c68;
}

@page{
    @top-center{
        content: element(sectionTitle);
    }
}

h2{
    color:#0b3c68;
    border-bottom:2px solid #0b3c68;
    padding-bottom:4px;
    margin-top:30px;
    page-break-after:avoid;
    page-break-inside:avoid;
}

h3{
    font-size:13px;
    color:#37474f;
    margin-top:18px;
    margin-bottom:6px;
    font-weight:bold;
}

.bloco{
    page-break-inside: avoid;
}

.bloco h3{
    page-break-after: avoid;
}

.bloco table{
    page-break-inside: auto;
}

.bloco thead{
    display: table-header-group;
}


table{
    width:100%;
    border-collapse:collapse;
    page-break-inside:avoid;
}

tr, td, th{ page-break-inside:avoid; }

th{
    background:#f5f7fa;
    padding:6px;
    text-align:left;
}

td{
    padding:6px;
   border-bottom:0.5px solid #ddd;

}

.valor{
    text-align:right;
    font-weight:bold;
    color:#0b3c68;
}

.page-break{
    page-break-before:always;
}

.page-marker{
    position:absolute;
    top:0;
}


.sumario ul{
    list-style:none;
    padding:0;
}

.sumario li{
    margin-bottom:6px;
}

.sumario .linha{
    display:flex;
}

.sumario .titulo{
    flex:1;
}

.sumario .pagina::before{
    content: target-counter(attr(data-target), page);
}


.sumario a{
    text-decoration:none;
    color:#000;
}

.sumario a::after{
    content: " ........................................ " target-counter(attr(href), page);
}

</style>
</head>

<body>

<div class="capa-page">
 
    <div class="capa-conteudo">
 
        <div class="capa-topo">
            <table width="100%">
                <tr>
                    <td width="25%"><img src="$logoGov"></td>
                    <td width="50%" align="center">
                        <strong>GOVERNO DO ESTADO DO PARÁ</strong><br>
                        CORPO DE BOMBEIROS MILITAR DO PARÁ<br>
                        COORDENADORIA ESTADUAL DE PROTEÇÃO E DEFESA CIVIL<br>
                        DIVISÃO DE GESTÃO DE RISCO – DGR
                    </td>
                    <td width="25%" align="right"><img src="$logoCedec"></td>
                </tr>
            </table>
        </div>
    

        <div class="capa-titulo">
            RELATÓRIO ANALÍTICO MULTIRRISCOS
        </div>
    
        <div class="capa-subtitulo">
            Sistema Inteligente Multirriscos
        </div>
    
        <div class="capa-box">
            <strong>ANO:</strong> $ano &nbsp;&nbsp;&nbsp;
            <strong>MÊS:</strong> $mes <br><br>
            <strong>REGIÃO:</strong> $regiao &nbsp;&nbsp;&nbsp;
            <strong>MUNICÍPIO:</strong> $municipio
            <br><br>
            <strong>DATA DE GERAÇÃO:</strong> $data
        </div>
    
        <div class="capa-rodape">
            <img src="$logoCedec">
            <img src="$logoSIMD">
            <img src="$logoSEM">
            <img src="$logoANA">
            <img src="$logoCEN">
            <img src="$logoCEM">
            <img src="$logoINMET">
            <img src="$logoSGB">
            <img src="$logoCENAD">
        </div>
        
        <div class="capa-footer-texto">
            Subdivisão de Informações de Monitoramento de Desastres – SIMD
        </div>
    
    </div>
</div>



<div class="header">
<table width="100%">
<tr>
<td width="25%"><img src="$logoGov" height="55"></td>
<td width="50%" align="center">
<strong>RELATÓRIO ANALÍTICO MULTIRRISCOS</strong><br>
Corpo de Bombeiros Militar do Pará <br> 
Coordenadoria Estadual de Proteção e Defesa Civil<br>
Divisão de Gestão de Risco – DGR
</td>
<td width="25%" align="right"><img src="$logoCedec" height="55"></td>
</tr>
</table>
</div>

<h2>Parâmetros do Relatório</h2>
<table>
<tr><td>Ano</td><td class="valor">$ano</td></tr>
<tr><td>Mês</td><td class="valor">$mes</td></tr>
<tr><td>Região</td><td class="valor">$regiao</td></tr>
<tr><td>Município</td><td class="valor">$municipio</td></tr>
<tr><td>Gerado em</td><td class="valor">$data</td></tr>
</table>

<div class="page-break"></div>



<h2>Sumário</h2>

<table>
<tr><td><strong>1. Severidade</strong></td></tr>
<tr><td>&nbsp;&nbsp;• Distribuição por Severidade</td></tr>
<tr><td>&nbsp;&nbsp;• Municípios Mais Impactados</td></tr>
<tr><td>&nbsp;&nbsp;• Quantidade de Alertas por Evento</td></tr>
<tr><td>&nbsp;&nbsp;• Duração Média (horas) por Evento</td></tr>

<tr><td style="padding-top:8px;"><strong>2. Análise Temporal</strong></td></tr>
<tr><td>&nbsp;&nbsp;• Evolução Anual de Alertas</td></tr>
<tr><td>&nbsp;&nbsp;• Alertas Cancelados por Ano</td></tr>
<tr><td>&nbsp;&nbsp;• Sazonalidade Mensal</td></tr>
<tr><td>&nbsp;&nbsp;• Frequência por Período do Dia</td></tr>
<tr><td>&nbsp;&nbsp;• Sazonalidade Mensal — Comparativo de Eventos</td></tr>

<tr><td style="padding-top:8px;"><strong>3. Tipologia dos Eventos</strong></td></tr>
<tr><td>&nbsp;&nbsp;• Correlação: Tipo de Evento × Severidade</td></tr>
<tr><td>&nbsp;&nbsp;• Tipologia por Região de Integração</td></tr>

<tr><td style="padding-top:8px;"><strong>4. Índices de Risco</strong></td></tr>
<tr><td>&nbsp;&nbsp;• Índice Regional de Pressão (IRP)</td></tr>
<tr><td>&nbsp;&nbsp;• Índice de Pressão Territorial (IPT)</td></tr>
</table>





<div class="page-break"></div>
<div class="section-title">Severidade</div>
<h2>Severidade</h2>
<div class="bloco">
    <h3>Distribuição por Severidade</h3>
    $sev1
</div>

<div class="bloco">
<h3>Municípios Mais Impactados</h3>
$sev2
</div>

<div class="bloco">
<h3>Quantidade de Alertas por Evento</h3>
$sev3
</div>

<div class="bloco">
<h3>Duração Média (horas) por Evento</h3>
$sev4
</div>


<div class="page-break"></div>
<div class="section-title">Temporal</div>
<h2>Temporal</h2>
<div class="bloco">
<h3>Evolução Anual de Alertas</h3>
$temp1
</div>

<div class="bloco">
<h3>Alertas Cancelados por Ano</h3>
$tempCancelados
</div>


<div class="bloco">
<h3>Sazonalidade Mensal</h3>
$temp2
</div>

<div class="bloco">
<h3>Frequência por Período do Dia</h3>
$temp3
</div>

<div class="bloco">
<h3>Sazonalidade Mensal — Comparativo de Eventos</h3>
$temp4
</div>

<div class="page-break"></div>
<div class="section-title">Tipologia</div>
<h2>Tipologia</h2>
<div class="bloco">
<h3>Correlação: Tipo de Evento × Severidade</h3>
$tip1
</div>

<div class="bloco">
<h3>Tipologia por Região de Integração</h3>
$tip2
</div>

<div class="page-break"></div>
<div class="section-title">Índices de Risco</div>
<h2>Índices de Risco</h2>

<div class="bloco">
<h3>Índice Regional de Pressão (IRP)</h3>
<p>Avalia a pressão operacional sobre uma região de integração, considerando a severidade e o número de municípios afetados.</p>
<p style="text-align:center; font-weight:bold;">IRP = Σ (alertas × peso da severidade × municípios afetados)</p>
<p style="text-align:center;">Pesos: Baixo = 1, Moderado = 2, Alto = 3, Muito Alto = 4 e Extremo = 5</p>
$ind1
</div>

<div class="bloco">
<h3>Índice de Pressão Territorial (IPT)</h3>
<p>Mede a intensidade da pressão sofrida por um município considerando a severidade do alerta e o tempo de duração do evento.</p>
<p style="text-align:center; font-weight:bold;">IPT = Σ (alertas × peso da severidade × duração em horas)</p>
<p style="text-align:center;">Pesos: Baixo = 1, Moderado = 2, Alto = 3, Muito Alto = 4 e Extremo = 5</p>
$ind2
</div>



<div class="footer">

    <div class="footer-logos">
        <img src="$logoCedec">
        <img src="$logoSIMD">
        <img src="$logoSEM">
        <img src="$logoANA">
        <img src="$logoCEN">
        <img src="$logoCEM">
        <img src="$logoINMET">
        <img src="$logoSGB">
        <img src="$logoCENAD">
    </div>

    <div class="footer-texto">
        Subdivisão de Informações de Monitoramento de Desastres – SIMD
    </div>

</div>


</body>
</html>
HTML;
    }

    private static function tabela($lista, $c1, $c2)
    {
        if (empty($lista)) return '';

        $html = "<table><thead><tr><th>Descrição</th><th class='valor'>Valor</th></tr></thead><tbody>";
        $lista = array_slice($lista, 0, 144);

        foreach ($lista as $l) {
            $html .= "<tr><td>{$l[$c1]}</td><td class='valor'>{$l[$c2]}</td></tr>";
        }
        return $html."</tbody></table>";
    }
    
    private static function tabelaComOrdem($lista, $c1, $c2)
    {
        if (empty($lista)) return '';
    
        $html = "
        <table>
            <thead>
                <tr>
                    <th style='width:60px'>Ordem</th>
                    <th>Descrição</th>
                    <th class='valor'>Valor</th>
                </tr>
            </thead>
            <tbody>
        ";
    
        $pos = 1;
    
        foreach ($lista as $l) {
    
            $html .= "
            <tr>
                <td style='text-align:center'>{$pos}</td>
                <td>{$l[$c1]}</td>
                <td class='valor'>{$l[$c2]}</td>
            </tr>
            ";
    
            $pos++;
        }
    
        return $html . "</tbody></table>";
    }


    private static function tabelaObjeto($obj)
    {
        if (empty($obj)) return '';

        $html = "<table><thead><tr><th>Descrição</th><th class='valor'>Valor</th></tr></thead><tbody>";
        foreach ($obj as $k => $v) {
            $html .= "<tr><td>$k</td><td class='valor'>$v</td></tr>";
        }
        return $html."</tbody></table>";
    }
    
    
    private static function tabelaMulti($obj)
    {
        if (empty($obj) || !is_array($obj)) return '';
    
        $cols = array_keys(reset($obj));
    
        $html = "<table><thead><tr><th>Descrição</th>";
    
        foreach ($cols as $c) {
            $html .= "<th>{$c}</th>";
        }
    
        $html .= "</tr></thead><tbody>";
    
        foreach ($obj as $k => $linha) {
    
            $html .= "<tr>";
            $html .= "<td><strong>{$k}</strong></td>";
    
            foreach ($cols as $c) {
                $html .= "<td style='text-align:center'>" . ($linha[$c] ?? 0) . "</td>";
            }
    
            $html .= "</tr>";
        }
    
        $html .= "</tbody></table>";
    
        return $html;
    }


   private static function tabelaCorrelacaoTipologia($lista)
    {
        if (empty($lista)) return '';
    
        $severidades = ['BAIXO','MODERADO','ALTO','MUITO ALTO','EXTREMO'];
    
        // eventos únicos (mesma lógica do JS)
        $eventos = array_unique(array_column($lista, 'tipo_evento'));
    
        // montar mapa
        $mapa = [];
    
        foreach ($lista as $i) {
            $mapa[$i['tipo_evento']][$i['nivel_gravidade']] = $i['total'];
        }
    
        $html = "<table>";
        $html .= "<thead><tr><th>Evento</th>";
    
        foreach ($severidades as $s) {
            $html .= "<th style='text-align:center'>{$s}</th>";
        }
    
        $html .= "</tr></thead><tbody>";
    
        foreach ($eventos as $ev) {
    
            $html .= "<tr>";
            $html .= "<td><strong>{$ev}</strong></td>";
    
            foreach ($severidades as $s) {
                $valor = $mapa[$ev][$s] ?? 0;
                $html .= "<td style='text-align:center'>{$valor}</td>";
            }
    
            $html .= "</tr>";
        }
    
        $html .= "</tbody></table>";
    
        return $html;
    }
    
    private static function tabelaTipologiaRegiao($lista)
    {
        if (empty($lista)) return '';
    
        $regioes = array_unique(array_column($lista, 'regiao_integracao'));
        $eventos = array_unique(array_column($lista, 'tipo_evento'));
    
        $mapa = [];
    
        foreach ($lista as $i) {
            $mapa[$i['regiao_integracao']][$i['tipo_evento']] = $i['total'];
        }
    
        $html = "<table>";
        $html .= "<thead><tr><th>Região</th>";
    
        foreach ($eventos as $e) {
            $html .= "<th style='text-align:center'>{$e}</th>";
        }
    
        $html .= "</tr></thead><tbody>";
    
        foreach ($regioes as $r) {
    
            $html .= "<tr>";
            $html .= "<td><strong>{$r}</strong></td>";
    
            foreach ($eventos as $e) {
                $valor = $mapa[$r][$e] ?? 0;
                $html .= "<td style='text-align:center'>{$valor}</td>";
            }
    
            $html .= "</tr>";
        }
    
        $html .= "</tbody></table>";
    
        return $html;
    }




    private static function img($path)
    {
        $full = $_SERVER['DOCUMENT_ROOT'].$path;
        if (!is_file($full)) return '';
        $type = pathinfo($full, PATHINFO_EXTENSION);
        return "data:image/$type;base64,".base64_encode(file_get_contents($full));
    }
    
    

}
