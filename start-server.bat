@echo off
REM Batch script to start Laravel development server with PHP 8.2.29-nts
setlocal enabledelayedexpansion

set PHP_PATH=C:\laragon\bin\php\php-8.2.29-nts-Win32-vs16-x64\php.exe
set LARAVEL_PATH=C:\laragon\www\luwaas
set PORT=%1
if "%PORT%"=="" set PORT=8000
set HOST=%2
if "%HOST%"=="" set HOST=127.0.0.1

echo Starting Laravel development server on %HOST%:%PORT%...
cd /d %LARAVEL_PATH%
"%PHP_PATH%" artisan serve --host=%HOST% --port=%PORT% --no-reload

pause
