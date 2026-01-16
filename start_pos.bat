@echo off

REM Check if Apache is running
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I /N "httpd.exe">NUL
if "%ERRORLEVEL%"=="0" (
    goto :mysql_check
) else (
    REM Start Apache using XAMPP Control Panel
    "C:\xampp\xampp-control.exe" -start apache
    timeout /t 3 /nobreak >nul
)

:mysql_check
REM Check if MySQL is running
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="0" (
    goto :open_browser
) else (
    REM Start MySQL using XAMPP Control Panel
    "C:\xampp\xampp-control.exe" -start mysql
    timeout /t 3 /nobreak >nul
)

:open_browser
REM Wait a bit more for services to fully initialize
timeout /t 2 /nobreak >nul

REM Open the application in app mode (looks like desktop software)
start "" chrome --app=http://localhost/POS-DEWAAN/login.php --start-maximized

REM Exit immediately
exit
