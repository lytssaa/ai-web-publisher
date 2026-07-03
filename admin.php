<?php
require_once __DIR__ . '/functions.php';

session_start();
$is_login = isset($_SESSION['admin_login']) && $_SESSION['admin_login'] === true;

// 登录处理
if (!$is_login && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $pass = $_POST['password'] ?? '';
    $admin_pass = get_setting('admin_password');
    if ($pass === $admin_pass) {
        $_SESSION['admin_login'] = true;
        $is_login = true;
    } else {
        $login_error = '密码错误';
    }
}

// 退出登录
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// 后台操作处理
if ($is_login && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // 生成卡密
    if ($action === 'create_card') {
        $type_id = intval($_POST['type_id'] ?? 0);
        $num = intval($_POST['card_num'] ?? 1);
        $num = max(1, min($num, 100));
        
        $stmt = $pdo->prepare("SELECT * FROM card_types WHERE id = ?");
        $stmt->execute([$type_id]);
        $type = $stmt->fetch();
        
        if (!$type) {
            $card_error = '卡密规格不存在';
        } else {
            $new_cards = [];
            for ($i = 0; $i < $num; $i++) {
                $key = generate_card_key();
                $stmt = $pdo->prepare("INSERT INTO cards (card_key, card_type, expire_days, created_at) VALUES (?, ?, ?, ?)");
                $stmt->execute([$key, $type['type_name'], $type['expire_days'], time()]);
                $new_cards[] = $key;
            }
        }
    }
    
    // 删除卡密
    if ($action === 'delete_card') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM cards WHERE id = ? AND status = 0");
        $stmt->execute([$id]);
        header('Location: admin.php?tab=cards');
        exit;
    }
    
    // 删除站点
    if ($action === 'delete_site') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT site_id FROM sites WHERE id = ?");
        $stmt->execute([$id]);
        $site = $stmt->fetch();
        
        if ($site) {
            $dir = UPLOAD_DIR . $site['site_id'] . '/';
            if (is_dir($dir)) delete_dir($dir);
            $stmt = $pdo->prepare("DELETE FROM sites WHERE id = ?");
            $stmt->execute([$id]);
        }
        header('Location: admin.php?tab=sites');
        exit;
    }
    
    // 保存设置
    if ($action === 'save_settings') {
        update_setting('free_mode', $_POST['free_mode'] ?? '0');
        update_setting('auto_clear', $_POST['auto_clear'] ?? '0');
        update_setting('free_expire_days', intval($_POST['free_expire_days'] ?? 7));
        update_setting('free_ip_limit', intval($_POST['free_ip_limit'] ?? 5));
        
        if (!empty($_POST['new_password'])) {
            update_setting('admin_password', $_POST['new_password']);
        }
        header('Location: admin.php?tab=settings&saved=1');
        exit;
    }

    // 添加卡密规格
    if ($action === 'add_type') {
        $name = trim($_POST['type_name'] ?? '');
        $days = intval($_POST['expire_days'] ?? 1);
        $sort = intval($_POST['sort'] ?? 0);
        
        if (empty($name) || $days < 1) {
            $type_error = '名称和天数不能为空';
        } else {
            $stmt = $pdo->prepare("INSERT INTO card_types (type_name, expire_days, sort, created_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $days, $sort, time()]);
            header('Location: admin.php?tab=card_types');
            exit;
        }
    }

    // 编辑卡密规格
    if ($action === 'edit_type') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['type_name'] ?? '');
        $days = intval($_POST['expire_days'] ?? 1);
        $sort = intval($_POST['sort'] ?? 0);
        
        if (empty($name) || $days < 1) {
            $type_error = '名称和天数不能为空';
        } else {
            $stmt = $pdo->prepare("UPDATE card_types SET type_name = ?, expire_days = ?, sort = ? WHERE id = ?");
            $stmt->execute([$name, $days, $sort, $id]);
            header('Location: admin.php?tab=card_types');
            exit;
        }
    }

    // 删除卡密规格
    if ($action === 'delete_type') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM card_types WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: admin.php?tab=card_types');
        exit;
    }
}

