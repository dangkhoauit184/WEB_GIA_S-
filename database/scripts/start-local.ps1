# Start/reuse local Docker MySQL; does not initialize, migrate, seed, or delete data.
[CmdletBinding()]
param([string]$Container='educenter-mysql', [string]$Volume='educenter_mysql_data',
      [ValidateRange(1,65535)][int]$Port=3307, [string]$RootEnvFile='')
$ErrorActionPreference='Stop'
$root=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
foreach ($name in @($Container,$Volume)) {
    if ($name -notmatch '^[a-zA-Z0-9][a-zA-Z0-9_.-]*$') { throw 'Tên container/volume không hợp lệ.' }
}
function Invoke-Docker {
    & docker @args
    if ($LASTEXITCODE -ne 0) { throw 'Docker thất bại; xem thông báo phía trên.' }
}
Push-Location $root
try {
    & docker info --format '{{.ServerVersion}}' 2>$null | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Mở Docker Desktop và đợi Engine running.' }
    $state=& docker inspect --format '{{.State.Status}}' $Container 2>$null
    if ($LASTEXITCODE -eq 0) {
        if ($state -ne 'running') { Invoke-Docker start $Container }
    } else {
        & docker volume inspect $Volume 2>$null | Out-Null
        if ($LASTEXITCODE -eq 0) { throw 'Volume đã tồn tại nhưng container không còn. Giữ nguyên volume, khôi phục kết nối theo docs/huong-dan-cai-dat-va-su-dung.md; không tự khởi tạo hoặc đổi mật khẩu.' }
        $secretFile=if ($RootEnvFile) { [IO.Path]::GetFullPath($RootEnvFile) } else { Join-Path $root 'config/mysql.env' }
        if (!(Test-Path -LiteralPath $secretFile)) {
            $bytes=New-Object byte[] 32
            $rng=[Security.Cryptography.RandomNumberGenerator]::Create()
            try { $rng.GetBytes($bytes) } finally { $rng.Dispose() }
            $password=([BitConverter]::ToString($bytes)).Replace('-','').ToLowerInvariant()
            [IO.File]::WriteAllText($secretFile,"MYSQL_ROOT_PASSWORD=$password`n",[Text.UTF8Encoding]::new($false))
            $password=$null
        }
        $settings=[IO.File]::ReadAllLines($secretFile)
        if (!($settings | Where-Object { $_ -match '^MYSQL_ROOT_PASSWORD=.{16,}$' })) { throw 'config/mysql.env cần MYSQL_ROOT_PASSWORD riêng tối thiểu16 ký tự.' }
        Invoke-Docker run -d --name $Container --restart unless-stopped --env-file $secretFile `
          -p "127.0.0.1:${Port}:3306" --mount "type=volume,source=$Volume,target=/var/lib/mysql" `
          --mount "type=bind,source=$root\database,target=/sql,readonly" mysql:8.4 `
          --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci --default-time-zone=+07:00
    }
    & docker network inspect educenter-erp 2>$null | Out-Null
    if ($LASTEXITCODE -ne 0) { Invoke-Docker network create educenter-erp }
    $networks=& docker inspect --format '{{json .NetworkSettings.Networks}}' $Container | ConvertFrom-Json
    if ($networks.PSObject.Properties.Name -notcontains 'educenter-erp') { Invoke-Docker network connect educenter-erp $Container }
    $ready=$false
    for ($attempt=0;$attempt -lt 60;$attempt++) {
        & docker exec $Container sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --host=127.0.0.1 --protocol=TCP --user=root --execute="SELECT 1"' 2>$null | Out-Null
        if ($LASTEXITCODE -eq 0) { $ready=$true; break }
        Start-Sleep -Seconds 1
    }
    if (!$ready) { throw 'MySQL chưa sẵn sàng; kiểm tra Docker logs. Không xóa volume để thử lại.' }
    Write-Host "PASS: MySQL $Container đang chạy; dữ liệu được giữ trong $Volume. Chưa cài/migrate schema." -ForegroundColor Green
} finally { Pop-Location }
