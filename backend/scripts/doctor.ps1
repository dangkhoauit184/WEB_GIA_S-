[CmdletBinding()]
param([ValidateRange(1,65535)][int]$Port=5000)
$ErrorActionPreference='Stop'
$root=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
Push-Location $root
try {
    & docker compose exec -T --user www-data backend php scripts/doctor.php
    if ($LASTEXITCODE -ne 0) { throw 'Server chưa sẵn sàng; xem mục FAIL phía trên.' }
    $response=Invoke-WebRequest "http://127.0.0.1:$Port/api/health"
    if (!(($response.Content | ConvertFrom-Json).success)) { throw 'HTTP health thất bại.' }
    if ($response.Headers['X-Powered-By']) { throw 'HTTP đang lộ phiên bản PHP.' }
    if ($response.Headers['X-Content-Type-Options'] -ne 'nosniff') { throw 'Thiếu cấu hình HTTP headers.' }
    Write-Host 'PASS: HTTP health, Apache headers, PHP, MySQL, SMTP local.' -ForegroundColor Green
} finally { Pop-Location }
