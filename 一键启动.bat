@echo off
chcp 65001 >nul
title AI网页一键发布 - 本地服务
cd /d "%~dp0"

echo ============================================
echo   AI 网页一键发布工具 - 本地服务启动中...
echo ============================================
echo.

if not exist "php\php.exe" (
    echo [错误] 未找到 php\php.exe
    echo 请确保 php 文件夹与此脚本在同一目录下
    pause
    exit /b 1
)

start "" timeout /t 3 /nobreak >nul ^& start "" "http://localhost:8000/index.php"

echo 服务地址: http://localhost:8000/index.php
echo 后台地址: http://localhost:8000/admin.php  (密码 admin123)
echo.
echo [提示] 不要关闭此窗口,关闭即停止服务
echo [停止] 按 Ctrl + C 或直接关闭窗口
echo.
echo --------------------------------------------
echo 日志输出:
echo --------------------------------------------

php\php.exe -S localhost:8000
pause
