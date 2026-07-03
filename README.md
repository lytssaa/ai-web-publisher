# AI 网页一键发布工具

> HTML / ZIP 一键上线,生成专属访问链接。无需服务器,无需配置,复制链接就能发给别人访问。

[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat-square)
[![SQLite](https://img.shields.io/badge/SQLite-3-003B57?style=flat-square)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg?style=flat-square)

## ✨ 功能特性

- 🚀 **一键发布**:上传 HTML/ZIP 或粘贴代码,1 秒生成访问链接
- 🔗 **短链接**:内置短链生成,便于分享传播
- 🎨 **开箱即用**:PHP + SQLite,无需 MySQL,上传即用
- 📱 **响应式界面**:移动端自适应,手机也能上传生成
- 🔐 **卡密系统**:支持卡密付费模式,也可开启免费模式
- 🛡️ **安全防护**:文件白名单、路径穿越防护、IP 频率限制
- 🧹 **自动清理**:过期站点自动删除,节省空间
- 📊 **管理后台**:可视化管理站点、卡密、系统设置

## 🚀 快速开始

### 方式一:本地一键运行(Windows)

本项目自带 PHP 运行环境,开箱即用:

1. 下载/克隆本仓库
2. 双击 `一键启动.bat`
3. 浏览器自动打开 http://localhost:8000

> 后台地址:http://localhost:8000/admin.php  
> 默认密码:`admin123`(请立即修改!)

### 方式二:部署到服务器

1. 将所有文件上传到 PHP 虚拟主机/云服务器
2. 修改 `config.php` 中的 `SITE_URL` 为你的域名
3. 设置 `uploads/` 和 `data/` 目录可写权限
4. 访问域名即可使用

**环境要求**:
- PHP 7.4+ (推荐 8.0+)
- 扩展:pdo_sqlite、zip、curl、mbstring
- SQLite 3

## 📖 使用说明

### 上传网页

支持两种方式:

| 方式 | 说明 |
|------|------|
| **上传文件** | 支持 `.html` / `.htm` / `.zip`(最大 10MB,ZIP 根目录需含 `index.html`) |
| **粘贴代码** | 直接粘贴 HTML 代码,支持实时预览 |

### 自定义链接

在「想要的链接后缀」填入自定义名称,如 `my-page`,访问地址为 `/uploads/my-page/`。不填则自动生成 16 位随机 ID。

### 管理后台

- **数据概览**:站点数、卡密数统计
- **卡密管理**:生成/删除卡密
- **卡密规格**:天卡/月卡/年卡等自定义
- **站点管理**:查看/删除已生成的网页
- **系统设置**:开启免费模式、修改密码、设置有效期等

### 免费模式 vs 卡密模式

| 模式 | 说明 |
|------|------|
| **免费模式** | 无需卡密即可生成,支持 IP 频率限制,可设置有效期 |
| **卡密模式** | 必须输入有效卡密才能生成,卡密有不同有效期规格 |

在后台「系统设置」中切换。

## 📂 目录结构

```
.
├── php/                  # PHP 运行环境(Windows 版,可选)
├── uploads/              # 生成的网页文件(自动创建)
├── data/                 # SQLite 数据库(自动创建)
├── index.php             # 首页(上传/生成网页)
├── site.php              # 网页访问入口
├── admin.php             # 管理后台
├── config.php            # 配置文件
├── functions.php         # 函数库
├── db.php                # 数据库初始化
├── 一键启动.bat        # 本地启动(Windows)
├── 一键启动_公网版.bat   # 公网启动(需 cloudflared)
└── README.md
```

## ⚙️ 配置说明

编辑 `config.php`:

```php
define('SITE_URL', 'https://你的域名.com/');  // 站点根 URL
define('UPLOAD_DIR', 'uploads/');          // 上传目录
define('MAX_SIZE', 10 * 1024 * 1024);     // 单文件最大体积
define('ADMIN_DEFAULT_PASS', 'admin123'); // 默认密码
define('DEFAULT_FREE_EXPIRE', 7);            // 免费模式默认有效期(天)
```

## 🔒 安全机制

- **文件扩展名白名单**:仅允许 html/css/js/图片/字体/音视频
- **路径穿越防护**:拦截 `../` 等恶意路径
- **IP 频率限制**:免费模式下每 IP 每分钟限制生成次数
- **内容规范协议**:需勾选后才能上传
- **过期自动清理**:到期站点自动删除文件和数据库记录

## 🌐 公网访问

### 临时测试(Cloudflare Tunnel)

无需服务器,免费使用:

```bash
# 安装 cloudflared
winget install Cloudflare.cloudflared

# 启动隧道
cloudflared tunnel --url http://localhost:8000
```

会得到一个 `https://*.trycloudflare.com` 的临时公网地址。

### 正式部署

推荐使用虚拟主机或轻量云服务器,绑定自己的域名。

## 🤝 贡献

欢迎提交 Issue 和 Pull Request!

## 📄 License

MIT License
