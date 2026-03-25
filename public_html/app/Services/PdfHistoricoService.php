<?php

require_once __DIR__ . '/../Lib/dompdf/autoload.inc.php';
require_once __DIR__ . '/HistoricoPdfConfig.php';
require_once __DIR__ . '/HistoricoService.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfHistoricoService
{
    private const REGISTROS_POR_BLOCO = 36;
    private const REFERENCIA_LIMITE = 120;
    private const DESCRICAO_LIMITE = 88;

    public static function gerar(
        array $registros,
        array $filtrosDescricao,
        string $usuarioGerador,
        array $totalizadores,
        string $hash,
        array $metadadosExportacao = []
    ): void {
        self::ajustarMemoriaParaRelatorio();

        $cachePath = self::cachePath($hash);
        $pdfEmCache = self::carregarCache($cachePath);

        if ($pdfEmCache !== null) {
            self::enviarPdf($pdfEmCache);
        }

        $html = self::htmlRelatorio(
            $registros,
            $filtrosDescricao,
            $usuarioGerador,
            $totalizadores,
            $hash,
            $metadadosExportacao
        );

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('dpi', 96);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('Helvetica', 'normal');

        $canvas->page_text(
            705,
            575,
            'Pagina {PAGE_NUM} de {PAGE_COUNT}',
            $font,
            9,
            [0, 0, 0]
        );

        $pdfGerado = $dompdf->output();
        self::salvarCache($cachePath, $pdfGerado);
        self::enviarPdf($pdfGerado);
    }

    private static function ajustarMemoriaParaRelatorio(): void
    {
        $limiteAtual = self::memoryLimitBytes((string) ini_get('memory_limit'));
        $limiteSeguro = 268435456; // 256 MB

        if ($limiteAtual > 0 && $limiteAtual < $limiteSeguro) {
            @ini_set('memory_limit', '256M');
        }
    }

    private static function memoryLimitBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return -1;
        }

        $unit = strtolower(substr($value, -1));
        $bytes = (int) $value;

        return match ($unit) {
            'g' => $bytes * 1024 * 1024 * 1024,
            'm' => $bytes * 1024 * 1024,
            'k' => $bytes * 1024,
            default => (int) $value,
        };
    }

    private static function imgBase64(string $path): string
    {
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2);
        $full = rtrim((string) $documentRoot, '\\/') . $path;

        if (!is_file($full)) {
            return '';
        }

        $type = pathinfo($full, PATHINFO_EXTENSION);
        $data = @file_get_contents($full);

        if ($data === false) {
            return '';
        }

        return 'data:image/' . $type . ';base64,' . base64_encode($data);
    }

    private static function htmlRelatorio(
        array $registros,
        array $filtros,
        string $usuarioGerador,
        array $totalizadores,
        string $hash,
        array $metadadosExportacao = []
    ): string {
        $logoGov = self::imgBase64('/assets/images/logo.gov.pa.png');
        $logoCedec = self::imgBase64('/assets/images/logo-cedec.png');
        $logoGovHtml = $logoGov !== '' ? '<img class="report-logo report-logo-left" src="' . $logoGov . '" alt="Governo do Para">' : '';
        $logoCedecHtml = $logoCedec !== '' ? '<img class="report-logo report-logo-right" src="' . $logoCedec . '" alt="CEDEC">' : '';

        $periodo = self::escape($filtros['periodo'] ?? 'Todos');
        $usuario = self::escape($filtros['usuario'] ?? 'Todos');
        $acao = self::escape($filtros['acao'] ?? 'Todas');
        $usuarioGerador = self::escape($usuarioGerador);
        $hashCurto = self::escape(substr($hash, 0, 32));

        $totalRegistrosRecorte = (int) ($metadadosExportacao['total_registros'] ?? count($registros));
        $registrosExportados = (int) ($metadadosExportacao['registros_exportados'] ?? count($registros));
        $houveCorte = (bool) ($metadadosExportacao['houve_corte'] ?? false);
        $limiteRegistros = (int) ($metadadosExportacao['limite_registros'] ?? HistoricoPdfConfig::MAX_REGISTROS_EXPORTACAO);
        $totalAcoes = count($totalizadores);
        $blocos = max(1, (int) ceil(max(1, $registrosExportados) / self::REGISTROS_POR_BLOCO));
        $notaVolume = $houveCorte
            ? "Recorte com {$totalRegistrosRecorte} registros. PDF otimizado com os {$registrosExportados} registros mais recentes, limitado a {$limiteRegistros} itens por exportacao."
            : "PDF com {$registrosExportados} registros do recorte atual, sem necessidade de reduzir o volume exportado.";

        $totalizadoresHtml = self::renderTotalizadores($totalizadores);
        $registrosHtml = self::renderRegistros($registros);

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 28px 30px 40px 30px; }

