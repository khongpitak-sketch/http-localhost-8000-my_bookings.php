@echo off
chcp 65001 > nul
title ระบบขออนุญาตใช้รถยนต์ คณะวิทยาการจัดการ ม.นราธิวาสราชนครินทร์
echo =======================================================================
echo     ระบบขออนุญาตใช้รถยนต์ คณะวิทยาการจัดการ มหาวิทยาลัยนราธิวาสราชนครินทร์
echo =======================================================================
echo.
echo กำลังตรวจสอบโปรแกรม PHP...

set PHP_BIN=php
where php >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    if exist "C:\xampp\php\php.exe" (
        set PHP_BIN=C:\xampp\php\php.exe
    ) else (
        echo [ERROR] ไม่พบโปรแกรม PHP ในเครื่อง หรือใน C:\xampp\php
        echo กรุณาติดตั้ง XAMPP หรือระบุตำแหน่ง PHP ใน start_system.bat
        pause
        exit /b 1
    )
)

echo ตรวจพบ PHP: %PHP_BIN%
echo.
echo กำลังเริ่มต้น Web Server ที่ http://localhost:8000 ...
echo (อย่าปิดหน้าต่างนี้ขณะใช้งานระบบ)
echo.

start "" "http://localhost:8000"
"%PHP_BIN%" -S localhost:8000
pause
