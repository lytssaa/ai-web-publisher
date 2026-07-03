@echo off
chcp 65001 >nul
title AI网页一键发布 - 公网服务
cd /d "%~dp0"

echo ============================================
echo   AI 网页一键发布工具 - 公网模式启动中...
echo ============================================
echo.

if not exist "php\php.exe" (
    echo [错误] 未找到 php\php.exe
    echo 请确保 php 文件夹与此脚本在同一目录下
    pause
    exit /b 1
)

echo [1/2] 正在启动 PHP 服务...
start "PHP-Server" /B php\php.exe -S 0.0.0.0:8000
timeout /t 2 /nobreak >nul

echo.
echo [2/2] 正在启动 Cloudflare 隧道...
echo.
echo 如果显示 404 或域名未就绪,稍等几秒再试即可
echo.
echo ============================================
echo   公网地址将会在下方显示 (https://*.trycloudflare.com)
echo ============================================
echo.
echo [提示] 关闭此窗口即停止所有服务
echo.

where cloudflared >nul 2>&1
if %errorlevel%==0 (
    cloudflared tunnel --url http://localhost:8000
) else (
    echo [错误] 未检测到 cloudflared
    echo 请先安装: winget install Cloudflare.cloudflared
    echo 或者只使用本地模式: 双击"一键启动.bat"
    pause
)

taskkill /F /IM php.exe >nul 2>&1
