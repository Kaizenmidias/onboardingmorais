$ErrorActionPreference = 'Stop'
$taskProject = Split-Path -Parent $PSScriptRoot
$taskPublic = Join-Path $taskProject 'public'
$taskDist = Join-Path $taskProject 'dist'
$taskStage = Join-Path $taskDist '.functional-stage'
$taskArchive = Join-Path $taskDist 'morais-portal-funcional-0.3.0.zip'

php -l (Join-Path $taskProject 'app/bootstrap.php')
php -l (Join-Path $taskPublic 'api.php')
php -l (Join-Path $taskPublic 'install.php')
php -l (Join-Path $taskPublic 'upload.php')
php -l (Join-Path $taskPublic 'media.php')
node --check (Join-Path $taskPublic 'assets/app.js')

if (Test-Path -LiteralPath $taskStage) { Remove-Item -LiteralPath $taskStage -Recurse -Force }
New-Item -ItemType Directory -Path $taskStage -Force | Out-Null
Copy-Item -Path (Join-Path $taskPublic '*') -Destination $taskStage -Recurse -Force
Copy-Item -LiteralPath (Join-Path $taskProject 'app') -Destination (Join-Path $taskStage 'app') -Recurse -Force
Copy-Item -LiteralPath (Join-Path $taskProject 'database') -Destination (Join-Path $taskStage 'database') -Recurse -Force
New-Item -ItemType Directory -Path (Join-Path $taskStage 'config') -Force | Out-Null
Copy-Item -LiteralPath (Join-Path $taskProject 'config/config.example.php') -Destination (Join-Path $taskStage 'config/config.example.php') -Force
New-Item -ItemType Directory -Path (Join-Path $taskStage 'storage/uploads') -Force | Out-Null
Set-Content -LiteralPath (Join-Path $taskStage 'storage/.htaccess') -Value "Require all denied`nDeny from all" -Encoding ASCII
Compress-Archive -Path (Join-Path $taskStage '*') -DestinationPath $taskArchive -Force
Remove-Item -LiteralPath $taskStage -Recurse -Force
Get-FileHash -LiteralPath $taskArchive -Algorithm SHA256
