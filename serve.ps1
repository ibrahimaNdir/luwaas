# Laravel Development Server Launcher
# Usage: .\serve.ps1 [port] [host]

param(
    [int]$Port = 8000,
    [string]$Host = "127.0.0.1"
)

$PHP = "C:\laragon\bin\php\php-8.2.29-nts-Win32-vs16-x64\php.exe"
$WorkDir = "C:\laragon\www\luwaas"

Write-Host "Starting Laravel development server on $Host`:$Port" -ForegroundColor Green
Write-Host "Press Ctrl+C to stop" -ForegroundColor Yellow

Set-Location $WorkDir

# Run PHP built-in server with router script
& $PHP -S "$Host`:$Port" -t public server.php
