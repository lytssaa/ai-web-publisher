<?php
require_once __DIR__ . '/functions.php';

clear_expired();
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$free_mode = get_setting('free_mode') === '1';
$msg = $msg_type = $share_url = $short_url = '';

function get_short_url($long_url) {
    // curl 不可用时直接跳过短链接生成
    if (!function_exists('curl_init')) return false;
    $api_url = SHORT_API_URL . '?m_token=' . SHORT_API_TOKEN . '&url=' . urlencode($long_url);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    if (!$response) return false;
    $data = json_decode($response, true);
    return (isset($data['code']) && $data['code'] == 200 && isset($data['s_url'])) ? $data['s_url'] : false;
}

// 统一站点生成逻辑
function handle_submit() {
    global $free_mode, $client_ip;
    $result = ['code' => 0, 'msg' => '', 'share_url' => '', 'short_url' => ''];

    if ($free_mode && !check_ip_rate_limit($client_ip)) {
        $limit = intval(get_setting('free_ip_limit'));
        $result['msg'] = "操作过于频繁，免费模式每IP每分钟最多生成 {$limit} 次，请稍后再试";
        return $result;
    }
    if (!isset($_POST['agree'])) {
        $result['msg'] = '请先阅读并同意内容规范';
        return $result;
    }

    $card_key = trim($_POST['card_key'] ?? '');
    $card_info = null;
    if (!$free_mode) {
        if (empty($card_key)) {
            $result['msg'] = '请输入有效的卡密';
            return $result;
        }
        $card_info = verify_card($card_key);
        if (!$card_info) {
            $result['msg'] = '卡密无效或已被使用';
            return $result;
        }
    }

    $link_suffix = trim($_POST['link_suffix'] ?? '');
    if (!empty($link_suffix)) {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $link_suffix)) {
            $result['msg'] = '链接后缀仅支持字母、数字、横杠和下划线';
            return $result;
        }
        if (is_dir(UPLOAD_DIR . $link_suffix . '/')) {
            $result['msg'] = '该后缀已被占用，请换一个';
            return $result;
        }
        $site_id = $link_suffix;
    } else {
        $site_id = generate_id();
    }

    $site_dir = UPLOAD_DIR . $site_id . '/';
    if (!mkdir($site_dir, 0755, true)) {
        $result['msg'] = '创建目录失败，请检查目录权限';
        return $result;
    }

    $success = false;
    if (isset($_FILES['zip_file']) && $_FILES['zip_file']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['zip_file']['size'] > MAX_SIZE) {
            $result['msg'] = '文件过大，最大支持10MB';
        } else {
            $orig_name = $_FILES['zip_file']['name'];
            $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
            // 单个 HTML 文件直接保存为 index.html
            if ($ext === 'html' || $ext === 'htm') {
                if (move_uploaded_file($_FILES['zip_file']['tmp_name'], $site_dir . 'index.html')) {
                    $success = true;
                } else {
                    $result['msg'] = '文件保存失败，请检查目录权限';
                }
            } elseif ($ext === 'zip') {
                $zip = new ZipArchive();
                if ($zip->open($_FILES['zip_file']['tmp_name']) !== true) {
                    $result['msg'] = 'ZIP文件损坏，无法解压';
                } else {
                    $has_index = $danger_file = false;
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $stat = $zip->statIndex($i);
                        $filename = safe_path($stat['name']);
                        if (str_ends_with($filename, '/')) continue;
                        if (!check_ext($filename)) { $danger_file = true; break; }
                        if (strtolower(basename($filename)) === 'index.html') $has_index = true;
                        $dest_path = $site_dir . $filename;
                        if (!is_dir(dirname($dest_path))) mkdir(dirname($dest_path), 0755, true);
                        file_put_contents($dest_path, $zip->getFromIndex($i));
                    }
                    $zip->close();
                    if ($danger_file) {
                        $result['msg'] = 'ZIP包包含非法可执行文件，已拦截';
                    } elseif (!$has_index) {
                        $result['msg'] = 'ZIP包根目录未找到 index.html 入口文件';
                    } else {
                        $files = array_diff(scandir($site_dir), ['.', '..']);
                        if (count($files) === 1 && is_dir($site_dir . $files[array_key_first($files)])) {
                            $inner_dir = $site_dir . $files[array_key_first($files)] . '/';
                            foreach (array_diff(scandir($inner_dir), ['.', '..']) as $f) {
                                rename($inner_dir . $f, $site_dir . $f);
                            }
                            rmdir($inner_dir);
                        }
                        $success = true;
                    }
                }
            } else {
                $result['msg'] = '仅支持 .html / .htm / .zip 文件';
            }
        }
    } else {
        $html_code = $_POST['html_code'] ?? '';
        if (empty($html_code)) {
            $result['msg'] = '请上传文件或粘贴HTML代码';
        } else {
            file_put_contents($site_dir . 'index.html', $html_code);
            $success = true;
        }
    }

    if ($success) {
        $card_info ? use_card($card_info['id'], $site_id, $client_ip) : create_free_site($site_id, $client_ip);
        $result['code'] = 1;
        $result['msg'] = '网页已成功生成，可通过链接访问';
        $result['share_url'] = SITE_URL . 'site.php?id=' . $site_id;
        if (isset($_POST['make_short'])) {
            $short = get_short_url($result['share_url']);
            if ($short) $result['short_url'] = $short;
        }
    } else {
        delete_dir($site_dir);
        if (empty($result['msg'])) $result['msg'] = '生成失败，请重试';
    }
    return $result;
}

