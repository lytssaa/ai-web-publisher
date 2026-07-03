@echo off
chcp 65001 >nul
title 一键安装 PHP 运行环境
cd /d "%~dp0"

echo ============================================
echo   AI 网页一键发布工具 - PHP 环境安装
echo ============================================
echo.

if exist "php\php.exe" (
    echo [提示] 检测到 php\php.exe 已存在
    echo 是否重新安装？(y/n)
    set /p choice=
    if /i not "%choice%"=="y" (
        echo 已取消安装。
        pause
        exit /b 0
    )
)

echo.
echo [1/4] 正在下载 PHP 8.2 ...
echo.

set PHP_URL=https://windows.php.net/downloads/releases/php-8.2.32-Win32-vs16-x64.zip
set PHP_ZIP=%TEMP%\php_install.zip

powershell -Command "Invoke-WebRequest -Uri '%PHP_URL%' -OutFile '%PHP_ZIP%' -UseBasicParsing"

if not exist "%PHP_ZIP%" (
    echo [错误] 下载失败，请检查网络连接
    pause
    exit /b 1
)

echo.
echo [2/4] 正在解压 PHP ...
echo.

if exist "php" rmdir /s /q php
powershell -Command "Expand-Archive -Path '%PHP_ZIP%' -DestinationPath 'php' -Force"

del /q "%PHP_ZIP%"

if not exist "php\php.exe" (
    echo [错误] 解压失败，请手动重试
    pause
    exit /b 1
)

echo.
echo [3/4] 正在配置 PHP ...
echo.

copy /y "php\php.ini-development" "php\php.ini" >nul

powershell -Command "$ini='php\php.ini'; $c=Get-Content $ini; $c=$c -replace '^;extension=curl','extension=curl'; $c=$c -replace '^;extension=zip','extension=zip'; $c=$c -replace '^;extension=pdo_sqlite','extension=pdo_sqlite'; $c=$c -replace '^;extension=sqlite3','extension=sqlite3'; $c=$c -replace '^;extension=mbstring','extension=mbstring'; $c=$c -replace '^;extension=openssl','extension=openssl'; $c=$c -replace '^;extension=fileinfo','extension=fileinfo'; Set-Content $ini $c"

echo.
echo [4/4] 验证安装 ...
echo.

php\php.exe -v

if %errorlevel% neq 0 (
    echo [错误] PHP 安装验证失败
    pause
    exit /b 1
)

echo.
echo ============================================
echo   PHP 安装完成！
echo ============================================
echo.
echo 已启用扩展: curl / zip / pdo_sqlite / sqlite3 / mbstring / openssl / fileinfo
echo.
echo 下一步: 双击"一键启动.bat"即可运行
echo.
pause
