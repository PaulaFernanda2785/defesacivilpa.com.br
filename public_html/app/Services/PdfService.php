<?php

require_once __DIR__ . '/../Lib/dompdf/autoload.inc.php';
require_once __DIR__ . '/../Helpers/TimeHelper.php';

use Dompdf\Dompdf;

class PdfService
{
    private const PDF_MEMORY_LIMIT = '256M';
    private const MAP_MAX_WIDTH = 1200;
    private const MAP_MAX_HEIGHT = 900;
    private const INFO_MAX_WIDTH = 1080;
    private const INFO_MAX_HEIGHT = 1320;
    private const INFO_JPEG_QUALITY = 82;

    public static function gerarAlerta(
        array $alerta,
        array $municipiosPorRegiao,
        ?string $mapaImagem = null,
        string $modo = 'stream'
    ): ?string {
        @ini_set('display_errors', '0');
        @ini_set('display_startup_errors', '0');
        @ini_set('memory_limit', self::PDF_MEMORY_LIMIT);
        @set_time_limit(60);

        $html = self::htmlAlerta($alerta, $municipiosPorRegiao, $mapaImagem);

        $dompdf = new Dompdf([
            'enable_remote' => true,
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ]);

        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('Helvetica', 'normal');

        $canvas->page_text(
            520,
            820,
            'Pagina {PAGE_NUM} de {PAGE_COUNT}',
            $font,
            9,
            [0, 0, 0]
        );

        $nome = 'alerta_defesa_civil_pa_' .
            preg_replace('/[^a-zA-Z0-9_]/', '_', (string) ($alerta['numero'] ?? 'sem_numero')) .
            '.pdf';

        if ($modo === 'string') {
            return $dompdf->output();
        }

        if ($modo === 'download') {
            $dompdf->stream($nome, ['Attachment' => true]);
            return null;
        }

        $dompdf->stream($nome, ['Attachment' => false]);
        return null;
    }

