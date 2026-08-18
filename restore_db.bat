@echo off
REM Restores the dailyprod MySQL database from a backup made by backup_db.bat.
REM WARNING: this overwrites current data in the 'dailyprod' database with
REM whatever is in the .sql file you pick. Existing tables/rows with the same
REM names will be replaced.

setlocal
set BACKUP_DIR=C:\xampp\db_backups\dailyprodcs

echo Available backups in %BACKUP_DIR%:
echo.
dir /b /o-d "%BACKUP_DIR%\*.sql" 2>nul
echo.

set /p FILENAME=Type the exact filename to restore (e.g. dailyprod_20260817_103000.sql):

if not exist "%BACKUP_DIR%\%FILENAME%" (
    echo File not found: %BACKUP_DIR%\%FILENAME%
    pause
    exit /b 1
)

echo.
echo This will OVERWRITE the current 'dailyprod' database with:
echo   %BACKUP_DIR%\%FILENAME%
set /p CONFIRM=Type YES to continue:

if /I not "%CONFIRM%"=="YES" (
    echo Cancelled.
    pause
    exit /b 0
)

"C:\xampp\mysql\bin\mysql.exe" -u root dailyprod < "%BACKUP_DIR%\%FILENAME%"

if %ERRORLEVEL% EQU 0 (
    echo.
    echo Restore complete.
) else (
    echo.
    echo Restore FAILED.
)

echo.
pause
