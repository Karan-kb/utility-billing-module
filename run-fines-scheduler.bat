@echo off
:loop
echo Running Laravel schedule:run at %time%
cd C:\mantra office\Bidut-Backend
php artisan schedule:run
timeout /t 30 /nobreak >nul
goto loop