// AJAX 提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode(handle_submit());
    exit;
}

// 普通表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $res = handle_submit();
    $msg = $res['msg'];
    $msg_type = $res['code'] ? 'success' : 'error';
    $share_url = $res['share_url'];
    $short_url = $res['short_url'];
}

function count_deployed_sites() {
    global $pdo;
    return $pdo->query("SELECT COUNT(*) FROM sites WHERE status = 1")->fetchColumn();
}
$site_count = count_deployed_sites();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AI网页一键发布 - 生成专属访问链接</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{
    font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"PingFang SC","Microsoft YaHei",sans-serif;
    background:linear-gradient(135deg,#eaf2ff 0%,#f6f0ff 50%,#fff0f6 100%);
    background-attachment:fixed;
    min-height:100vh;color:#1f2937;line-height:1.5
}

/* Hero 区域 */
.hero{text-align:center;padding:48px 20px 32px}
.hero .sub-title{font-size:14px;color:#64748b;letter-spacing:2px;margin-bottom:12px}
.hero h1{
    font-size:48px;font-weight:800;line-height:1.15;margin-bottom:16px;
    background:linear-gradient(135deg,#1e40af 0%,#7c3aed 100%);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text
}
.hero .desc{font-size:15px;color:#64748b;margin-bottom:24px}
.stats{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
.stat-item{
    padding:6px 14px;background:rgba(255,255,255,.65);backdrop-filter:blur(8px);
    border:1px solid rgba(255,255,255,.8);border-radius:20px;font-size:14px;color:#4338ca;font-weight:500
}
.stat-item strong{
    background:linear-gradient(135deg,#3b82f6 0%,#8b5cf6 100%);
    -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;font-weight:700
}

/* 容器与卡片 */
.container{max-width:960px;margin:0 auto 60px;padding:0 20px}
.card{background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(100,80,160,.08);padding:28px}
.card-title{font-size:18px;font-weight:600;margin-bottom:16px}

/* 提示条 */
.notice-bar{
    display:flex;align-items:flex-start;gap:10px;padding:12px 16px;
    background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;
    font-size:13px;color:#1e40af;line-height:1.5;margin-bottom:24px
}
.notice-bar svg{flex-shrink:0;width:18px;height:18px;margin-top:1px;color:#3b82f6}

/* 表单通用 */
.form-group{margin-bottom:24px}
.form-label{display:block;font-size:16px;font-weight:500;color:#1e293b;margin-bottom:12px}
.form-input,textarea{
    width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;
    font-size:14px;outline:none;transition:border-color .2s;background:#fefefe
}
textarea{min-height:220px;font-family:Consolas,Monaco,monospace;font-size:13px;resize:vertical}
.form-input:focus,textarea:focus{border-color:#8b5cf6}
.input-tip{font-size:12px;color:#94a3b8;margin-top:6px;line-height:1.5}

/* 上传区域 */
.upload-area{
    display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;
    border:2px dashed #dbeafe;border-radius:12px;background:#f8fafc;
    min-height:180px;padding:24px 20px;cursor:pointer;transition:all .2s
}
.upload-area:hover{border-color:#8b5cf6;background:#f5f3ff}
.upload-area svg{width:48px;height:48px;color:#6366f1;margin-bottom:4px}
.upload-area .upload-title{font-size:18px;font-weight:500;color:#4338ca}
.upload-area .upload-desc{font-size:14px;color:#64748b}
.upload-area .upload-status{font-size:15px;color:#6366f1;font-weight:500;margin-top:4px}
.upload-area input{display:none}

/* 分割线 */
.divider-text{text-align:center;color:#94a3b8;font-size:14px;margin:20px 0}

/* 代码编辑区 */
.code-wrap{position:relative;width:100%}
.code-tabs{
    position:absolute;top:8px;right:8px;display:flex;gap:4px;z-index:10;
    background:rgba(255,255,255,.92);backdrop-filter:blur(4px);
    border-radius:6px;padding:2px;box-shadow:0 2px 8px rgba(0,0,0,.06)
}
.code-tab{
    padding:4px 12px;border:none;background:transparent;border-radius:4px;
    font-size:12px;cursor:pointer;color:#64748b;transition:all .2s
}
.code-tab.active{background:linear-gradient(135deg,#3b82f6,#8b5cf6);color:#fff;font-weight:500}
.preview-frame{width:100%;min-height:220px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;display:none}

/* 卡密输入 */
.card-input-wrap{display:flex;gap:10px;align-items:center}
.card-input-wrap .form-input{flex:1}
.option-row{display:flex;gap:8px;align-items:center;font-size:13px;color:#475569;margin:16px 0}
.option-row input{flex-shrink:0}

/* 协议勾选 */
.agree-box{
    display:flex;gap:10px;align-items:flex-start;font-size:12px;color:#92400e;
    margin:20px 0;line-height:1.6;background:#fffbeb;padding:12px 14px;
    border-radius:8px;border:1px solid #fde68a
}
.agree-box input{margin-top:3px;flex-shrink:0}
.agree-box .tip-icon{flex-shrink:0;width:16px;height:16px;margin-top:2px;color:#f59e0b}

/* 提交按钮 */
.submit-btn{
    width:100%;padding:14px;background:linear-gradient(90deg,#2563eb 0%,#7c3aed 100%);
    color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:500;
    cursor:pointer;transition:all .2s
}
.submit-btn:hover:not(:disabled){
    background:linear-gradient(90deg,#1d4ed8 0%,#6d28d9 100%);
    transform:translateY(-1px);box-shadow:0 6px 16px rgba(99,102,241,.25)
}
.submit-btn:disabled{opacity:.6;cursor:not-allowed}

/* 进度条 */
.progress-wrap{margin-top:20px;display:none}
.progress-wrap.show{display:block}
.progress-bar{width:100%;height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden;margin-bottom:8px}
.progress-inner{
    height:100%;width:0%;background:linear-gradient(90deg,#3b82f6,#8b5cf6);
    border-radius:4px;transition:width .3s ease
}
.progress-text{font-size:13px;color:#64748b;text-align:center}

/* 结果卡片 */
.result-box{
    margin-top:20px;padding:16px 20px;border-radius:10px;display:none;
    border:1px solid transparent;box-shadow:0 2px 8px rgba(0,0,0,.04)
}
.result-box.success{display:block;background:#f0fdf4;border-color:#bbf7d0;color:#166534}
.result-box.error{display:block;background:#fef2f2;border-color:#fecaca;color:#991b1b}
.result-box .result-header{display:flex;align-items:center;gap:10px;font-weight:600;font-size:15px;margin-bottom:8px}
.result-box .result-header svg{width:20px;height:20px;flex-shrink:0}
.result-box.success .result-header svg{color:#22c55e}
.result-box.error .result-header svg{color:#ef4444}
.result-box .result-content{font-size:14px;line-height:1.6;padding-left:30px}

.url-row{display:flex;gap:10px;margin-top:12px;align-items:center;padding-left:30px}
.url-row label{min-width:60px;font-size:13px;font-weight:500;flex-shrink:0}
.url-row input{flex:1;padding:10px;border:1px solid #bbf7d0;border-radius:6px;background:#fff;font-size:13px;outline:none}
.result-box.error .url-row input{border-color:#fecaca}

.copy-btn{
    padding:0 16px;background:#16a34a;color:#fff;border:none;border-radius:6px;
    cursor:pointer;display:flex;align-items:center;gap:6px;
    transition:background .2s;font-size:13px;height:38px;flex-shrink:0
}
.copy-btn:hover{background:#15803d}
.result-box.error .copy-btn{background:#dc2626}
.result-box.error .copy-btn:hover{background:#b91c1c}
.copy-btn svg{width:16px;height:16px}

@media (max-width:768px){
    .hero h1{font-size:32px}
    .card{padding:20px}
    .hero{padding:32px 20px 24px}
    .upload-area{min-height:160px}
    .upload-area .upload-title{font-size:16px}
    .url-row{flex-direction:column;align-items:stretch;padding-left:0}
    .url-row label{min-width:auto}
    .result-box .result-content{padding-left:0}
}
</style>
</head>
<body>
<div class="hero">
    <div class="sub-title">HTML / ZIP 一键上线</div>
    <h1>AI 网页一键发布<br>生成专属访问链接</h1>
    <p class="desc">让 AI 生成的网页 1 分钟内上线，无需服务器，无需代码，复制链接就能发给别人访问。</p>
    <div class="stats">
        <div class="stat-item"><strong><?= $site_count ?></strong> 个网页已部署</div>
        <div class="stat-item"><strong>1分钟</strong> 快速上线</div>
        <div class="stat-item"><strong>0配置</strong> 无需服务器</div>
    </div>
</div>

<div class="container">
    <div class="card">
        <h2 class="card-title">提交网页</h2>
        
        <div class="notice-bar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            <span>支持HTML/CSS/JS/图片等静态内容，禁止上传违法、钓鱼、诈骗、仿冒登录、侵权或收集敏感信息的内容；生成失败不会消耗卡密。</span>
        </div>

        <form id="submitForm" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">上传 HTML 或 ZIP 文件</label>
                <label class="upload-area" id="uploadArea">
                    <input type="file" name="zip_file" id="fileInput" accept=".zip,.html,.htm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                    <div class="upload-title">点击上传 HTML / ZIP 文件</div>
                    <div class="upload-desc">支持拖拽上传，点击后在弹窗中选择或拖入文件</div>
                    <div class="upload-status" id="fileStatus">还没有选择文件</div>
                </label>
            </div>

            <div class="divider-text">或者直接粘贴 HTML 代码</div>

            <div class="form-group">
                <div class="code-wrap">
                    <div class="code-tabs">
                        <button type="button" class="code-tab active" data-mode="edit">编辑</button>
                        <button type="button" class="code-tab" data-mode="preview">预览</button>
                    </div>
                    <textarea name="html_code" id="htmlCode" placeholder="<!doctype html>..."><?= htmlspecialchars($_POST['html_code'] ?? '') ?></textarea>
                    <iframe id="previewFrame" class="preview-frame" title="HTML预览"></iframe>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">想要的链接后缀</label>
                <input type="text" class="form-input" name="link_suffix" placeholder="例如 my-ai-page" value="<?= htmlspecialchars($_POST['link_suffix'] ?? '') ?>">
                <div class="input-tip">例如 my-ai-page，生成 /uploads/my-ai-page/；不填会自动生成</div>
            </div>

            <?php if (!$free_mode): ?>
            <div class="form-group">
                <label class="form-label">卡密</label>
                <div class="card-input-wrap">
                    <input type="text" class="form-input" name="card_key" placeholder="例如 KM-XXXX-XXXX-XXXX" value="<?= htmlspecialchars($_POST['card_key'] ?? '') ?>">
                </div>
                <div class="input-tip">没有卡密可先预览页面，确认满意后再输入卡密生成正式链接。</div>
            </div>
            <?php endif; ?>

            <label class="option-row">
                <input type="checkbox" name="make_short" <?= isset($_POST['make_short']) ? 'checked' : '' ?>>
                <span>同时生成短链接，便于分享传播</span>
            </label>

            <label class="agree-box">
                <svg class="tip-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
                <input type="checkbox" name="agree" <?= isset($_POST['agree']) ? 'checked' : '' ?>>
                <span>我确认上传内容为本人有权发布的静态网页，不包含违法违规、钓鱼仿冒、诈骗引流、色情博彩、侵权盗版、收集密码/验证码/银行卡/身份证等敏感信息的内容。</span>
            </label>

            <button type="submit" id="submitBtn" class="submit-btn"><?= $free_mode ? '生成访问链接' : '使用卡密并生成链接' ?></button>

            <div class="progress-wrap" id="progressWrap">
                <div class="progress-bar">
                    <div class="progress-inner" id="progressInner"></div>
                </div>
                <div class="progress-text" id="progressText">准备上传...</div>
            </div>
        </form>

        <div class="result-box <?= $msg_type ?>" id="resultBox" <?php if (!$msg) echo 'style="display:none;"' ?>>
            <div class="result-header">
                <?php if ($msg_type == 'success'): ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <span>操作成功</span>
                <?php else: ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
                <span>操作失败</span>
                <?php endif; ?>
            </div>
            <div class="result-content"><?= htmlspecialchars($msg) ?></div>

            <?php if ($share_url): ?>
            <div class="url-row">
                <label>原链接</label>
                <input type="text" value="<?= htmlspecialchars($share_url) ?>" id="shareUrl" readonly>
                <button class="copy-btn" onclick="copyText('shareUrl', this)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                    复制
                </button>
            </div>
            <?php endif; ?>
            <?php if ($short_url): ?>
            <div class="url-row">
                <label>短链接</label>
                <input type="text" value="<?= htmlspecialchars($short_url) ?>" id="shortUrl" readonly>
                <button class="copy-btn" onclick="copyText('shortUrl', this)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                    </svg>
                    复制
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const $ = id => document.getElementById(id);
const fileInput = $('fileInput'), fileStatus = $('fileStatus'), uploadArea = $('uploadArea');
const submitForm = $('submitForm'), submitBtn = $('submitBtn');
const progressWrap = $('progressWrap'), progressInner = $('progressInner'), progressText = $('progressText');
const resultBox = $('resultBox'), htmlCode = $('htmlCode'), previewFrame = $('previewFrame');
const codeTabs = document.querySelectorAll('.code-tab');
const btnText = '<?= $free_mode ? "生成访问链接" : "使用卡密并生成链接" ?>';

// 文件选择
fileInput.addEventListener('change', () => {
    fileStatus.textContent = fileInput.files.length ? '已选择：' + fileInput.files[0].name : '还没有选择文件';
});

// 拖拽上传
['dragenter','dragover','dragleave','drop'].forEach(evt => {
    uploadArea.addEventListener(evt, e => { e.preventDefault(); e.stopPropagation(); });
});
['dragenter','dragover'].forEach(evt => {
    uploadArea.addEventListener(evt, () => {
        uploadArea.style.borderColor = '#8b5cf6';
        uploadArea.style.background = '#f5f3ff';
    });
});
['dragleave','drop'].forEach(evt => {
    uploadArea.addEventListener(evt, () => {
        uploadArea.style.borderColor = '#dbeafe';
        uploadArea.style.background = '#f8fafc';
    });
});
uploadArea.addEventListener('drop', e => {
    if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        fileStatus.textContent = '已选择：' + e.dataTransfer.files[0].name;
    }
});

// 预览切换
codeTabs.forEach(tab => {
    tab.addEventListener('click', () => {
        codeTabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        const edit = tab.dataset.mode === 'edit';
        htmlCode.style.display = edit ? 'block' : 'none';
        previewFrame.style.display = edit ? 'none' : 'block';
        if (!edit) previewFrame.srcdoc = htmlCode.value;
    });
});

// AJAX 提交
submitForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const xhr = new XMLHttpRequest();

    submitBtn.disabled = true;
    submitBtn.textContent = '处理中...';
    progressWrap.classList.add('show');
    resultBox.style.display = 'none';
    progressInner.style.width = '0%';
    progressText.textContent = '准备上传...';

    xhr.upload.onprogress = e => {
        if (!e.lengthComputable) return;
        const p = Math.round((e.loaded / e.total) * 100);
        progressInner.style.width = p + '%';
        progressText.textContent = p < 100 ? '文件上传中 ' + p + '%' : '正在处理文件...';
    };

    xhr.onreadystatechange = () => {
        if (xhr.readyState !== 4) return;
        if (xhr.status === 200) {
            try {
                const res = JSON.parse(xhr.responseText);
                progressText.textContent = res.code ? '生成完成' : '生成失败';
                setTimeout(() => showResult(res.code ? 'success' : 'error', res.msg, res.share_url, res.short_url), 300);
            } catch {
                showResult('error', '服务器响应异常，请刷新重试');
            }
        } else {
            showResult('error', '网络错误，请检查网络后重试');
        }
        submitBtn.disabled = false;
        submitBtn.textContent = btnText;
        setTimeout(() => progressWrap.classList.remove('show'), 800);
    };

    xhr.open('POST', '', true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.send(formData);
});

function showResult(type, msg, shareUrl = '', shortUrl = '') {
    resultBox.className = 'result-box ' + type;
    resultBox.style.display = 'block';
    resultBox.querySelector('.result-header span').textContent = type === 'success' ? '操作成功' : '操作失败';
    resultBox.querySelector('.result-content').textContent = msg;

    resultBox.querySelectorAll('.url-row').forEach(r => r.remove());
    if (shareUrl) appendUrlRow('原链接', shareUrl, 'shareUrl');
    if (shortUrl) appendUrlRow('短链接', shortUrl, 'shortUrl');
}

function appendUrlRow(label, url, id) {
    const row = document.createElement('div');
    row.className = 'url-row';
    row.innerHTML = `
        <label>${label}</label>
        <input type="text" value="${url}" id="${id}" readonly>
        <button class="copy-btn" onclick="copyText('${id}', this)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
            </svg>
            复制
        </button>
    `;
    resultBox.appendChild(row);
}

function copyText(inputId, btn) {
    $(inputId).select();
    document.execCommand('copy');
    const original = btn.innerHTML;
    btn.innerHTML = `
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
        已复制
    `;
    setTimeout(() => btn.innerHTML = original, 2000);
}
</script>
</body>
</html>