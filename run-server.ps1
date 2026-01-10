# Script to run Laravel server with PHP 8.2.29-nts
param(
    [int]$Port = 8000,
    [string]$Host = "127.0.0.1"
)

$phpPath = "C:\laragon\bin\php\php-8.2.29-nts-Win32-vs16-x64\php-cgi.exe"
$serverPy = "C:\laragon\bin\php\php-8.2.29-nts-Win32-vs16-x64\php-win.exe"

# Try with php-win.exe (GUI, no console) first if it exists
if (Test-Path $serverPy) {
    Write-Host "Starting Laravel server on $Host`:$Port using php-win.exe..."
    & $serverPy -S "$Host`:$Port" "server.php"
} else {
    Write-Host "Starting Laravel server on $Host`:$Port using php-cgi.exe..."
    & $phpPath -S "$Host`:$Port" "server.php"
}
