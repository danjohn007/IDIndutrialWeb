@echo off
setlocal
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0crear-zip-winrar.ps1"
if errorlevel 1 (
  echo.
  echo No fue posible crear el ZIP. Revisa el mensaje anterior.
)
echo.
pause
