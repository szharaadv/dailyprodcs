@echo off
REM Backs up the dailyprod MySQL database to a timestamped .sql file.
REM Double-click this before shutting down / sleeping your laptop, or any
REM time you want a safety snapshot. Restores are plain SQL files — see
REM restore_db.bat.

setlocal
for /f "delims=" %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd_HHmmss"') do set TS=%%i

set BACKUP_DIR=C:\xampp\db_backups\dailyprodcs
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

set OUTFILE=%BACKUP_DIR%\dailyprod_%TS%.sql

echo Backing up database 'dailyprod' ...
"C:\xampp\mysql\bin\mysqldump.exe" -u root --routines --triggers --single-transaction dailyprod > "%OUTFILE%"

if %ERRORLEVEL% EQU 0 (
    echo.
    echo Done. Saved to:
    echo   %OUTFILE%
) else (
    echo.
    echo Backup FAILED. Is MySQL running in XAMPP?
)

echo.
pause
