@echo off
echo ====================================
echo SmartEdu Hub - تنظيف المشروع للنشر
echo ====================================
echo.

REM حذف المجلدات غير المطلوبة
echo [1/6] حذف المجلدات...
if exist .git rmdir /s /q .git
if exist .github rmdir /s /q .github
if exist test rmdir /s /q test
if exist setup rmdir /s /q setup
if exist .vscode rmdir /s /q .vscode
if exist .idea rmdir /s /q .idea
if exist node_modules rmdir /s /q node_modules
if exist vendor rmdir /s /q vendor

REM حذف ملفات SQL
echo [2/6] حذف ملفات SQL...
del /q *.sql 2>nul

REM حذف ملفات الاختبار والتصحيح
echo [3/6] حذف ملفات الاختبار...
del /q test*.php 2>nul
del /q check*.php 2>nul
del /q fix*.php 2>nul
del /q update*.php 2>nul
del /q run*.php 2>nul
del /q info.php 2>nul
del /q phpinfo.php 2>nul
del /q view_users.php 2>nul

REM حذف ملفات التوثيق (ماعدا README)
echo [4/6] حذف ملفات التوثيق...
for %%f in (*.md) do (
    if /i not "%%f"=="README.md" (
        if /i not "%%f"=="INSTALLATION.md" (
            del /q "%%f"
        )
    )
)

REM حذف ملفات اللوج والمؤقتة
echo [5/6] حذف ملفات اللوج...
del /q *.log 2>nul
del /q *.cache 2>nul
del /q *.tmp 2>nul
del /q Thumbs.db 2>nul
del /q .DS_Store 2>nul

REM حذف ملفات HTML للاختبار
echo [6/6] حذف ملفات HTML للاختبار...
del /q index.html 2>nul
del /q START.html 2>nul

echo.
echo ====================================
echo تم التنظيف بنجاح!
echo ====================================
echo.
echo الآن يمكنك:
echo 1. تعديل config/database.php
echo 2. ضغط المشروع في ملف ZIP
echo 3. رفع إلى InfinityFree
echo.
pause
