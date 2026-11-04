param([int]$Port = 8085)
$ErrorActionPreference = 'Stop'
$taskProject = Split-Path -Parent $PSScriptRoot
$taskPublic = Join-Path $taskProject 'public'
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    throw 'PHP não encontrado. Use PHP 8.4 local ou publique o pacote no aaPanel.'
}
Write-Host "Prévia local: http://127.0.0.1:$Port/"
Write-Host 'Use Ctrl+C para encerrar. Esta é uma demonstração com dados fictícios.'
& php -S "127.0.0.1:$Port" -t $taskPublic
exit $LASTEXITCODE
