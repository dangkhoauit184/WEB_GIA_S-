[CmdletBinding()]
param([ValidateRange(1,65535)][int]$Port = 5000, [string]$DatabaseContainer = 'educenter-mysql')
$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
function Invoke-Docker {
    & docker @args
    if ($LASTEXITCODE -ne 0) { throw 'Docker thất bại. Xem thông báo ngay phía trên.' }
}
Push-Location $projectRoot
try {
    if (!(Test-Path -LiteralPath 'config/server.env')) {
        throw 'Sao chép config/server.env.example thành config/server.env, điền DB_PASSWORD và JWT_SECRET trước.'
    }
    & docker info --format '{{.ServerVersion}}' 2>$null
    if ($LASTEXITCODE -ne 0) { throw 'Hãy mở Docker Desktop và chờ Engine running.' }
    $state = & docker inspect --format '{{.State.Status}}' $DatabaseContainer 2>$null
    if ($LASTEXITCODE -ne 0) { throw "Chưa có container $DatabaseContainer. Xem database/README.md trước." }
    if ($state -ne 'running') { Invoke-Docker start $DatabaseContainer }
    & docker network inspect educenter-erp --format '{{.Name}}' 2>$null | Out-Null
    if ($LASTEXITCODE -ne 0) { Invoke-Docker network create educenter-erp }
    $networks = & docker inspect --format '{{json .NetworkSettings.Networks}}' $DatabaseContainer | ConvertFrom-Json
    if ($networks.PSObject.Properties.Name -notcontains 'educenter-erp') {
        Invoke-Docker network connect educenter-erp $DatabaseContainer
    }
    $env:EDUCENTER_HTTP_PORT = [string]$Port
    $env:EDUCENTER_DB_CONTAINER = $DatabaseContainer
    Invoke-Docker compose build backend
    Invoke-Docker compose run --rm --no-deps backend composer install --no-interaction --prefer-dist
    Invoke-Docker compose --profile tools up -d backend mailpit
    $healthy = $false
    for ($attempt=0; $attempt -lt 15; $attempt++) {
        try {
            $health = Invoke-RestMethod "http://127.0.0.1:$Port/api/health"
            if ($health.success) { $healthy=$true; break }
        } catch { Start-Sleep -Seconds 2 }
    }
    if (!$healthy) { throw 'API chưa sẵn sàng. Chạy docker compose logs --tail 50 backend để kiểm tra.' }
    Write-Host "Đã chạy PHP / CodeIgniter: http://127.0.0.1:$Port" -ForegroundColor Green
} finally { Pop-Location }
