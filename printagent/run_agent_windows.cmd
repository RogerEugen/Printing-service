@echo off
setlocal
cd /d "%~dp0"

if not exist "venv\Scripts\python.exe" (
    echo Print agent virtual environment was not found.
    echo Run: py -m venv venv
    exit /b 2
)

"venv\Scripts\python.exe" agent.py
exit /b %errorlevel%
