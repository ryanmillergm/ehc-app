@echo off
setlocal

REM --- Customize if needed ---
set APP_URL=http://localhost:8000
set WEBHOOK_PATH=/stripe/webhook

echo Starting Laravel + Vite + Stripe listen...
echo APP_URL=%APP_URL%
echo Forwarding to %APP_URL%%WEBHOOK_PATH%
echo.

REM Laravel server
start "Laravel" cmd /k php artisan serve

REM Vite
start "Vite" cmd /k npm run dev

REM Stripe listen (make sure stripe.exe is in PATH)
start "Stripe Listen" cmd /k stripe listen --forward-to %APP_URL%%WEBHOOK_PATH%

REM Optional: show status after a second
timeout /t 2 >nul
php artisan stripe:webhook-status

endlocal
