$ErrorActionPreference = 'Stop'
$taskProject = Split-Path -Parent $PSScriptRoot
$taskPublic = Join-Path $taskProject 'public'
$taskDist = Join-Path $taskProject 'dist'
$taskArchive = Join-Path $taskDist 'morais-previa-visual-0.2.0.zip'
& php -l (Join-Path $taskPublic 'index.php')
if ($LASTEXITCODE -ne 0) { throw 'A verificação do PHP falhou.' }
foreach ($taskScript in @('app.js', 'data.js', 'academy.js')) {
    & node --check (Join-Path $taskPublic "assets/$taskScript")
    if ($LASTEXITCODE -ne 0) { throw "A verificação de $taskScript falhou." }
}
New-Item -ItemType Directory -Path $taskDist -Force | Out-Null
Compress-Archive -LiteralPath (Join-Path $taskPublic 'index.php'),(Join-Path $taskPublic 'robots.txt'),(Join-Path $taskPublic 'assets') -DestinationPath $taskArchive -Force
Get-Item -LiteralPath $taskArchive | Select-Object FullName, Length
Get-FileHash -LiteralPath $taskArchive -Algorithm SHA256
