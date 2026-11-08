[CmdletBinding()]
param([string]$Username = 'admin', [switch]$Reset)
$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$oldPassword=$env:INITIAL_ADMIN_PASSWORD
$oldUsername=$env:ADMIN_USERNAME
$pointer=[IntPtr]::Zero
Push-Location $projectRoot
try {
    $secure=Read-Host 'Đặt mật khẩu admin (ít nhất 6 ký tự)' -AsSecureString
    $pointer=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    $env:INITIAL_ADMIN_PASSWORD=[Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    $env:ADMIN_USERNAME=$Username
    $extraArgs=@()
    if($Reset) { $extraArgs+='--reset' }
    & docker compose run --rm --no-deps -e INITIAL_ADMIN_PASSWORD -e ADMIN_USERNAME `
        backend php spark educenter:bootstrap-admin @extraArgs
    if($LASTEXITCODE -ne 0) { throw 'Không tạo được admin. Xem thông báo phía trên.' }
} finally {
    if($pointer -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }
    $env:INITIAL_ADMIN_PASSWORD=$oldPassword
    $env:ADMIN_USERNAME=$oldUsername
    Pop-Location
}