    private static function imgBase64(
        ?string $path,
        int $maxWidth = 0,
        int $maxHeight = 0,
        ?string $forceFormat = null,
        int $jpegQuality = self::INFO_JPEG_QUALITY
    ): string {
        $full = self::resolveFilePath($path);

        if ($full === null || !is_readable($full)) {
            return '';
        }

        $imageInfo = @getimagesize($full);

        if ($imageInfo === false) {
            $bytes = @file_get_contents($full);
            if ($bytes === false) {
                return '';
            }

            $type = strtolower((string) pathinfo($full, PATHINFO_EXTENSION));
            $type = $type !== '' ? $type : 'png';

            return 'data:image/' . $type . ';base64,' . base64_encode($bytes);
        }

        $mime = strtolower((string) ($imageInfo['mime'] ?? ''));
        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        $targetFormat = self::normalizeImageFormat($forceFormat, $mime);

        $needsResize =
            ($maxWidth > 0 && $width > $maxWidth) ||
            ($maxHeight > 0 && $height > $maxHeight);

        $needsReencode = $targetFormat !== null && self::mimeToFormat($mime) !== $targetFormat;

        if (!$needsResize && !$needsReencode) {
            $bytes = @file_get_contents($full);
            if ($bytes === false) {
                return '';
            }

            $format = self::mimeToFormat($mime) ?? 'png';
            return 'data:image/' . $format . ';base64,' . base64_encode($bytes);
        }

        if (!function_exists('imagecreatetruecolor')) {
            $bytes = @file_get_contents($full);
            if ($bytes === false) {
                return '';
            }

            $fallbackFormat = self::mimeToFormat($mime) ?? 'png';
            return 'data:image/' . $fallbackFormat . ';base64,' . base64_encode($bytes);
        }

        $source = self::loadImageResource($full, $mime);
        if (!$source) {
            $bytes = @file_get_contents($full);
            if ($bytes === false) {
                return '';
            }

            $fallbackFormat = self::mimeToFormat($mime) ?? 'png';
            return 'data:image/' . $fallbackFormat . ';base64,' . base64_encode($bytes);
        }

        $scale = self::scaleFactor($width, $height, $maxWidth, $maxHeight);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        if (!$canvas) {
            imagedestroy($source);
            return '';
        }

        if ($targetFormat === 'png') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
            imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
        } else {
            $background = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $background);
        }

        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            max(1, $width),
            max(1, $height)
        );

        ob_start();
        $encoded = self::encodeImageResource($canvas, $targetFormat, $jpegQuality);
        $bytes = $encoded ? (string) ob_get_clean() : '';
        if (!$encoded) {
            ob_end_clean();
        }

        imagedestroy($canvas);
        imagedestroy($source);

        if ($bytes === '') {
            return '';
        }

        return 'data:image/' . $targetFormat . ';base64,' . base64_encode($bytes);
    }

    private static function normalizeImageFormat(?string $forceFormat, string $mime): ?string
    {
        $format = strtolower(trim((string) $forceFormat));

        if ($format === '') {
            return self::mimeToFormat($mime);
        }

        return match ($format) {
            'jpg', 'jpeg' => 'jpeg',
            'png' => 'png',
            'webp' => 'webp',
            default => self::mimeToFormat($mime),
        };
    }

    private static function mimeToFormat(string $mime): ?string
    {
        return match (strtolower($mime)) {
            'image/jpeg', 'image/jpg' => 'jpeg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };
    }

    private static function loadImageResource(string $fullPath, string $mime): mixed
    {
        return match (strtolower($mime)) {
            'image/jpeg', 'image/jpg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($fullPath) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($fullPath) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($fullPath) : false,
            default => false,
        };
    }

    private static function encodeImageResource(mixed $resource, string $format, int $jpegQuality): bool
    {
        return match ($format) {
            'jpeg' => imagejpeg($resource, null, $jpegQuality),
            'webp' => function_exists('imagewebp') ? imagewebp($resource, null, $jpegQuality) : imagejpeg($resource, null, $jpegQuality),
            default => imagepng($resource),
        };
    }

    private static function scaleFactor(int $width, int $height, int $maxWidth, int $maxHeight): float
    {
        $scale = 1.0;

        if ($maxWidth > 0 && $width > 0) {
            $scale = min($scale, $maxWidth / $width);
        }

        if ($maxHeight > 0 && $height > 0) {
            $scale = min($scale, $maxHeight / $height);
        }

        return min(1.0, max(0.01, $scale));
    }

    private static function resolveFilePath(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (is_file($path) && is_readable($path)) {
            return $path;
        }

        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/\\'));
        $publicRoot = dirname(__DIR__, 2);
        $projectRoot = dirname(__DIR__, 3);

        $candidates = [];

        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $candidates[] = rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\') . DIRECTORY_SEPARATOR . $normalized;
        }

        $candidates[] = $publicRoot . DIRECTORY_SEPARATOR . $normalized;
        $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . $normalized;

        foreach (array_unique($candidates) as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function statusAlerta(array $alerta): array
    {
        if (TimeHelper::isPastLocal($alerta['fim_alerta'] ?? null)) {
            return [
                'texto' => 'ENCERRADO',
                'cor' => '#b71c1c',
            ];
        }

        return [
            'texto' => 'VIGENTE',
            'cor' => '#2e7d32',
        ];
    }

    private static function htmlAlerta(array $alerta, array $regioes, ?string $mapa): string
    {
        $logoGov = self::imgBase64('/assets/images/logo.gov.pa.png');
        $logoCedec = self::imgBase64('/assets/images/logo-cedec.png');
        $logoSIMD = self::imgBase64('/assets/images/SIMD.png');
        $logoANA = self::imgBase64('/assets/images/ana.png');
        $logoCEN = self::imgBase64('/assets/images/censipam.png');
        $logoINMET = self::imgBase64('/assets/images/INMET.png');
        $logoSGB = self::imgBase64('/assets/images/sgb.png');
        $logoCEM = self::imgBase64('/assets/images/Cemaden.png');
        $logoSEM = self::imgBase64('/assets/images/semas.png');
        $logoCENAD = self::imgBase64('/assets/images/cenad.jpeg');

        $mapaImg = self::imgBase64($mapa, self::MAP_MAX_WIDTH, self::MAP_MAX_HEIGHT, 'png');
        if ($mapaImg === '') {
            $mapaImg = self::imgBase64('/assets/images/mapa_indisponivel.png', self::MAP_MAX_WIDTH, self::MAP_MAX_HEIGHT, 'png');
        }

        $infoImg = !empty($alerta['informacoes'])
            ? self::imgBase64(
                (string) $alerta['informacoes'],
                self::INFO_MAX_WIDTH,
                self::INFO_MAX_HEIGHT,
                'jpeg',
                self::INFO_JPEG_QUALITY
            )
            : '';

        $gravidadeCor = match ((string) ($alerta['nivel_gravidade'] ?? '')) {
            'BAIXO' => '#CCC9C7',
            'MODERADO' => '#FFE000',
            'ALTO' => '#FF7B00',
            'EXTREMO' => '#7A28C6',
            'MUITO ALTO' => '#FF1D08',
            default => '#444444',
        };

        $dataAlerta = TimeHelper::formatDate($alerta['data_alerta'] ?? null);
        $inicioVigencia = TimeHelper::formatDateTime($alerta['inicio_alerta'] ?? null);
        $fimVigencia = TimeHelper::formatDateTime($alerta['fim_alerta'] ?? null);

        $status = self::statusAlerta($alerta);

        $municipiosHtml = '';
        foreach ($regioes as $regiao => $municipios) {
            $municipiosHtml .= "
                <div class=\"regiao\">
                    <strong>" . htmlspecialchars((string) $regiao, ENT_QUOTES, 'UTF-8') . "</strong><br>
                    " . htmlspecialchars(implode(', ', $municipios), ENT_QUOTES, 'UTF-8') . "
                </div>
            ";
        }

        $numero = htmlspecialchars((string) ($alerta['numero'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $evento = htmlspecialchars((string) ($alerta['tipo_evento'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $gravidade = htmlspecialchars((string) ($alerta['nivel_gravidade'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $fonte = htmlspecialchars((string) ($alerta['fonte'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $riscos = nl2br(htmlspecialchars((string) ($alerta['riscos'] ?? '-'), ENT_QUOTES, 'UTF-8'));
        $recomendacoes = nl2br(htmlspecialchars((string) ($alerta['recomendacoes'] ?? '-'), ENT_QUOTES, 'UTF-8'));

        $infoBloco = '';
        if ($infoImg !== '') {
            $infoBloco = '
<strong>Informacoes</strong><br>
<div class="imagem-info">
    <img src="' . $infoImg . '" alt="Imagem informativa do alerta">
</div><br>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
@page {
    margin: 120px 40px 120px 40px;
}

body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 12px;
    color: #333333;
    text-align: justify;
}

.header {
    position: fixed;
    top: -100px;
    left: 0;
    right: 0;
    border-bottom: 3px solid #0b3c68;
}

.header table {
    width: 100%;
}

.header img {
    height: 55px;
}

.footer {
    position: fixed;
    bottom: -100px;
    left: 0;
    right: 0;
    border-top: 2px solid #cccccc;
    font-size: 10px;
}

.footer-logos {
    text-align: center;
    margin-bottom: 4px;
}

.footer-logos img {
    height: 26px;
    margin: 10px 0 6px 0;
}

.carimbo {
    position: absolute;
    top: 50px;
    right: 40px;
    padding: 8px 14px;
    border: 3px solid;
    border-radius: 8px;
    font-size: 14px;
    font-weight: bold;
    transform: rotate(-8deg);
}

.mapa,
.imagem-info {
    margin-top: 12px;
    text-align: center;
}

.mapa img {
    display: inline-block;
    max-width: 100%;
    max-height: 420px;
    width: auto;
    height: auto;
    border: 1px solid #bbbbbb;
}

.imagem-info img {
    display: inline-block;
    max-width: 100%;
    max-height: 360px;
    width: auto;
    height: auto;
    border: 1px solid #bbbbbb;
    border-radius: 8px;
}

.regiao {
    margin-bottom: 10px;
    line-height: 1.4;
}

.legenda {
    margin-top: 24px;
    font-size: 11px;
}

.legenda-item {
    margin-bottom: 4px;
}

.legenda-cor {
    display: inline-block;
    width: 12px;
    height: 12px;
    margin-right: 6px;
    vertical-align: middle;
    border: 1px solid #555555;
}
</style>
</head>

<body>

<div class="header">
<table>
<tr>
<td width="25%"><img src="{$logoGov}"></td>
<td width="50%" align="center">
    <strong>ALERTA N&#186; {$numero}</strong><br>
    Corpo de Bombeiros Militar do Para<br>
    Coordenadoria Estadual de Protecao e Defesa Civil<br>
    Divisao de Gestao de Risco - DGR
</td>
<td width="25%" align="right"><img src="{$logoCedec}"></td>
</tr>
</table>
</div>

<div class="carimbo" style="color: {$status['cor']}; border-color: {$status['cor']}">
{$status['texto']}
</div>

<strong>Evento:</strong> {$evento}<br>
<strong>Gravidade:</strong> <span style="background:{$gravidadeCor};padding:4px;border-radius:8px;">{$gravidade}</span><br>
<strong>Data do Alerta:</strong> {$dataAlerta}<br>
<strong>Vigencia:</strong> {$inicioVigencia} ate {$fimVigencia}

<div class="mapa">
    <img src="{$mapaImg}" alt="Mapa do alerta">
</div>

<div class="legenda">
    <strong>Legenda:</strong><br>

    <div class="legenda-item">
        <span class="legenda-cor" style="background:#CCC9C7;"></span>
        Alerta de baixa gravidade
    </div>

    <div class="legenda-item">
        <span class="legenda-cor" style="background:#FFE000;"></span>
        Alerta de gravidade moderada
    </div>

    <div class="legenda-item">
        <span class="legenda-cor" style="background:#FF7B00;"></span>
        Alerta de alta gravidade
    </div>

    <div class="legenda-item">
        <span class="legenda-cor" style="background:#FF1D08;"></span>
        Alerta de gravidade muito alta
    </div>

    <div class="legenda-item">
        <span class="legenda-cor" style="background:#7A28C6;"></span>
        Alerta de gravidade extrema
    </div>
</div>

<br><strong>Riscos Potenciais</strong><br>
{$riscos}<br><br>

<strong>Recomendacoes</strong><br>
{$recomendacoes}<br><br>

{$infoBloco}

<strong>Municipios afetados (por regiao)</strong><br>
{$municipiosHtml}

<div class="footer">
<div class="footer-logos">
    <img src="{$logoCedec}">
    <img src="{$logoSIMD}">
    <img src="{$logoSEM}">
    <img src="{$logoANA}">
    <img src="{$logoCEN}">
    <img src="{$logoCEM}">
    <img src="{$logoINMET}">
    <img src="{$logoSGB}">
    <img src="{$logoCENAD}">
</div>
<div style="text-align:center">
    Subdivisao de Informacoes de Monitoramento de Desastres - SIMD<br>
    Fonte: {$fonte}
</div>
</div>

</body>
</html>
HTML;
    }
}
