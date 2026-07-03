<?php
require_once 'db.php';

// 生成随机唯一ID
function generate_id($length = ID_LENGTH) {
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $str = '';
    for ($i = 0; $i < $length; $i++) {
        $str .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $str;
}

// 生成卡密号
function generate_card_key() {
    $part1 = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));
    $part2 = strtoupper(substr(md5(uniqid(mt_rand(), true)), 4, 4));
    $part3 = strtoupper(substr(md5(uniqid(mt_rand(), true)), 8, 4));
    return "KM-{$part1}-{$part2}-{$part3}";
}

// 递归删除目录
function delete_dir($dir) {
    if (!is_dir($dir)) return false;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? delete_dir($path) : unlink($path);
    }
    return rmdir($dir);
}

// 清理过期站点
function clear_expired() {
    if (get_setting('auto_clear') !== '1') return;
    if (mt_rand(1, 100) !== 1) return;
    
    global $pdo;
    $now = time();
    
    // 查询过期站点
    $stmt = $pdo->prepare("SELECT site_id FROM sites WHERE expire_at < ?");
    $stmt->execute([$now]);
    $sites = $stmt->fetchAll();
    
    foreach ($sites as $site) {
        $site_dir = UPLOAD_DIR . $site['site_id'] . '/';
        if (is_dir($site_dir)) {
            delete_dir($site_dir);
        }
    }
    
    // 删除数据库记录
    $stmt = $pdo->prepare("DELETE FROM sites WHERE expire_at < ?");
    $stmt->execute([$now]);
}

// 检查文件扩展名白名单
function check_ext($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ALLOW_EXT);
}

// 安全化文件路径
function safe_path($filename) {
    $filename = str_replace('\\', '/', $filename);
    $filename = preg_replace('/\.\.\//', '', $filename);
    $filename = preg_replace('/^\//', '', $filename);
    return $filename;
}

// 验证卡密
function verify_card($card_key) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM cards WHERE card_key = ? AND status = 0");
    $stmt->execute([strtoupper(trim($card_key))]);
    return $stmt->fetch();
}

// 使用卡密
function use_card($card_id, $site_id, $ip) {
    global $pdo;
    $now = time();
    
    // 获取卡密信息
    $stmt = $pdo->prepare("SELECT * FROM cards WHERE id = ?");
    $stmt->execute([$card_id]);
    $card = $stmt->fetch();
    if (!$card) return false;
    
    // 标记卡密已使用
    $stmt = $pdo->prepare("UPDATE cards SET status = 1, used_at = ?, used_ip = ? WHERE id = ?");
    $stmt->execute([$now, $ip, $card_id]);
    
    // 计算过期时间
    $expire_at = $now + $card['expire_days'] * 86400;
    
    // 写入站点记录
    $stmt = $pdo->prepare("INSERT INTO sites (site_id, card_id, create_ip, created_at, expire_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$site_id, $card_id, $ip, $now, $expire_at]);
    
    return $expire_at;
}

// 免费模式创建站点记录
function create_free_site($site_id, $ip) {
    global $pdo;
    $now = time();
    $expire_days = intval(get_setting('free_expire_days'));
    $expire_at = $now + $expire_days * 86400;
    
    $stmt = $pdo->prepare("INSERT INTO sites (site_id, create_ip, created_at, expire_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$site_id, $ip, $now, $expire_at]);
    
    return $expire_at;
}
?>