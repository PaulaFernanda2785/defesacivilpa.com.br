$styleTargets = @(
    'public_html/pages/alertas/detalhe.php',
    'public_html/pages/alertas/editar.php',
    'public_html/pages/alertas/kml.php',
    'public_html/pages/alertas/listar.php',
    'public_html/pages/alertas/preview_inmet.php',
    'public_html/pages/analises/index.php',
    'public_html/pages/analises/indice_risco.php',
    'public_html/pages/analises/severidade.php',
    'public_html/pages/analises/temporal.php',
    'public_html/pages/analises/tipologia.php',
    'public_html/pages/usuarios/listar.php',
    'public_html/pages/painel.php'
)

$scriptTargets = @(
    'public_html/pages/alertas/cadastrar.php',
    'public_html/pages/alertas/importar_inmet.php',
    'public_html/pages/alertas/listar.php',
    'public_html/pages/historico/index.php',
    'public_html/pages/mapas/mapa_multirriscos.php'
)

$utf8 = New-Object System.Text.UTF8Encoding($false)

foreach ($target in $styleTargets) {
    $content = [System.IO.File]::ReadAllText((Resolve-Path $target), [System.Text.Encoding]::UTF8)
    $match = [regex]::Match($content, '<style>\s*(.*?)\s*</style>', [System.Text.RegularExpressions.RegexOptions]::Singleline)
    if (-not $match.Success) { continue }

    $relativeName = ($target -replace '^public_html/pages/', '' -replace '[\\/]', '-' -replace '\.php$', '.css')
    $assetPath = Join-Path (Resolve-Path 'public_html/assets/css/pages').Path $relativeName
    [System.IO.File]::WriteAllText($assetPath, ($match.Groups[1].Value.Trim() + [Environment]::NewLine), $utf8)

    $linkTag = '<link rel="stylesheet" href="/assets/css/pages/' + $relativeName + '">' + [Environment]::NewLine
    $content = $content.Remove($match.Index, $match.Length).Insert($match.Index, $linkTag)
    [System.IO.File]::WriteAllText((Resolve-Path $target), $content, $utf8)
}

foreach ($target in $scriptTargets) {
    $content = [System.IO.File]::ReadAllText((Resolve-Path $target), [System.Text.Encoding]::UTF8)
    $matches = [regex]::Matches($content, '<script(?:(?!src=)[^>])*?>(.*?)</script>', [System.Text.RegularExpressions.RegexOptions]::Singleline -bor [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)
    if ($matches.Count -eq 0) { continue }

    $scriptBlocks = New-Object System.Collections.Generic.List[string]

    for ($i = $matches.Count - 1; $i -ge 0; $i--) {
        $block = $matches[$i]
        $scriptBlocks.Insert(0, $block.Groups[1].Value.Trim())
        $content = $content.Remove($block.Index, $block.Length)
    }

    $relativeName = ($target -replace '^public_html/pages/', '' -replace '[\\/]', '-' -replace '\.php$', '.js')
    $assetPath = Join-Path (Resolve-Path 'public_html/assets/js/pages').Path $relativeName
    [System.IO.File]::WriteAllText($assetPath, (($scriptBlocks -join ([Environment]::NewLine + [Environment]::NewLine)) + [Environment]::NewLine), $utf8)

    $scriptTag = '<script src="/assets/js/pages/' + $relativeName + '"></script>' + [Environment]::NewLine
    $content = $content -replace '</body>', ($scriptTag + '</body>')
    [System.IO.File]::WriteAllText((Resolve-Path $target), $content, $utf8)
}
