<?php
// 站点根URL（结尾带斜杠）带http或者https
define('SITE_URL', 'http://localhost:8000/');

// 上传/生成目录（相对路径，结尾带斜杠）
define('UPLOAD_DIR', 'uploads/');

// 数据库文件路径
define('DB_PATH', __DIR__ . '/data/app.db');

// 后台默认登录密码
define('ADMIN_DEFAULT_PASS', 'admin123');

// 单文件/包最大体积（10MB）
define('MAX_SIZE', 10 * 1024 * 1024);

// 允许的静态文件扩展名白名单
define('ALLOW_EXT', [
    'html', 'htm', 'css', 'js',
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico',
    'woff', 'woff2', 'ttf', 'eot',
    'mp3', 'mp4', 'webm', 'ogg'
]);

// 免费模式默认有效期（天）
define('DEFAULT_FREE_EXPIRE', 7);

// 随机目录名长度
define('ID_LENGTH', 16);

// ========== 短链接API配置 免费的缩短接口 建议不要修改 ==========
define('SHORT_API_URL', 'https://api.suol.cc/v1/dwz.php');
define('SHORT_API_TOKEN', '740CBE4F6BEEF11511F0F127262ADF9F');
?>