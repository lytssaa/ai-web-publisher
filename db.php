<?php
require_once __DIR__ . '/config.php';

// 初始化数据库目录
if (!is_dir(dirname(DB_PATH))) {
    mkdir(dirname(DB_PATH), 0755, true);
}

// 连接SQLite
try {
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('数据库连接失败：' . $e->getMessage());
}

// 初始化数据表
function init_db() {
    global $pdo;

    // 卡密表
    $pdo->exec("CREATE TABLE IF NOT EXISTS cards (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        card_key TEXT UNIQUE NOT NULL,
        card_type TEXT NOT NULL,
        expire_days INTEGER NOT NULL,
        status INTEGER DEFAULT 0,
        created_at INTEGER NOT NULL,
        used_at INTEGER DEFAULT NULL,
        used_ip TEXT DEFAULT NULL
    )");

    // 卡密规格表
    $pdo->exec("CREATE TABLE IF NOT EXISTS card_types (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type_name TEXT UNIQUE NOT NULL,
        expire_days INTEGER NOT NULL,
        sort INTEGER DEFAULT 0,
        created_at INTEGER NOT NULL
    )");

    // 站点表
    $pdo->exec("CREATE TABLE IF NOT EXISTS sites (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        site_id TEXT UNIQUE NOT NULL,
        suffix TEXT DEFAULT NULL,
        card_id INTEGER DEFAULT NULL,
        create_ip TEXT NOT NULL,
        created_at INTEGER NOT NULL,
        expire_at INTEGER NOT NULL,
        status INTEGER DEFAULT 1
    )");

    // 设置表
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key TEXT PRIMARY KEY,
        setting_value TEXT NOT NULL
    )");

    // IP访问记录表（频率限制用）
    $pdo->exec("CREATE TABLE IF NOT EXISTS ip_records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip TEXT NOT NULL,
        create_at INTEGER NOT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ip_records_ip ON ip_records(ip)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ip_records_time ON ip_records(create_at)");

    // 插入默认设置
    $default_settings = [
        'free_mode' => '0',
        'auto_clear' => '1',
        'admin_password' => ADMIN_DEFAULT_PASS,
        'free_expire_days' => DEFAULT_FREE_EXPIRE,
        'free_ip_limit' => '5' // 免费模式单IP每分钟最大生成次数
    ];
    foreach ($default_settings as $key => $value) {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute([$key, $value]);
    }

    // 插入默认卡密规格
    $default_types = [
        ['天卡', 1, 1],
        ['月卡', 30, 2],
        ['年卡', 365, 3]
    ];
    foreach ($default_types as $t) {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO card_types (type_name, expire_days, sort, created_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$t[0], $t[1], $t[2], time()]);
    }
}

// 获取设置
function get_setting($key) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetchColumn();
    return $result !== false ? $result : null;
}

// 更新设置
function update_setting($key, $value) {
    global $pdo;
    $stmt = $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    return $stmt->execute([$key, $value]);
}

// 获取所有卡密规格
function get_all_card_types() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM card_types ORDER BY sort ASC, id ASC");
    return $stmt->fetchAll();
}

// 检查IP频率限制，返回true表示允许，false表示超限
function check_ip_rate_limit($ip) {
    global $pdo;
    $limit = intval(get_setting('free_ip_limit'));
    if ($limit <= 0) return true; // 0表示不限制

    $time_ago = time() - 60;

    // 先清理过期记录
    $stmt = $pdo->prepare("DELETE FROM ip_records WHERE create_at < ?");
    $stmt->execute([$time_ago]);

    // 统计当前IP 1分钟内记录数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ip_records WHERE ip = ? AND create_at >= ?");
    $stmt->execute([$ip, $time_ago]);
    $count = intval($stmt->fetchColumn());

    if ($count >= $limit) {
        return false;
    }

    // 记录本次访问
    $stmt = $pdo->prepare("INSERT INTO ip_records (ip, create_at) VALUES (?, ?)");
    $stmt->execute([$ip, time()]);
    return true;
}

init_db();
?>