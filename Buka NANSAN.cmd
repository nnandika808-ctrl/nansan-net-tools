@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\staging-admin-access.ps1" -CopyPassword
if errorlevel 1 pause
