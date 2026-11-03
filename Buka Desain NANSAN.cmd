@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\design-preview-access.ps1" -CopyPassword
if errorlevel 1 pause
