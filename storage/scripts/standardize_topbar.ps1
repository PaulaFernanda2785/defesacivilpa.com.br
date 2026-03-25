$ErrorActionPreference = 'Stop'

$pagesRoot = (Resolve-Path 'public_html/pages').Path
$files = Get-ChildItem -Path $pagesRoot -Recurse -Filter '*.php' |
    Where-Object { $_.Name -ne '_topbar.php' }

$headerPattern = '(?s)<header class="topbar">.*?</header>'

foreach ($file in $files) {
    $content = Get-Content -Path $file.FullName -Raw -Encoding UTF8

    if ($content -notmatch '<header class="topbar">') {
        continue
    }

    $relativeDir = $file.Directory.FullName.Substring($pagesRoot.Length).TrimStart('\')
    $includeLine = if ([string]::IsNullOrWhiteSpace($relativeDir)) {
        "<?php include __DIR__ . '/_topbar.php'; ?>"
    } else {
        "<?php include __DIR__ . '/../_topbar.php'; ?>"
    }

    $updated = [regex]::Replace($content, $headerPattern, $includeLine, 1)

    if ($updated -ne $content) {
        Set-Content -Path $file.FullName -Value $updated -Encoding UTF8
    }
}