// 当前Tab
$tab = $_GET['tab'] ?? 'overview';
$card_types = get_all_card_types();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>管理后台 - HTML网页发布系统</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }
        .login-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            background: #fff;
            padding: 32px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .login-card h2 {
            text-align: center;
            margin-bottom: 24px;
            color: #1e40af;
        }
        .form-input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 16px;
            outline: none;
        }
        .form-input:focus { border-color: #3b82f6; }
        .btn {
            width: 100%;
            padding: 12px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn:hover { background: #1d4ed8; }
        .error {
            color: #dc2626;
            text-align: center;
            margin-bottom: 16px;
            font-size: 14px;
        }

        /* 提示框 */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert.success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        .alert.error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        /* 后台布局 */
        .admin-layout {
            display: flex;
            min-height: 100vh;
            position: relative;
        }
        /* 桌面端侧边栏固定左侧 */
        .sidebar {
            width: 220px;
            background: #0f172a;
            color: #fff;
            padding: 20px 0;
            flex-shrink: 0;
            height: 100vh;
            position: sticky;
            top: 0;
        }
        .sidebar h3 {
            padding: 0 20px 20px;
            font-size: 18px;
            border-bottom: 1px solid #1e293b;
            margin-bottom: 10px;
        }
        .sidebar a {
            display: block;
            padding: 12px 20px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
        }
        .sidebar a:hover, .sidebar a.active {
            background: #1e293b;
            color: #fff;
        }
        .sidebar .logout {
            margin-top: 30px;
            color: #f87171;
        }

        /* 移动端顶部栏+汉堡按钮 */
        .mobile-header {
            display: none;
            height: 56px;
            background: #0f172a;
            align-items: center;
            padding: 0 16px;
            position: sticky;
            top: 0;
            z-index: 99;
        }
        .menu-toggle {
            background: transparent;
            border: none;
            color: #fff;
            font-size: 24px;
            cursor: pointer;
            padding: 0 10px;
        }
        .overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 98;
        }

        .main {
            flex: 1;
            padding: 30px;
            overflow-x: auto;
        }
        .page-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 24px;
        }

        /* 数据概览卡片 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .stat-card .num {
            font-size: 32px;
            font-weight: 700;
            color: #2563eb;
            margin-bottom: 4px;
        }
        .stat-card .label {
            font-size: 14px;
            color: #64748b;
        }

        /* 表格滚动容器 */
        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }
        .card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .card h4 {
            margin-bottom: 16px;
            font-size: 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            min-width: 640px;
        }
        th, td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }
        th {
            background: #f8fafc;
            font-weight: 600;
            color: #475569;
        }
        .tag {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            white-space: nowrap;
        }
        .tag.green { background: #dcfce7; color: #166534; }
        .tag.gray { background: #f1f5f9; color: #475569; }
        .tag.blue { background: #dbeafe; color: #1e40af; }
        .btn-sm {
            padding: 4px 10px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            background: #fef2f2;
            color: #dc2626;
            margin-right: 4px;
            white-space: nowrap;
        }
        .btn-sm:hover { background: #fee2e2; }
        .btn-sm.edit {
            background: #eff6ff;
            color: #2563eb;
        }
        .btn-sm.edit:hover { background: #dbeafe; }

        /* 表单行 */
        .form-row {
            margin-bottom: 16px;
        }
        .form-row label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: 500;
        }
        .form-row select, .form-row input {
            width: 100%;
            max-width: 300px;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            outline: none;
        }
        .form-row .tip {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 4px;
        }
        .btn-primary {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s;
        }
        .btn-primary:hover { background: #1d4ed8; }

        .switch-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .switch-row input {
            width: auto;
        }

        .inline-form {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .inline-form .form-row {
            margin-bottom: 0;
        }

        /* 手机端适配 */
        @media (max-width: 768px) {
            .admin-layout { flex-direction: column; }
            .mobile-header { display: flex; }
            /* 侧边栏默认隐藏，右侧滑入 */
            .sidebar {
                position: fixed;
                left: -220px;
                top: 0;
                transition: left 0.3s ease;
                z-index: 100;
            }
            .sidebar.open { left: 0; }
            .overlay.open { display: block; }
            .main { padding: 16px; }
            .page-title { font-size: 20px; }
            .stat-card .num { font-size: 26px; }
            .card { padding: 16px; }
            .inline-form { gap: 8px; }
            .form-row select, .form-row input { max-width: 100%; }
            .login-card { padding: 24px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            th, td { padding: 10px 6px; font-size: 13px; }
            .sidebar a { padding: 14px 20px; }
        }
    </style>
</head>
<body>

<?php if (!$is_login): ?>
<div class="login-wrap">
    <div class="login-card">
        <h2>管理后台登录</h2>
        <?php if (isset($login_error)): ?>
        <div class="alert error"><?= $login_error ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="password" name="password" class="form-input" placeholder="请输入管理员密码" required>
            <button type="submit" name="login" class="btn">登录</button>
        </form>
        <p style="text-align:center; margin-top:16px; font-size:12px; color:#94a3b8;">
            默认密码：admin123
        </p>
    </div>
</div>
<?php else: ?>
<div class="mobile-header">
    <button class="menu-toggle" id="menuBtn">☰</button>
</div>
<!-- 遮罩层 -->
<div class="overlay" id="overlay"></div>

<div class="admin-layout">
    <div class="sidebar" id="sidebar">
        <h3>管理后台</h3>
        <a href="?tab=overview" class="<?= $tab === 'overview' ? 'active' : '' ?>">数据概览</a>
        <a href="?tab=cards" class="<?= $tab === 'cards' ? 'active' : '' ?>">卡密管理</a>
        <a href="?tab=card_types" class="<?= $tab === 'card_types' ? 'active' : '' ?>">卡密规格</a>
        <a href="?tab=sites" class="<?= $tab === 'sites' ? 'active' : '' ?>">站点管理</a>
        <a href="?tab=settings" class="<?= $tab === 'settings' ? 'active' : '' ?>">系统设置</a>
        <a href="?action=logout" class="logout">退出登录</a>
    </div>

    <div class="main">
        <?php if ($tab === 'overview'): ?>
        <h2 class="page-title">数据概览</h2>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="num"><?= $pdo->query("SELECT COUNT(*) FROM sites")->fetchColumn() ?></div>
                <div class="label">总站点数</div>
            </div>
            <div class="stat-card">
                <div class="num"><?= $pdo->query("SELECT COUNT(*) FROM cards WHERE status = 0")->fetchColumn() ?></div>
                <div class="label">未使用卡密</div>
            </div>
            <div class="stat-card">
                <div class="num"><?= $pdo->query("SELECT COUNT(*) FROM cards WHERE status = 1")->fetchColumn() ?></div>
                <div class="label">已使用卡密</div>
            </div>
            <div class="stat-card">
                <div class="num"><?= get_setting('free_mode') === '1' ? '开启' : '关闭' ?></div>
                <div class="label">免费模式</div>
            </div>
        </div>

        <div class="card">
            <h4>最近生成的站点</h4>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>站点ID</th>
                            <th>创建时间</th>
                            <th>到期时间</th>
                            <th>状态</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->query("SELECT * FROM sites ORDER BY id DESC LIMIT 10");
                        $sites = $stmt->fetchAll();
                        foreach ($sites as $s):
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($s['site_id']) ?></td>
                            <td><?= date('Y-m-d H:i', $s['created_at']) ?></td>
                            <td><?= date('Y-m-d H:i', $s['expire_at']) ?></td>
                            <td><span class="tag <?= $s['status'] ? 'green' : 'gray' ?>"><?= $s['status'] ? '正常' : '禁用' ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($tab === 'cards'): ?>
        <h2 class="page-title">卡密管理</h2>

        <div class="card">
            <h4>生成新卡密</h4>
            <?php if (isset($card_error)): ?>
            <div class="alert error"><?= htmlspecialchars($card_error) ?></div>
            <?php endif; ?>
            <form method="post" class="inline-form">
                <input type="hidden" name="action" value="create_card">
                <div class="form-row">
                    <label>卡密规格</label>
                    <select name="type_id">
                        <?php foreach ($card_types as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['type_name']) ?> (<?= $t['expire_days'] ?>天)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label>生成数量</label>
                    <input type="number" name="card_num" value="1" min="1" max="100">
                </div>
                <button type="submit" class="btn-primary">生成卡密</button>
            </form>
        </div>

        <?php if (isset($new_cards) && !empty($new_cards)): ?>
        <div class="card" style="border-left:4px solid #16a34a;">
            <h4>生成成功（复制使用）</h4>
            <textarea style="width:100%; min-height:120px; padding:10px; font-family:monospace; font-size:13px; border:1px solid #e2e8f0; border-radius:6px;" readonly><?= implode("\n", $new_cards) ?></textarea>
        </div>
        <?php endif; ?>

        <div class="card">
            <h4>卡密列表</h4>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>卡密</th>
                            <th>类型</th>
                            <th>有效期</th>
                            <th>状态</th>
                            <th>生成时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->query("SELECT * FROM cards ORDER BY id DESC");
                        $cards = $stmt->fetchAll();
                        foreach ($cards as $c):
                        ?>
                        <tr>
                            <td style="font-family:monospace;"><?= htmlspecialchars($c['card_key']) ?></td>
                            <td><span class="tag blue"><?= htmlspecialchars($c['card_type']) ?></span></td>
                            <td><?= $c['expire_days'] ?> 天</td>
                            <td><span class="tag <?= $c['status'] ? 'gray' : 'green' ?>"><?= $c['status'] ? '已使用' : '未使用' ?></span></td>
                            <td><?= date('Y-m-d H:i', $c['created_at']) ?></td>
                            <td>
                                <?php if (!$c['status']): ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm('确定删除？')">
                                    <input type="hidden" name="action" value="delete_card">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="btn-sm">删除</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($tab === 'card_types'): ?>
        <h2 class="page-title">卡密规格管理</h2>

        <div class="card">
            <h4>添加规格</h4>
            <?php if (isset($type_error)): ?>
            <div class="alert error"><?= htmlspecialchars($type_error) ?></div>
            <?php endif; ?>
            <form method="post" class="inline-form">
                <input type="hidden" name="action" value="add_type">
                <div class="form-row">
                    <label>规格名称</label>
                    <input type="text" name="type_name" placeholder="如 季度卡" required>
                </div>
                <div class="form-row">
                    <label>有效天数</label>
                    <input type="number" name="expire_days" value="90" min="1" required>
                </div>
                <div class="form-row">
                    <label>排序</label>
                    <input type="number" name="sort" value="0" placeholder="数字越小越靠前">
                </div>
                <button type="submit" class="btn-primary">添加规格</button>
            </form>
        </div>

        <div class="card">
            <h4>规格列表</h4>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>规格名称</th>
                            <th>有效天数</th>
                            <th>排序</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($card_types as $t): ?>
                        <tr>
                            <td><?= $t['id'] ?></td>
                            <td><span class="tag blue"><?= htmlspecialchars($t['type_name']) ?></span></td>
                            <td><?= $t['expire_days'] ?> 天</td>
                            <td><?= $t['sort'] ?></td>
                            <td><?= date('Y-m-d H:i', $t['created_at']) ?></td>
                            <td>
                                <form method="post" style="display:inline;" onsubmit="return confirm('确定删除该规格？已生成的卡密不受影响')">
                                    <input type="hidden" name="action" value="delete_type">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn-sm">删除</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($tab === 'sites'): ?>
        <h2 class="page-title">站点管理</h2>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>站点链接</th>
                            <th>创建IP</th>
                            <th>创建时间</th>
                            <th>到期时间</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->query("SELECT * FROM sites ORDER BY id DESC");
                        $sites = $stmt->fetchAll();
                        foreach ($sites as $s):
                            $url = SITE_URL . UPLOAD_DIR . $s['site_id'] . '/';
                        ?>
                        <tr>
                            <td><a href="<?= htmlspecialchars($url) ?>" target="_blank" style="color:#2563eb; font-size:13px;"><?= htmlspecialchars($s['site_id']) ?></a></td>
                            <td><?= htmlspecialchars($s['create_ip']) ?></td>
                            <td><?= date('Y-m-d H:i', $s['created_at']) ?></td>
                            <td><?= date('Y-m-d H:i', $s['expire_at']) ?></td>
                            <td><span class="tag <?= $s['status'] ? 'green' : 'gray' ?>"><?= $s['status'] ? '正常' : '禁用' ?></span></td>
                            <td>
                                <form method="post" style="display:inline;" onsubmit="return confirm('确定删除该站点？文件将永久删除')">
                                    <input type="hidden" name="action" value="delete_site">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn-sm">删除</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($tab === 'settings'): ?>
        <h2 class="page-title">系统设置</h2>

        <?php if (isset($_GET['saved']) && $_GET['saved'] == 1): ?>
        <div class="alert success">设置保存成功</div>
        <?php endif; ?>

        <div class="card">
            <form method="post">
                <input type="hidden" name="action" value="save_settings">

                <div class="form-row switch-row">
                    <input type="checkbox" id="free_mode" name="free_mode" value="1" <?= get_setting('free_mode') === '1' ? 'checked' : '' ?>>
                    <label for="free_mode">开启免费模式（无需卡密即可生成）</label>
                </div>

                <div class="form-row switch-row">
                    <input type="checkbox" id="auto_clear" name="auto_clear" value="1" <?= get_setting('auto_clear') === '1' ? 'checked' : '' ?>>
                    <label for="auto_clear">自动清理过期站点文件</label>
                </div>

                <div class="form-row">
                    <label>免费模式默认有效期（天）</label>
                    <input type="number" name="free_expire_days" value="<?= get_setting('free_expire_days') ?>" min="1" max="365">
                    <div class="tip">免费模式下生成的站点默认过期天数</div>
                </div>

                <div class="form-row">
                    <label>免费模式单IP频率限制（次/分钟）</label>
                    <input type="number" name="free_ip_limit" value="<?= get_setting('free_ip_limit') ?>" min="0" max="999">
                    <div class="tip">设置为0表示不限制；仅对免费模式生效，卡密用户不受限制</div>
                </div>

                <div class="form-row">
                    <label>修改管理员密码</label>
                    <input type="password" name="new_password" placeholder="不修改请留空">
                </div>

                <button type="submit" class="btn-primary">保存设置</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// 移动端侧边抽屉菜单逻辑
const menuBtn = document.getElementById('menuBtn');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');

function openMenu() {
    sidebar.classList.add('open');
    overlay.classList.add('open');
}
function closeMenu() {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
}
menuBtn.addEventListener('click', openMenu);
overlay.addEventListener('click', closeMenu);
// 点击导航链接自动关闭菜单
sidebar.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
        if(window.innerWidth <= 768) closeMenu();
    })
})
</script>
<?php endif; ?>
</body>
</html>