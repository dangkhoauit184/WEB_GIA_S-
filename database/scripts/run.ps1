# [THÊM] Công cụ triển khai Database, không phải API hay code Frontend/Backend.
# [LƯU Ý] Không dùng --force, không xóa database, không tự sửa dữ liệu sai để migration chạy tiếp.
[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)]
    [ValidateSet('init','preflight','migrate','seed-demo','check','test')][string]$Action,
    [string]$EnvFile = (Join-Path $PSScriptRoot '../.env'),
    [string]$Container = ''
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
if ($Container) {
    if ($Container -notmatch '^[a-zA-Z0-9][a-zA-Z0-9_.-]*$') { throw 'Tên container không hợp lệ.' }
    foreach ($entry in @(@('DB_PORT','3306'),@('DB_CHARSET','utf8mb4'),@('DB_TIMEZONE','+07:00'))) {
        if (!$settings.ContainsKey($entry[0])) { $settings[$entry[0]]=$entry[1] }
    }
}
$required = if ($Container) { @('DB_NAME','DB_PORT','DB_CHARSET','DB_TIMEZONE') } else { @('DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASSWORD','DB_CHARSET','DB_TIMEZONE','MYSQL_BIN') }
foreach ($key in $required) {
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
if (!$Container) { [IO.File]::WriteAllLines($optionPath, $lines, $utf8) }
function Invoke-Sql([string]$Sql, [switch]$NoDatabase) {
    $mysqlArgs = @("--defaults-extra-file=$optionPath",'--batch','--raw','--skip-column-names','--default-character-set=utf8mb4',
        "--init-command=SET SESSION time_zone='+07:00', SESSION transaction_isolation='READ-COMMITTED'")
    if (!$NoDatabase) { $mysqlArgs += '--database=' + $settings.DB_NAME }
    # [THÊM] Chuyển UTF-8 qua stdin bằng Process API, không phụ thuộc codepage Windows/PowerShell.
    $start = [Diagnostics.ProcessStartInfo]::new()
    $start.FileName = $settings.MYSQL_BIN
    if ($Container) {
        # [THÊM] Docker local: mật khẩu root chỉ được đọc bên trong container, không đưa vào args/log Windows.
        $start.FileName = 'docker'
        $inside = 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --user=root --batch --raw --skip-column-names --default-character-set=utf8mb4 --init-command="SET SESSION time_zone=''+07:00'', SESSION transaction_isolation=''READ-COMMITTED''"'
        if (!$NoDatabase) { $inside += ' --database=' + $settings.DB_NAME }
        $mysqlArgs = @('exec','-i',$Container,'sh','-c',$inside)
    }
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
    # [SỬA] Client có thể thoát ngay ở DDL đầu tiên; vẫn thu stderr thay vì che lỗi SQL bằng broken pipe.
    $inputError = $null
    try { $process.StandardInput.Write($Sql) } catch { $inputError = $_ }
    try { $process.StandardInput.Close() } catch { if (!$inputError) { $inputError = $_ } }
    $process.WaitForExit()
    $output = $outTask.GetAwaiter().GetResult()
    $errOutput = $errTask.GetAwaiter().GetResult()
    $exitCode = $process.ExitCode
    $process.Dispose()
    if ($exitCode -ne 0) { throw "MySQL thất bại ($exitCode): $errOutput" }
    if ($inputError) { throw "Không gửi đủ SQL đến MySQL: $inputError" }
    if ($errOutput.Trim()) { Write-Warning $errOutput.Trim() }
    return $output.Trim()
}
function Read-Sql([string]$Name) { return [IO.File]::ReadAllText((Join-Path $dbRoot $Name),$utf8) }
# [SỬA] Một manifest dùng chung cho mọi đường triển khai, không duy trì file SQL gộp thứ hai.
function Read-Modules {
    $order = Get-Content -LiteralPath (Join-Path $dbRoot 'install-order.json') -Raw | ConvertFrom-Json
    $parts = foreach ($name in $order) {
        $resolved = [IO.Path]::GetFullPath((Join-Path $dbRoot $name))
        if (!$resolved.StartsWith($dbRoot + [IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)) {
            throw 'Đường dẫn module SQL nằm ngoài database/.'
        }
        Read-Sql $name
    }
    return $parts -join "`n"
}
function Assert-Report([string]$Report) {
    foreach ($row in ($Report -split "`r?`n")) {
        $cells = $row -split "`t"
        if ($cells.Count -eq 3 -and $cells[0] -eq 'VI_PHAM' -and $cells[2] -ne '0') {
            throw 'Database có vi phạm; xem báo cáo, không tự sửa số liệu.'
        }
    }
}
try {
    $version = Invoke-Sql 'SELECT VERSION();' -NoDatabase
    if ($version -match 'MariaDB' -or $version -notmatch '^8\.(0|4)\.') { throw 'Chỉ hỗ trợ MySQL 8.0.16+ hoặc 8.4 LTS; không tự coi phiên bản/hệ quản trị khác tương đương.' }
    if ($version -match '^8\.0\.(\d+)' -and [int]$Matches[1] -lt 16) { throw 'MySQL trước 8.0.16 không thực thi CHECK đầy đủ.' }
    switch ($Action) {
        'init' {
            $db = $settings.DB_NAME
            [void](Invoke-Sql "CREATE DATABASE IF NOT EXISTS ``$db`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" -NoDatabase)
            $tables = Invoke-Sql 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE();'
            if ($tables -ne '0') { throw 'Init chỉ dành cho database rỗng. Database cũ dùng preflight/migrate.' }
            # [SỬA] Schema mới đã có cấu trúc 005; chỉ ghi lịch sử, không ALTER lại.
            $sql = (Read-Sql 'schema.sql') + "`nINSERT INTO schema_migrations(version,status) VALUES('001_feature_based','applying'),('002_routine_review','applying'),('003_attribute_review','applying'),('004_business_rules','applying'),('005_operations_ready','applying');`n" +
                (Read-Modules) + "`n" + (Read-Sql 'seeders/001_reference.sql') + "`n" + (Read-Sql 'seeders/004_business_policy.sql') + "`n" + (Read-Sql 'seeders/005_operations_policy.sql') +
                "`nUPDATE schema_migrations SET status='applied',applied_at=NOW() WHERE status='applying' AND version IN ('001_feature_based','002_routine_review','003_attribute_review','004_business_rules','005_operations_ready');"
            [void](Invoke-Sql $sql)
        }
        'preflight' {
            $hasHistory = Invoke-Sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='schema_migrations';"
            if ($hasHistory -eq '0') {
                $issues = Invoke-Sql (Read-Sql 'migrations/preflight.sql')
                if ($issues) { throw "Dừng nâng cấp. Các vấn đề dữ liệu:`n$issues" }
                $report = Invoke-Sql (Read-Sql 'migrations/003_preflight.sql')
                Write-Output $report
                Assert-Report $report
            } else {
                $latest = Invoke-Sql "SELECT COUNT(*) FROM schema_migrations WHERE version='003_attribute_review' AND status='applied';"
                $has004 = Invoke-Sql "SELECT COUNT(*) FROM schema_migrations WHERE version='004_business_rules' AND status='applied';"
                $has005 = Invoke-Sql "SELECT COUNT(*) FROM schema_migrations WHERE version='005_operations_ready' AND status='applied';"
                $reportFile = if ($has005 -eq '1') { 'checks.sql' } elseif ($has004 -eq '1') { 'migrations/005_preflight.sql' } elseif ($latest -eq '1') { 'migrations/004_preflight.sql' } else { 'migrations/003_preflight.sql' }
                $report = Invoke-Sql (Read-Sql $reportFile)
                Write-Output $report
                Assert-Report $report
            }
            Write-Output 'Không phát hiện vi phạm trong các kiểm tra preflight; tiếp tục đối chiếu cấu trúc và dữ liệu lịch sử theo README.'
        }
        'migrate' {
            $hasHistory = Invoke-Sql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='schema_migrations';"
            $sql = ''
            $has003 = '0'
            $has004 = '0'
            if ($hasHistory -eq '0') {
                $issues = Invoke-Sql (Read-Sql 'migrations/preflight.sql')
                if ($issues) { throw "Dừng nâng cấp. Các vấn đề dữ liệu:`n$issues" }
                $report = Invoke-Sql (Read-Sql 'migrations/003_preflight.sql')
                Write-Output $report
                Assert-Report $report
                $sql = (Read-Sql 'migrations/001_feature_based.sql') + "`n" + (Read-Sql 'migrations/002_routine_review.sql') + "`n"
            } else {
                $dirty = Invoke-Sql "SELECT COUNT(*) FROM schema_migrations WHERE status<>'applied';"
                if ($dirty -ne '0') { throw 'Migration còn applying; kiểm tra DDL đã chạy, không tự tiếp tục.' }
                $baseline = Invoke-Sql "SELECT COUNT(*) FROM schema_migrations WHERE version='001_feature_based' AND status='applied';"
                if ($baseline -ne '1') { throw 'Không nhận diện baseline 001; cần đối chiếu cấu trúc.' }
                $unknown = Invoke-Sql "SELECT COUNT(*) FROM schema_migrations WHERE version NOT IN ('001_feature_based','002_routine_review','003_attribute_review','004_business_rules','005_operations_ready');"
                if ($unknown -ne '0') { throw 'Database có phiên bản mới hơn hoặc tùy chỉnh; không tự hạ phiên bản.' }
                $done = Invoke-Sql "SELECT COUNT(*) FROM schema_migrations WHERE version='005_operations_ready' AND status='applied';"
                if ($done -eq '1') {
                    $report = Invoke-Sql (Read-Sql 'checks.sql')
                    Assert-Report $report
                    Write-Output 'Database đã ở 005; không chạy lại migration.'
                    break
                }
                $has003 = Invoke-Sql "SELECT COUNT(*) FROM schema_migrations WHERE version='003_attribute_review' AND status='applied';"
                $has004 = Invoke-Sql "SELECT COUNT(*) FROM schema_migrations WHERE version='004_business_rules' AND status='applied';"
                $preflightFile = if ($has004 -eq '1') { 'migrations/005_preflight.sql' } elseif ($has003 -eq '1') { 'migrations/004_preflight.sql' } else { 'migrations/003_preflight.sql' }
                $report = Invoke-Sql (Read-Sql $preflightFile)
                Write-Output $report
                Assert-Report $report
                $has002 = Invoke-Sql "SELECT COUNT(*) FROM schema_migrations WHERE version='002_routine_review' AND status='applied';"
                if ($has002 -eq '0') { $sql += (Read-Sql 'migrations/002_routine_review.sql') + "`n" }
            }
            if ($has003 -ne '1') { $sql += (Read-Sql 'migrations/003_attribute_review.sql') + "`n" }
            if ($has004 -ne '1') { $sql += (Read-Sql 'migrations/004_business_rules.sql') + "`n" }
            $sql += (Read-Sql 'migrations/005_operations_ready.sql') + "`n" + (Read-Modules) + "`n" +
                (Read-Sql 'seeders/004_business_policy.sql') + "`n" + (Read-Sql 'seeders/005_operations_policy.sql') +
                "`nUPDATE schema_migrations SET status='applied',applied_at=NOW() WHERE status='applying' AND version IN ('001_feature_based','002_routine_review','003_attribute_review','004_business_rules','005_operations_ready');"
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
            Assert-Report $report
        }
        'test' {
            # [THÊM] Suite workflow có COMMIT riêng; chỉ chạy trên demo dùng một lần.
            if ($settings.DB_NAME -notmatch '_test$') { throw 'Test chỉ chạy trên database có hậu tố _test.' }
            $users = Invoke-Sql 'SELECT COUNT(*) FROM users;'
            if ($users -ne '8') { throw 'Suite yêu cầu đúng demo đã seed, không dùng với dữ liệu thật.' }
            foreach ($file in @('tests/integrity.sql','tests/regressions.sql','tests/attributes.sql','tests/workflows.sql','tests/business_rules.sql','tests/operations_ready.sql')) {
                Write-Output (Invoke-Sql (Read-Sql $file))
            }
        }
    }
    Write-Output "Hoàn tất thao tác Database: $Action."
} finally {
    if (Test-Path -LiteralPath $optionPath) { Remove-Item -LiteralPath $optionPath -Force }
}
