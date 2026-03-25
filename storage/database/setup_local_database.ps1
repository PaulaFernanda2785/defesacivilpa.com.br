param(
    [string]$MysqlExe = "D:\wamp64\bin\mysql\mysql8.4.7\bin\mysql.exe",
    [string]$DumpPath = "D:\wamp64\www\defesacivilpa.com.br\public_html\banco_db\u696029111_DefesaCivilPA.sql",
    [string]$DatabaseName = "u696029111_DefesaCivilPA",
    [string]$User = "",
    [string]$Password = "",
    [string]$TempDirectory = "D:\wamp64\www\defesacivilpa.com.br\storage\database"
)

if (-not (Test-Path $MysqlExe)) {
    throw "Nao encontrei o mysql.exe em $MysqlExe"
}

if (-not (Test-Path $DumpPath)) {
    throw "Nao encontrei o dump em $DumpPath"
}

if ([string]::IsNullOrWhiteSpace($User)) {
    throw "Informe o usuario MySQL via -User."
}

if ([string]::IsNullOrWhiteSpace($Password)) {
    throw "Informe a senha MySQL via -Password."
}

if (-not (Test-Path $TempDirectory)) {
    New-Item -ItemType Directory -Path $TempDirectory -Force | Out-Null
}

$normalizedDumpPath = Join-Path $TempDirectory (
    "{0}.normalized.sql" -f [System.IO.Path]::GetFileNameWithoutExtension($DumpPath)
)

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$reader = [System.IO.StreamReader]::new($DumpPath, [System.Text.Encoding]::UTF8, $true)
$writer = [System.IO.StreamWriter]::new($normalizedDumpPath, $false, $utf8NoBom)

try {
    while (($line = $reader.ReadLine()) -ne $null) {
        $normalizedLine = $line -replace 'utf8mb4_uca1400_ai_ci', 'utf8mb4_unicode_ci'
        $writer.WriteLine($normalizedLine)
    }
} finally {
    $reader.Dispose()
    $writer.Dispose()
}

$mysqlArgs = @(
    "--default-character-set=utf8mb4",
    "-u", $User,
    "-p$Password"
)

& $MysqlExe @mysqlArgs -e "DROP DATABASE IF EXISTS $DatabaseName; CREATE DATABASE $DatabaseName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

if ($LASTEXITCODE -ne 0) {
    throw "Falha ao recriar o banco $DatabaseName."
}

$command = "`"$MysqlExe`" --default-character-set=utf8mb4 -u $User -p$Password $DatabaseName < `"$normalizedDumpPath`""
cmd.exe /c $command

if ($LASTEXITCODE -ne 0) {
    throw "Falha ao importar o dump normalizado em $DatabaseName."
}

Write-Output "Banco $DatabaseName restaurado com sucesso a partir de $normalizedDumpPath"