body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 10px;
    color: #1f2d3a;
}

* {
    box-sizing: border-box;
}

.report-header {
    border-bottom: 2px solid #0f3d57;
    padding-bottom: 8px;
    margin-bottom: 16px;
}

.report-header table,
.summary-table,
.totals-table,
.records-table {
    width: 100%;
    border-collapse: collapse;
}

.report-header td {
    vertical-align: middle;
}

.report-logo {
    display: block;
    width: auto;
    height: 38px;
}

.report-logo-right {
    margin-left: auto;
}

.report-title {
    text-align: center;
    padding: 0 6px;
}

.report-title strong {
    display: block;
    font-size: 14px;
    line-height: 1.12;
    color: #0f3d57;
    margin-bottom: 3px;
    white-space: nowrap;
}

.report-subtitle {
    font-size: 8.5px;
    line-height: 1.22;
    white-space: nowrap;
}

.summary-table {
    margin-bottom: 16px;
}

.summary-table td {
    width: 33.33%;
    padding: 0 8px 0 0;
    vertical-align: top;
}

.summary-card {
    border: 1px solid #d8e1e8;
    background: #f7fafc;
    padding: 10px 12px;
    min-height: 82px;
}

.summary-label {
    display: block;
    font-size: 9px;
    font-weight: bold;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #5f7486;
    margin-bottom: 6px;
}

.summary-value {
    display: block;
    font-size: 13px;
    font-weight: bold;
    color: #0f3d57;
    margin-bottom: 6px;
}

.summary-note {
    font-size: 9px;
    line-height: 1.45;
    color: #4a5a67;
}

.section-title {
    margin: 0 0 8px 0;
    color: #0f3d57;
    font-size: 12px;
}

.section-note {
    margin: 0 0 10px 0;
    color: #5f7486;
    font-size: 9px;
}

.totals-table {
    margin-bottom: 18px;
}

.totals-table th,
.totals-table td,
.records-table th,
.records-table td {
    border: 1px solid #d7e0e7;
    padding: 6px;
    vertical-align: top;
}

