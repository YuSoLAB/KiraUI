<div align="center">

![:name](https://count.getloli.com/@kiraui?name=kiraui&theme=random&padding=7&offset=0&align=top&scale=1&pixelated=1&darkmode=auto)

![logo](./img/kiraui.png)
# KiraUI

[![License: GPL 2.0](https://img.shields.io/badge/license-GPL%202.0-blue)](https://www.gnu.org/licenses/gpl-2.0.html)
[![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-777BB4)](https://www.php.net/)
[![MySQL 8.0+](https://img.shields.io/badge/MySQL-8.0%2B-4479A1)](https://www.mysql.com/)
[![Apache](https://img.shields.io/badge/Apache-HTTP%20Server-D22128)](https://httpd.apache.org/)
[![PHPMailer](https://img.shields.io/badge/PHPMailer-7.0.2-007396)](https://github.com/PHPMailer/PHPMailer)

</div>

## 📋 项目介绍

KirauI 是一个轻量级、高性能的 PHP 内容管理系统，专为个人博客设计。系统采用现代化的 PHP 架构，支持响应式设计，提供完整的后台管理功能。

## 🤔 为何有此

> 本项目的目标是为个人博客提供一个简单、高效的 CMS 解决方案。不将过多的存储空间放在动画效果上，不将过多的性能放在没有必要的地方。对低配置服务器和极高流量的博客友好，提供基本但功能完善的博客系统，将其他框架插件的功能（甚至是付费功能）集成到项目中，完全免费和开箱即用，摆脱 WordPress 的高性能消耗和庞杂的付费主题与插件。

## ✨ 主要特性

- 🚀 **高性能**: 采用缓存机制优化页面加载速度
- 📱 **响应式设计**: 适配桌面和移动设备
- 🔒 **安全可靠**: 内置安全防护机制，防止常见攻击
- 📝 **文章管理**: 完整的文章发布、编辑、分类标签功能
- 💬 **评论系统**: 支持用户评论和回复管理
- 👥 **用户管理**: 多用户角色权限管理
- 📧 **邮件通知**: 集成 [PHPMailer](https://github.com/PHPMailer/PHPMailer) 支持邮件发送
- 🎨 **主题切换**: 支持明暗主题切换
- 🔍 **SEO 友好**: 优化的 URL 结构和元标签

## 🛠️ 技术栈

- **后端**: PHP 8.0+
- **数据库**: MySQL 8.0+
- **Web 服务器**: Apache HTTP Server
> Nginx 也支持, 但需要配置 rewrite 规则, 否则可能会出现 404 错误或无法正常访问
- **邮件服务**: [PHPMailer](https://github.com/PHPMailer/PHPMailer)
- **缓存**: 文件缓存系统

## 📦 安装指南

### 环境要求

- PHP 8.0 或更高版本
- MySQL 8.0 或更高版本
- Apache HTTP Server 或 Nginx
- 其他可参考 test.php 的配置

### 安装步骤

1. **下载项目**
   ```bash
   git clone https://github.com/YuSoLAB/KiraUI
   ```    
   - 或者直接下载项目压缩包并解压
   - 上传项目到您的 Web 服务器根目录

2. **环境配置**
   - 安装 PHP 8.0 或更高版本
   - 安装 MySQL 8.0 或更高版本
   - 安装 Apache HTTP Server 或 Nginx
   - 确保 Apache 的 mod_rewrite 模块已启用，或 Nginx 已配置 rewrite 规则

3. **配置数据库**
   - 创建一个新的 MySQL 数据库
   - 访问 `http://your-domain.com/admin/admin` 进行初始化配置

4. **安装依赖**
   ```bash
   composer install
   ```
   > 项目源码自带依赖的 PHP 库，只有在你需要自行更新依赖时才需要运行此命令

5. **访问网站**
   - 前台访问: `http://your-domain.com`
   - 后台管理: `http://your-domain.com/admin/admin`

6. **检查测试**
   - 对各功能进行测试，确保正常运行

## 🎯 使用说明

### 前台功能

- **首页**: 显示最新发布的文章列表
- **文章详情**: 查看文章内容、评论和相关信息
- **用户中心**: 用户个人信息管理和设置
- **搜索功能**: 按关键词搜索文章

### 后台管理

后台管理地址: `http://your-domain.com/admin/admin`

- **仪表板**: 系统概览和统计数据
- **文章相关**: 发布、编辑、删除文章
- **用户相关**: 管理用户注册登录账户和权限
- **评论相关**: 审核和管理用户评论
- **系统设置**: 配置网站基本信息
- **页面相关**: 管理网站页面和菜单
- **缓存更新**: 清理和优化系统缓存和在线更新源码


## 📁 项目结构

```
├── admin/                 # 后台管理文件
├── cache/                 # 缓存文件
├── img/                   # 图片资源
├── include/               # 核心类库
├── uploads/               # 用户上传文件
├── vendor/                # Composer 依赖
├── .htaccess              # Apache 配置
├── index.php              # 前台入口
└── README.md              # 项目说明
```

## 🔧 开发指南

### 缓存系统

系统使用文件缓存机制，缓存文件存储在 `cache/data` 目录。支持缓存清理和手动刷新。

## 其他参考

### Nginx 配置

本项目开发使用 Apache ，如果你的服务器配置不错，且流量不是很大，建议使用 Apache 。如果使用 Nginx ，配置重写规则会有些麻烦，你可以参考以下配置：

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/your-project;

    index index.php;

   location ~* ^/favicon\.(ico|png|svg)$ {
      try_files /img/favicon.$1 =404;
   }

   if ($request_uri ~ "^/index\.php(\?.*)?$") {
      return 301 /;
   }

   set $php_redir "";
   if ($request_method = GET)                        { set $php_redir "G"; }
   if ($uri ~ "^/(index|get_download_url)\.php")     { set $php_redir "skip"; }
   if ($php_redir = "G") {
      rewrite ^/([^/]+)\.php$ /$1 permanent;
   }

   location / {
      index index.php index.html;
      try_files $uri $uri/ @php_rewrite;
   }

   location @php_rewrite {
      rewrite ^/([^./]+(?:/[^./]+)*)/?$ /$1.php last;
   }
}
```
> 注意：以上配置仅供参考，具体配置请根据您的服务器环境和需求进行调整。

### 更新与缓存

项目内置更新功能，你可以在后台管理中进行更新。更新时会自动备份数据库和文件，防止更新过程中出现问题。

但注意，备份程序并非一定可靠，建议在更新前手动备份数据库和文件。

更新不会删除任何用户数据，也不会删除任何自定义配置，你的所有数据都将被保留，请放心更新。

## 🤝 贡献指南

欢迎提交 Issue 和 Pull Request 来改进项目！

## 📄 许可证

本项目采用 GPL 2.0 许可证 - 查看 [LICENSE](LICENSE) 文件了解详情。

---

<div align="center">

**感谢使用 KiraUI !** 🎉

</div>