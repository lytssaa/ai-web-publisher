<?php
require_once __DIR__ . '/functions.php';

clear_expired();

// 获取站点ID和请求路径
$site_id = $_GET['id'] ?? '';
$path = $_GET['path'] ?? '';

// 兼容PATH_INFO模式
if (empty($site_id) && !empty($_SERVER['PATH_INFO'])) {
    $path_info = trim($_SERVER['PATH_INFO'], '/');
    $parts = explode('/', $path_info, 2);
    $site_id = $parts[0] ?? '';
    $path = $parts[1] ?? '';
}

if (empty($site_id)) {
    header('HTTP/1.1 400 Bad Request');
    exit('参数错误');
}

// 查询站点信息
$stmt = $pdo->prepare("SELECT * FROM sites WHERE site_id = ? LIMIT 1");
$stmt->execute([$site_id]);
$site = $stmt->fetch();

// 站点不存在、已禁用、已过期 → 显示过期提示
if (!$site || $site['status'] != 1 || time() > $site['expire_at']) {
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>站点已过期</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                background: linear-gradient(180deg, #e8f1ff 0%, #f5f9ff 45%, #ffffff 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                color: #1f2937;
            }
            .tip-card {
                background: #fff;
                border-radius: 16px;
                box-shadow: 0 10px 40px rgba(37, 99, 235, 0.08);
                padding: 40px 32px;
                text-align: center;
                max-width: 420px;
                width: 100%;
            }
            .tip-icon {
                width: 64px;
                height: 64px;
                margin: 0 auto 20px;
                color: #f59e0b;
            }
            .tip-card h2 {
                font-size: 22px;
                font-weight: 600;
                margin-bottom: 12px;
                color: #1e293b;
            }
            .tip-card p {
                font-size: 14px;
                color: #64748b;
                line-height: 1.6;
                margin-bottom: 24px;
            }
            .back-btn {
                display: inline-block;
                padding: 10px 24px;
                background: linear-gradient(90deg, #2563eb 0%, #1d4ed8 100%);
                color: #fff;
                text-decoration: none;
                border-radius: 8px;
                font-size: 14px;
                transition: all 0.2s;
            }
            .back-btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
            }
        </style>
    </head>
    <body>
        <div class="tip-card">
            <svg class="tip-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
            <h2>站点已过期或不存在</h2>
            <p>该网页已超过有效期限，或链接有误。如需继续使用，请联系发布者重新生成。</p>
            <a href="index.php" class="back-btn">返回首页</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 站点正常，输出对应静态文件
$site_dir = rtrim(UPLOAD_DIR, '/') . '/' . $site_id . '/';

// 默认访问 index.html
if (empty($path) || substr($path, -1) === '/') {
    $path .= 'index.html';
}

// 安全校验：防止目录穿越
$real_path = realpath($site_dir . $path);
$real_site_dir = realpath($site_dir);
if (!$real_path || !$real_site_dir || strpos($real_path, $real_site_dir) !== 0) {
    header('HTTP/1.1 403 Forbidden');
    exit('非法访问');
}

if (!file_exists($real_path)) {
    header('HTTP/1.1 404 Not Found');
    exit('文件不存在');
}

// 设置正确的Content-Type
$ext = strtolower(pathinfo($real_path, PATHINFO_EXTENSION));
$mime_types = [
    'html' => 'text/html; charset=utf-8',
    'htm'  => 'text/html; charset=utf-8',
    'css'  => 'text/css; charset=utf-8',
    'js'   => 'application/javascript; charset=utf-8',
    'json' => 'application/json; charset=utf-8',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
    'svg'  => 'image/svg+xml',
    'ico'  => 'image/x-icon',
    'woff' => 'font/woff',
    'woff2'=> 'font/woff2',
    'ttf'  => 'font/ttf',
    'eot'  => 'application/vnd.ms-fontobject',
    'txt'  => 'text/plain; charset=utf-8',
    'xml'  => 'application/xml; charset=utf-8',
    'pdf'  => 'application/pdf',
    'zip'  => 'application/zip',
];

$mime = $mime_types[$ext] ?? 'application/octet-stream';
header('Content-Type: ' . $mime);

// 静态资源缓存
if (in_array($ext, ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot'])) {
    header('Cache-Control: public, max-age=86400');
}

// 输出文件内容
readfile($real_path);
exit;