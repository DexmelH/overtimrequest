@echo off
REM Overtime email queue — batch drain (exits after each run).
REM
REM Windows Task Scheduler (recommended):
REM   1. Create a Basic Task / Task that runs this .bat
REM   2. Trigger: Daily, repeat every 1 minute for indefinitely (or use a 1-minute trigger)
REM   3. Action: Start a program = this file (full path)
REM   4. Settings: "If the task is already running, then the following rule applies:
REM                 Do not start a new instance"
REM   5. Stop any old ALWAYS-ON php.exe email_worker daemons before enabling this task
REM
REM Optional: pass --limit=N after the script path by editing the php line below.
REM
REM One-time on the DB server:
REM   mysql ... < databases\migrations\013_email_queue_worker_indexes.sql

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
"%PHP_EXE%" -f "%WORKER%" -- --limit=10
exit /b %ERRORLEVEL%
