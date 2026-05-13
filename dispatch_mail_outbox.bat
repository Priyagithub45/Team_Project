@echo off
setlocal

set "APP_DIR=C:\xampp\htdocs\CFO"
set "LOG_DIR=%APP_DIR%\storage\logs"
set "LOG_FILE=%LOG_DIR%\mail_dispatch.log"
set "DISPATCH_URL=http://localhost/CFO/mail_dispatch.php"

if not exist "%LOG_DIR%" mkdir "%LOG_DIR%"

cd /d "%APP_DIR%"
echo [%date% %time%] Starting mail dispatch >> "%LOG_FILE%"
powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "try { (Invoke-WebRequest -UseBasicParsing -Uri '%DISPATCH_URL%' -TimeoutSec 30).Content } catch { Write-Output $_.Exception.Message; exit 1 }" >> "%LOG_FILE%" 2>&1
echo [%date% %time%] Finished mail dispatch >> "%LOG_FILE%"
echo. >> "%LOG_FILE%"

endlocal
