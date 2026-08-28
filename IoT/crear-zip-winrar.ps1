$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$projectName = Split-Path -Leaf $projectRoot
$destination = Split-Path -Parent $projectRoot
$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$archivePath = Join-Path $destination "$projectName-$timestamp.zip"

$winRarCandidates = @(
    (Join-Path $env:ProgramFiles 'WinRAR\WinRAR.exe'),
    (Join-Path ${env:ProgramFiles(x86)} 'WinRAR\WinRAR.exe')
) | Where-Object { $_ -and (Test-Path -LiteralPath $_) }

if ($winRarCandidates.Count -eq 0) {
    throw 'No se encontro WinRAR en Program Files. Instala WinRAR o ajusta su ruta en crear-zip-winrar.ps1.'
}

$winRar = $winRarCandidates[0]
$source = Join-Path $projectRoot '*'
$exclusions = @(
    (Join-Path $projectRoot '.git\*'),
    (Join-Path $projectRoot '.preview\*'),
    (Join-Path $projectRoot 'mobile\node_modules\*'),
    (Join-Path $projectRoot 'mobile\.expo\*'),
    (Join-Path $projectRoot 'mobile\.expo-export-check\*'),
    (Join-Path $projectRoot 'mobile\expo-start.log'),
    (Join-Path $projectRoot 'mobile\expo-start-error.log'),
    (Join-Path $projectRoot 'mobile\.env'),
    (Join-Path $projectRoot 'mobile\.env.local'),
    (Join-Path $projectRoot 'mobile\.env.production'),
    (Join-Path $projectRoot 'api\config.local.php'),
    (Join-Path $projectRoot 'backend-cpanel\api\config.local.php'),
    (Join-Path $projectRoot 'api\error_log'),
    (Join-Path $projectRoot 'backend-cpanel\api\error_log'),
    (Join-Path $projectRoot '*.zip')
)

$arguments = @(
    'a',
    '-afzip',
    '-r',
    '-ep1',
    '-idq',
    $archivePath,
    $source
)

$arguments += $exclusions | ForEach-Object { "-x$_" }

Write-Host "Creando respaldo con WinRAR..." -ForegroundColor Cyan
Write-Host "Origen:  $projectRoot"
Write-Host "Destino: $archivePath"

& $winRar @arguments
if ($LASTEXITCODE -ne 0) {
    throw "WinRAR termino con el codigo $LASTEXITCODE."
}

if (-not (Test-Path -LiteralPath $archivePath)) {
    throw 'WinRAR no genero el archivo esperado.'
}

$archive = Get-Item -LiteralPath $archivePath
$sizeMb = [Math]::Round($archive.Length / 1MB, 2)
Write-Host "ZIP creado correctamente: $($archive.FullName) ($sizeMb MB)" -ForegroundColor Green

