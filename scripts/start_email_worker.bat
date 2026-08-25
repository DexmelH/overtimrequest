@echo off
REM Starts the overtime email queue worker (long-running loop).
REM Use with Windows Task Scheduler: run at startup/logon, do not start a new instance if already running.

set PHP_EXE=C:\xampp\php\php.exe
set WORKER=%~dp0..\src\usr\bin\email_worker.php

if not exist "%PHP_EXE%" (
  echo PHP not found at %PHP_EXE%
  exit /b 1
)

if not exist "%WORKER%" (
  echo Worker not found at %WORKER%
  exit /b 1
)

cd /d "%~dp0.."
"%PHP_EXE%" -f "%WORKER%"
