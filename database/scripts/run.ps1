# [THÊM] Công cụ triển khai Database, không phải API hay code Frontend/Backend.
# [LƯU Ý] Không dùng --force, không xóa database, không tự sửa dữ liệu sai để migration chạy tiếp.
[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)]
    [ValidateSet('init','preflight','migrate','seed-demo','check')][string]$Action,
    [string]$EnvFile = (Join-Path $PSScriptRoot '../.env')
)
$ErrorActionPreference = 'Stop'
$dbRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
if (!(Test-Path -LiteralPath $EnvFile)) { throw 'Chưa có database/.env. Sao chép .env.example và điền thông tin kết nối.' }
$settings = @{}
foreach ($line in [IO.File]::ReadAllLines((Resolve-Path -LiteralPath $EnvFile).Path)) {
    if ($line -match '^\s*(#|$)') { continue }
    if ($line -notmatch '^([A-Z_]+)=(.*)$') { throw 'Dòng .env không hợp lệ; dùng KEY=value, không có export hoặc nội suy.' }
    $settings[$Matches[1]] = $Matches[2]
}
foreach ($key in @('DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASSWORD','DB_CHARSET','DB_TIMEZONE','MYSQL_BIN')) {
    if (!$settings.ContainsKey($key)) { throw "Thiếu $key trong .env." }
}
if ($settings.DB_NAME -notmatch '^[a-zA-Z][a-zA-Z0-9_]{0,63}$') { throw 'DB_NAME chỉ gồm chữ ASCII, số, dấu gạch dưới, bắt đầu bằng chữ.' }
if ($settings.DB_PORT -notmatch '^\d+$' -or [int]$settings.DB_PORT -lt 1 -or [int]$settings.DB_PORT -gt 65535) { throw 'DB_PORT không hợp lệ.' }
if ($settings.DB_CHARSET -ne 'utf8mb4' -or $settings.DB_TIMEZONE -ne '+07:00') { throw 'Schema sử dụng utf8mb4 và +07:00.' }
# [LƯU Ý] Mật khẩu không nằm trong command line hoặc log; option file tạm thừa hưởng ACL thư mục người dùng.
$optionPath = Join-Path ([IO.Path]::GetTempPath()) ('educenter-' + [Guid]::NewGuid().ToString('N') + '.cnf')
$utf8 = [Text.UTF8Encoding]::new($false)
function Quote-Option([string]$value) {
    if ($value.Contains("`n") -or $value.Contains("`r")) { throw 'Giá trị .env không được chứa ký tự xuống dòng.' }
    return '"' + $value.Replace('\','\\').Replace('"','\"').Replace("`t",'\t') + '"'
}
$lines = @('[client]', 'protocol=TCP', 'default-character-set=utf8mb4')
foreach ($item in @(@('host','DB_HOST'),@('port','DB_PORT'),@('user','DB_USER'),@('password','DB_PASSWORD'))) {
    $lines += $item[0] + '=' + (Quote-Option $settings[$item[1]])
}
[IO.File]::WriteAllLines($optionPath, $lines, $utf8)
function Invoke-Sql([string]$Sql, [switch]$NoDatabase) {
    $mysqlArgs = @("--defaults-extra-file=$optionPath",'--batch','--raw','--skip-column-names','--default-character-set=utf8mb4',
        "--init-command=SET SESSION time_zone='+07:00', SESSION transaction_isolation='READ-COMMITTED'")
    if (!$NoDatabase) { $mysqlArgs += '--database=' + $settings.DB_NAME }
    # [THÊM] Chuyển UTF-8 qua stdin bằng Process API, không phụ thuộc codepage Windows/PowerShell.
    $start = [Diagnostics.ProcessStartInfo]::new()
    $start.FileName = $settings.MYSQL_BIN
    $start.UseShellExecute = $false
    $start.CreateNoWindow = $true
    $start.RedirectStandardInput = $true
    $start.RedirectStandardOutput = $true
    $start.RedirectStandardError = $true
    $start.StandardInputEncoding = $utf8
    $start.StandardOutputEncoding = $utf8
    $start.StandardErrorEncoding = $utf8
    foreach ($arg in $mysqlArgs) { $start.ArgumentList.Add($arg) }
    $process = [Diagnostics.Process]::new()
    $process.StartInfo = $start
    [void]$process.Start()
    $outTask = $process.StandardOutput.ReadToEndAsync()
    $errTask = $process.StandardError.ReadToEndAsync()
    $process.StandardInput.Write($Sql)
    $process.StandardInput.Close()
    $process.WaitForExit()
    $output = $outTask.GetAwaiter().GetResult()
    $errOutput = $errTask.GetAwaiter().GetResult()
    $exitCode = $process.ExitCode
    $process.Dispose()
    if ($exitCode -ne 0) { throw "MySQL thất bại ($exitCode): $errOutput" }
    if ($errOutput.Trim()) { Write-Warning $errOutput.Trim() }
    return $output.Trim()
}
function Read-Sql([string]$Name) { return [IO.File]::ReadAllText((Join-Path $dbRoot $Name),$utf8) }
try {
    $version = Invoke-Sql 'SELECT VERSION();' -NoDatabase
    if ($version -match 'MariaDB' -or $version -notmatch '^(8\.|9\.)') { throw 'Yêu cầu MySQL 8.0.16+ hoặc 8.4; MariaDB chưa được hỗ trợ.' }
    if ($version -match '^8\.0\.(\d+)' -and [int]$Matches[1] -lt 16) { throw 'MySQL trước 8.0.16 không thực thi CHECK đầy đủ.' }
    switch ($Action) {
        'init' {
            $db = $settings.DB_NAME
            [void](Invoke-Sql "CREATE DATABASE IF NOT EXISTS ``$db`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" -NoDatabase)
            $tables = Invoke-Sql 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE();'
            if ($tables -ne '0') { throw 'Init chỉ dành cho database rỗng. Database cũ dùng preflight/migrate.' }
            $sql = (Read-Sql 'schema.sql') + "`n" + (Read-Sql 'procedures.sql') + "`n" + (Read-Sql 'triggers.sql') + "`n" +
                (Read-Sql 'seeders/001_reference.sql') + "`nINSERT INTO schema_migrations(version,status,applied_at) VALUES('001_feature_based','applied',NOW());"
            [void](Invoke-Sql $sql)
        }
        'preflight' {
            $issues = Invoke-Sql (Read-Sql 'migrations/preflight.sql')
            if ($issues) { throw "Dừng nâng cấp. Các vấn đề dữ liệu:`n$issues" }
            Write-Output 'Không phát hiện vi phạm trong các kiểm tra preflight; tiếp tục đối chiếu cấu trúc và dữ liệu lịch sử theo README.'
        }
        'migrate' {
            $hasHistory = Invoke-Sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='schema_migrations';"
            if ($hasHistory -ne '0') { throw 'Đã có lịch sử migration; kiểm tra trạng thái. Không tự chạy lại migration hoặc tiếp tục DDL dở dang.' }
            $issues = Invoke-Sql (Read-Sql 'migrations/preflight.sql')
            if ($issues) { throw "Dừng nâng cấp. Các vấn đề dữ liệu:`n$issues" }
            $sql = (Read-Sql 'migrations/001_feature_based.sql') + "`n" + (Read-Sql 'procedures.sql') + "`n" + (Read-Sql 'triggers.sql') +
                "`nUPDATE schema_migrations SET status='applied',applied_at=NOW() WHERE version='001_feature_based';"
            [void](Invoke-Sql $sql)
        }
        'seed-demo' {
            # [LƯU Ý] Chỉ cho nạp dữ liệu mẫu vào database thử nghiệm đã init và chưa có người dùng.
            if ($settings.DB_NAME -notmatch '_test$') { throw 'Demo chỉ được chạy khi DB_NAME kết thúc bằng _test.' }
            $users = Invoke-Sql 'SELECT COUNT(*) FROM users;'
            if ($users -ne '0') { throw 'Không nạp demo lên database đã có người dùng.' }
            [void](Invoke-Sql (Read-Sql 'seeders/002_demo.sql'))
        }
        'check' {
            $report = Invoke-Sql (Read-Sql 'checks.sql')
            Write-Output $report
            foreach ($row in ($report -split "`r?`n")) {
                $cells = $row -split "`t"
                if ($cells.Count -eq 3 -and $cells[0] -eq 'VI_PHAM' -and $cells[2] -ne '0') {
                    throw 'Kiểm tra sau triển khai có vi phạm dữ liệu; xem báo cáo, không tự sửa số liệu.'
                }
            }
        }
    }
    Write-Output "Hoàn tất thao tác Database: $Action."
} finally {
    if (Test-Path -LiteralPath $optionPath) { Remove-Item -LiteralPath $optionPath -Force }
}