.totals-table th,
.records-table th {
    background: #0f3d57;
    color: #ffffff;
    font-size: 9px;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.totals-table td {
    font-size: 9px;
}

.totals-table td.total-qtd {
    text-align: center;
    width: 90px;
    font-weight: bold;
}

.records-section {
    margin-bottom: 16px;
}

.page-break {
    page-break-before: always;
}

.records-table {
    table-layout: fixed;
}

.records-table th:nth-child(1) { width: 16%; }
.records-table th:nth-child(2) { width: 18%; }
.records-table th:nth-child(3) { width: 20%; }
.records-table th:nth-child(4) { width: 18%; }
.records-table th:nth-child(5) { width: 28%; }

.records-table td {
    font-size: 8.5px;
    line-height: 1.4;
    word-break: break-word;
}

.code-chip {
    display: inline-block;
    margin-top: 4px;
    padding: 2px 5px;
    border: 1px solid #d7e0e7;
    background: #f5f8fa;
    font-size: 7.5px;
    color: #5f7486;
}

.empty-state {
    border: 1px solid #d8e1e8;
    background: #f7fafc;
    padding: 18px;
    text-align: center;
    color: #5f7486;
}

.report-footer {
    margin-top: 18px;
    padding-top: 10px;
    border-top: 1px solid #d8e1e8;
    font-size: 9px;
    color: #5f7486;
}
</style>
</head>
<body>

<div class="report-header">
    <table>
        <tr>
            <td width="16%">{$logoGovHtml}</td>
            <td width="68%" class="report-title">
                <strong>Relatorio do historico de usuarios</strong>
                <div class="report-subtitle">
                    Corpo de Bombeiros Militar do Para<br>
                    Coordenadoria Estadual de Protecao e Defesa Civil<br>
                    Subdivisao de Informacoes de Monitoramento de Desastres
                </div>
            </td>
            <td width="16%" align="right">{$logoCedecHtml}</td>
        </tr>
    </table>
</div>

<table class="summary-table">
    <tr>
        <td>
            <div class="summary-card">
                <span class="summary-label">Filtros do recorte</span>
                <span class="summary-value">Periodo e auditoria</span>
                <div class="summary-note">
                    Periodo: {$periodo}<br>
                    Usuario: {$usuario}<br>
                    Tipo de acao: {$acao}
                </div>
            </div>
        </td>
        <td>
            <div class="summary-card">
                <span class="summary-label">Volume analisado</span>
                <span class="summary-value">{$registrosExportados} registros</span>
                <div class="summary-note">
                    {$totalAcoes} tipos de acao consolidados<br>
                    {$notaVolume}<br>
                    {$blocos} bloco(s) de registros no PDF
                </div>
            </div>
        </td>
        <td>
            <div class="summary-card">
                <span class="summary-label">Emissao</span>
                <span class="summary-value">{$usuarioGerador}</span>
                <div class="summary-note">
                    Documento gerado com hash parcial de validacao<br>
                    {$hashCurto}
                </div>
            </div>
        </td>
    </tr>
</table>

<h3 class="section-title">Totalizadores por tipo de acao</h3>
<p class="section-note">Resumo consolidado do mesmo recorte aplicado no historico.</p>
<table class="totals-table">
    <thead>
        <tr>
            <th>Acao</th>
            <th>Quantidade</th>
        </tr>
    </thead>
    <tbody>
        {$totalizadoresHtml}
    </tbody>
</table>

{$registrosHtml}

<div class="report-footer">
    Relatorio gerado automaticamente pelo sistema inteligente multirriscos. Hash de verificacao: {$hashCurto}
</div>

</body>
</html>
HTML;
    }

    private static function renderTotalizadores(array $totalizadores): string
    {
        if ($totalizadores === []) {
            return '<tr><td colspan="2">Nenhuma acao encontrada no recorte atual.</td></tr>';
        }

        arsort($totalizadores);
        $html = '';

        foreach ($totalizadores as $acaoCodigo => $quantidade) {
            $html .= '<tr>'
                . '<td>' . self::escape(HistoricoService::labelAcao((string) $acaoCodigo)) . '</td>'
                . '<td class="total-qtd">' . (int) $quantidade . '</td>'
                . '</tr>';
        }

        return $html;
    }

    private static function renderRegistros(array $registros): string
    {
        if ($registros === []) {
            return '<div class="empty-state">Nenhum registro encontrado para os filtros informados.</div>';
        }

        $blocos = array_chunk($registros, self::REGISTROS_POR_BLOCO);
        $html = '';

        foreach ($blocos as $indice => $bloco) {
            $classeBloco = $indice > 0 ? 'records-section page-break' : 'records-section';
            $titulo = 'Registros de auditoria - bloco ' . ($indice + 1) . ' de ' . count($blocos);

            $html .= '<section class="' . $classeBloco . '">';
            $html .= '<h3 class="section-title">' . self::escape($titulo) . '</h3>';
            $html .= '<table class="records-table">';
            $html .= '<thead><tr>'
                . '<th>Data/Hora</th>'
                . '<th>Usuario</th>'
                . '<th>Acao</th>'
                . '<th>Descricao</th>'
                . '<th>Referencia</th>'
                . '</tr></thead><tbody>';

            foreach ($bloco as $registro) {
                $acaoCodigo = (string) ($registro['acao_codigo'] ?? '');
                $acaoDescricao = (string) ($registro['acao_descricao'] ?? '');
                $acaoLabel = HistoricoService::labelAcao($acaoCodigo, $acaoDescricao);

                $html .= '<tr>'
                    . '<td>' . self::escape((string) ($registro['data_hora'] ?? '-')) . '</td>'
                    . '<td>' . self::escape((string) ($registro['usuario_nome'] ?? '-')) . '</td>'
                    . '<td>'
                        . self::escape($acaoLabel)
                        . '<br><span class="code-chip">' . self::escape($acaoCodigo !== '' ? $acaoCodigo : 'SEM_CODIGO') . '</span>'
                    . '</td>'
                    . '<td>' . self::escape(self::limitText($acaoDescricao, self::DESCRICAO_LIMITE, 'Sem descricao complementar.')) . '</td>'
                    . '<td>' . self::escape(self::limitText((string) ($registro['referencia'] ?? ''), self::REFERENCIA_LIMITE, 'Sem referencia complementar.')) . '</td>'
                    . '</tr>';
            }

            $html .= '</tbody></table></section>';
        }

        return $html;
    }

    private static function limitText(string $value, int $limit, string $fallback): string
    {
        $value = trim($value);

        if ($value === '' || $value === '-') {
            return $fallback;
        }

        return mb_strimwidth($value, 0, $limit, '...');
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private static function cachePath(string $hash): string
    {
        $baseDir = dirname(__DIR__, 2) . '/storage/cache/pdf/historico';

        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0775, true);
        }

        return $baseDir . '/relatorio_historico_' . $hash . '.pdf';
    }

    private static function carregarCache(string $cachePath): ?string
    {
        if (!is_file($cachePath)) {
            return null;
        }

        $conteudo = @file_get_contents($cachePath);
        return $conteudo === false ? null : $conteudo;
    }

    private static function salvarCache(string $cachePath, string $pdf): void
    {
        $diretorio = dirname($cachePath);

        if (!is_dir($diretorio)) {
            @mkdir($diretorio, 0775, true);
        }

        @file_put_contents($cachePath, $pdf);
    }

    private static function enviarPdf(string $pdf): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="relatorio_historico_usuarios.pdf"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, max-age=300');

        echo $pdf;
        exit;
    }
}
