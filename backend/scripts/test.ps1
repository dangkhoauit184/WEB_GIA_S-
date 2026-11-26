# Requires PowerShell 7. Uses an isolated MySQL container, never the developer database.
[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$testTag = [Guid]::NewGuid().ToString('N').Substring(0,12)
$testContainer = "educenter-test-$testTag"
$testNetwork = "educenter-test-$testTag"
$testPassword = [Guid]::NewGuid().ToString('N')
$testJwt = [Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')
$testDatabase = 'educenter_ci_test'
function Invoke-Docker {
    & docker @args
    if ($LASTEXITCODE -ne 0) { throw 'Docker thất bại. Xem thông báo phía trên.' }
}
function Invoke-TestSql([string]$Sql) {
    $start = [Diagnostics.ProcessStartInfo]::new()
    $start.FileName = 'docker'
    $start.UseShellExecute = $false
    $start.CreateNoWindow = $true
    $start.RedirectStandardInput = $true
    $start.RedirectStandardOutput = $true
    $start.RedirectStandardError = $true
    $start.StandardInputEncoding = [Text.UTF8Encoding]::new($false)
    $start.StandardOutputEncoding = [Text.UTF8Encoding]::new($false)
    $start.StandardErrorEncoding = [Text.UTF8Encoding]::new($false)
    foreach ($arg in @('exec','-i','-e',"MYSQL_PWD=$testPassword",$testContainer,
        'mysql','--user=root','--batch','--raw','--skip-column-names','--default-character-set=utf8mb4',
        "--init-command=SET SESSION time_zone='+07:00', SESSION transaction_isolation='READ-COMMITTED'", "--database=$testDatabase")) {
        $start.ArgumentList.Add($arg)
    }
    $process=[Diagnostics.Process]::new(); $process.StartInfo=$start
    [void]$process.Start()
    $outTask=$process.StandardOutput.ReadToEndAsync(); $errTask=$process.StandardError.ReadToEndAsync()
    try { $process.StandardInput.Write($Sql) } finally { $process.StandardInput.Close() }
    $process.WaitForExit()
    $output=$outTask.GetAwaiter().GetResult(); $errorText=$errTask.GetAwaiter().GetResult()
    $code=$process.ExitCode; $process.Dispose()
    if($code -ne 0) { throw "SQL kiểm thử thất bại: $errorText" }
    return $output.Trim()
}
function Invoke-PhpTest([string]$Script) {
    Invoke-Docker run --rm --network $testNetwork `
        -e DB_HOST=$testContainer -e DB_PORT=3306 -e DB_NAME=$testDatabase `
        -e DB_USER=root -e DB_PASSWORD=$testPassword -e TEST_WITH_DB=1 -e JWT_SECRET=$testJwt -e COMPOSER_PROCESS_TIMEOUT=1200 -e PAYOS_ENABLED=false `
        --mount "type=bind,source=$projectRoot,target=/var/www/educenter" `
        educenter-php-runtime:local composer $Script
}
Push-Location $projectRoot
try {
    Invoke-Docker build --target runtime -t educenter-php-runtime:local -f backend/Dockerfile .
    # Dependency install does not need a configured developer config/server.env.
    Invoke-Docker run --rm --mount "type=bind,source=$projectRoot,target=/var/www/educenter" `
        educenter-php-runtime:local composer install --no-interaction --prefer-dist
    Invoke-Docker network create $testNetwork
    Invoke-Docker run -d --name $testContainer --network $testNetwork `
        -e MYSQL_ROOT_PASSWORD=$testPassword -e MYSQL_DATABASE=$testDatabase mysql:8.4 `
        --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci --default-time-zone=+07:00
    $ready=$false
    for($attempt=0;$attempt -lt 60;$attempt++) {
        & docker exec -e "MYSQL_PWD=$testPassword" $testContainer mysql --host=127.0.0.1 --protocol=TCP --user=root --batch --execute 'SELECT 1' 2>$null | Out-Null
        if($LASTEXITCODE -eq 0) { $ready=$true; break }
        Start-Sleep -Seconds 2
    }
    if(!$ready) { throw 'MySQL kiểm thử chưa sẵn sàng.' }
    $sql=[IO.File]::ReadAllText((Join-Path $projectRoot 'database/schema.sql'))
    $order=Get-Content database/install-order.json -Raw | ConvertFrom-Json
    foreach($file in $order) { $sql+="`n"+[IO.File]::ReadAllText((Join-Path $projectRoot "database/$file")) }
    $sql+="`n"+[IO.File]::ReadAllText((Join-Path $projectRoot 'database/seeders/001_reference.sql'))
    $sql+="`n"+[IO.File]::ReadAllText((Join-Path $projectRoot 'database/seeders/004_business_policy.sql'))
    $sql+="`n"+[IO.File]::ReadAllText((Join-Path $projectRoot 'database/seeders/005_operations_policy.sql'))
    $sql+="`nINSERT INTO schema_migrations(version,status,applied_at) VALUES ('001_feature_based','applied',NOW()),('002_routine_review','applied',NOW()),('003_attribute_review','applied',NOW()),('004_business_rules','applied',NOW()),('005_operations_ready','applied',NOW()),('006_shift_recruitment','applied',NOW()),('007_email_queue_index','applied',NOW()),('008_security_perf','applied',NOW());"
    $baselineSql=$sql
    [void](Invoke-TestSql $sql)
    Invoke-PhpTest 'test'
    Invoke-PhpTest 'test:db'
    Invoke-PhpTest 'test:admin'
    # The existing SQL regression suite expects demo records. Run it on a second clean database.
    $testDatabase='educenter_sql_test'
    & docker exec -e "MYSQL_PWD=$testPassword" $testContainer mysql --user=root --execute "CREATE DATABASE $testDatabase CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    if($LASTEXITCODE -ne 0) { throw 'Không tạo được database SQL test.' }
    $sql+="`n"+[IO.File]::ReadAllText((Join-Path $projectRoot 'database/seeders/002_demo.sql'))
    [void](Invoke-TestSql $sql)
    foreach($file in @('integrity.sql','regressions.sql','attributes.sql','workflows.sql','business_rules.sql','operations_ready.sql')) {
        $report=Invoke-TestSql ([IO.File]::ReadAllText((Join-Path $projectRoot "database/tests/$file")))
        if($report -match '(?m)^FAIL\s' -or $report -match '(?m)^VI_PHAM\t[^\t]+\t[1-9]') { throw $report }
        Write-Output $report
    }
    $report=Invoke-TestSql ([IO.File]::ReadAllText((Join-Path $projectRoot 'database/checks.sql')))
    if($report -match '(?m)^VI_PHAM\t[^\t]+\t[1-9]') { throw $report }
    # Recreate the all-role manual demo with retained credentials on its own database.
    $testDatabase='educenter_manual_demo_test'
    Invoke-Docker exec -e "MYSQL_PWD=$testPassword" $testContainer mysql --user=root --execute "CREATE DATABASE $testDatabase CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
    [void](Invoke-TestSql $baselineSql)
    $manualDemo=[IO.File]::ReadAllText((Join-Path $projectRoot 'database/seeders/demo-thu-cong.sql')).Replace('USE trung_tam_gia_su;',"USE $testDatabase;")
    [void](Invoke-TestSql $manualDemo)
    $expectedCounts=Invoke-TestSql 'SELECT COUNT(*) FROM account; SELECT COUNT(*) FROM hoc_vien; SELECT COUNT(*) FROM lop_hoc; SELECT COUNT(*) FROM buoi_hoc; SELECT COUNT(*) FROM hoa_don;'
    $demoCounts=$expectedCounts -split '\r?\n'
    if($demoCounts[0] -ne '15' -or $demoCounts[1] -ne '12' -or $demoCounts[2] -ne '5' -or [int]$demoCounts[3] -lt 20 -or $demoCounts[4] -ne '10') { throw 'Bộ demo thiếu dữ liệu cho các vai trò.' }
    $credentialQuery='SELECT account_id,user_id,group_id,username,password_hash,status,is_deleted FROM account ORDER BY account_id;'
    $credentialsBefore=Invoke-TestSql $credentialQuery
    $rolesQuery='SELECT id,user_id,ma_ns,phan_loai,trang_thai FROM nhan_su_chuyen_mon ORDER BY id; SELECT id,user_id,ma_nv,trang_thai FROM nhan_vien ORDER BY id;'
    $rolesBefore=Invoke-TestSql $rolesQuery
    [void](Invoke-TestSql "INSERT INTO audit_log(user_id,hanh_dong,bang,ban_ghi_id,du_lieu_moi) SELECT user_id,'sua','account',account_id,JSON_OBJECT('action','account_status_changed') FROM account WHERE username='demo_admin'; INSERT INTO audit_log(user_id,hanh_dong,bang,ban_ghi_id,du_lieu_moi) SELECT user_id,'khac','account',account_id,JSON_OBJECT('action','dang_xuat','tokenHash','revoked-test-token') FROM account WHERE username='demo_admin';")
    $authAuditQuery="SELECT id,user_id,ban_ghi_id,du_lieu_moi FROM audit_log WHERE bang='account' ORDER BY id;"
    $authAuditBefore=Invoke-TestSql $authAuditQuery
    [void](Invoke-TestSql "CALL sp_demo_educenter('THEM'); CALL sp_demo_educenter('XOA'); CALL sp_demo_educenter('XOA');")
    if((Invoke-TestSql $credentialQuery) -cne $credentialsBefore -or (Invoke-TestSql $rolesQuery) -cne $rolesBefore) { throw 'Dọn demo làm thay đổi tài khoản hoặc vai trò.' }
    if((Invoke-TestSql $authAuditQuery) -cne $authAuditBefore) { throw 'Dọn demo làm mất trạng thái bảo mật của tài khoản.' }
    $businessTables=(Invoke-TestSql "SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name NOT IN ('users','role_group','account','function','role_group_function','mon_hoc','cap_lop','khu_vuc','cau_hinh','nhan_vien','nhan_su_chuyen_mon','schema_migrations','audit_log')") -split '\r?\n'
    $countQuery='SELECT '+(($businessTables | ForEach-Object { '(SELECT COUNT(*) FROM '+$_+')' }) -join '+')+';'
    if((Invoke-TestSql $countQuery) -ne '0') { throw 'Còn dữ liệu nghiệp vụ sau khi dọn demo.' }
    [void](Invoke-TestSql "CALL sp_demo_educenter('THEM'); CALL sp_demo_educenter('THEM');")
    if((Invoke-TestSql $credentialQuery) -cne $credentialsBefore) { throw 'Thêm lại demo làm đổi mật khẩu đang dùng.' }
    if((Invoke-TestSql $authAuditQuery) -cne $authAuditBefore) { throw 'Thêm lại demo làm mất phiên đã thu hồi.' }
    if((Invoke-TestSql 'SELECT COUNT(*) FROM account; SELECT COUNT(*) FROM hoc_vien; SELECT COUNT(*) FROM lop_hoc; SELECT COUNT(*) FROM buoi_hoc; SELECT COUNT(*) FROM hoa_don;') -ne $expectedCounts) { throw 'Chạy lại demo tạo dữ liệu trùng hoặc thiếu.' }
    $report=Invoke-TestSql ([IO.File]::ReadAllText((Join-Path $projectRoot 'database/checks.sql')))
    if($report -match '(?m)^VI_PHAM\t[^\t]+\t[1-9]') { throw $report }
    Write-Output 'PASS: manual demo for all roles, repeat seed/cleanup, retained credentials and database checks.'
    $testDatabase='educenter_sql_test'
    & python database/tests/verify_upgrade.py --container $testContainer
    if($LASTEXITCODE -ne 0) { throw 'Kiểm thử nâng cấp database thất bại.' }
    & python database/tests/verify_upgrade.py --container $testContainer --baseline004
    if($LASTEXITCODE -ne 0) { throw 'Kiểm thử nâng cấp 004 thất bại.' }
    $backupOutput=@(& python database/scripts/backup.py backup --container $testContainer --database $testDatabase --output-dir ".cache/test-backups-$testTag")
    if($LASTEXITCODE -ne 0) { throw 'Kiểm thử tạo backup thất bại.' }
    & python database/scripts/backup.py verify $backupOutput[0] --migrate
    if($LASTEXITCODE -ne 0) { throw 'Kiểm thử khôi phục backup thất bại.' }
    Write-Host 'PASS: PHP, HTTP/MySQL và bộ SQL regression.' -ForegroundColor Green
} finally {
    # Names are generated above and only refer to this run's temporary container/network.
    & docker rm -f -v $testContainer 2>$null | Out-Null
    & docker network rm $testNetwork 2>$null | Out-Null
    Pop-Location
}
