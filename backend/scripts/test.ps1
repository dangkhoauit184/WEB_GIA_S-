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
        -e DB_USER=root -e DB_PASSWORD=$testPassword -e TEST_WITH_DB=1 -e JWT_SECRET=$testJwt `
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
    $sql+="`nINSERT INTO schema_migrations(version,status,applied_at) VALUES ('001_feature_based','applied',NOW()),('002_routine_review','applied',NOW()),('003_attribute_review','applied',NOW()),('004_business_rules','applied',NOW());"
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
    foreach($file in @('integrity.sql','regressions.sql','attributes.sql','workflows.sql','business_rules.sql')) {
        $report=Invoke-TestSql ([IO.File]::ReadAllText((Join-Path $projectRoot "database/tests/$file")))
        if($report -match '(?m)^FAIL\s' -or $report -match '(?m)^VI_PHAM\t[^\t]+\t[1-9]') { throw $report }
        Write-Output $report
    }
    $report=Invoke-TestSql ([IO.File]::ReadAllText((Join-Path $projectRoot 'database/checks.sql')))
    if($report -match '(?m)^VI_PHAM\t[^\t]+\t[1-9]') { throw $report }
    & python database/tests/verify_upgrade.py --container $testContainer
    if($LASTEXITCODE -ne 0) { throw 'Kiểm thử nâng cấp database thất bại.' }
    Write-Host 'PASS: PHP, HTTP/MySQL và bộ SQL regression.' -ForegroundColor Green
} finally {
    # Names are generated above and only refer to this run's temporary container/network.
    & docker rm -f -v $testContainer 2>$null | Out-Null
    & docker network rm $testNetwork 2>$null | Out-Null
    Pop-Location
}
