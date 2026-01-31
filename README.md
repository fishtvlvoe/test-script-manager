# Test Script Manager

WordPress 後台測試腳本管理工具，讓開發者可以直接在後台編寫、管理、執行測試腳本。

[![WordPress](https://img.shields.io/badge/WordPress-6.4+-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0+-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

## 功能特色

### 🎨 Monaco Editor 程式碼編輯器
- VS Code 同款編輯器
- PHP、SQL、JavaScript、CSS 語法高亮
- WordPress 函式自動完成
- 深色/淺色主題切換

### ⚡ 即時執行
- 一鍵執行腳本
- 即時查看輸出結果
- 格式化的表格和 JSON 顯示
- 執行時間和記憶體使用統計

### 🔄 背景執行
- 長時間運行的腳本可在背景執行
- 關閉瀏覽器後繼續執行
- 執行完成後通知（後台通知、Email）
- 自動重試機制

### 📝 版本控制
- 自動儲存每次修改
- 查看版本歷史
- Monaco Diff Editor 比較差異
- 一鍵還原到任意版本

### 📊 匯出功能
- 匯出為 CSV 檔案
- 匯出為 JSON 檔案
- 匯出為 Excel 檔案（.xlsx）

### 📁 組織管理
- 分類管理腳本
- 內建常用範本
- 批次操作（刪除、分類）
- 搜尋功能

### 🔒 安全性
- 僅管理員可存取
- IP 白名單限制
- 危險函式偵測
- 執行逾時保護

## 安裝

### 方法一：GitHub Release（推薦）

1. 前往 [Releases](https://github.com/fishtv/test-script-manager/releases) 頁面
2. 下載最新版本的 `test-script-manager-x.x.x.zip`
3. 在 WordPress 後台，前往「外掛 → 安裝外掛 → 上傳外掛」
4. 上傳 ZIP 檔案並啟用

### 方法二：手動安裝

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/fishtv/test-script-manager.git
```

然後在 WordPress 後台啟用外掛。

## 自動更新

外掛會自動檢查 GitHub Releases，當有新版本時會在 WordPress 後台顯示更新通知。

## 使用方式

1. 啟用外掛後，前往「工具 → Test Script Manager」
2. 點擊「新增腳本」建立新的測試腳本
3. 在 Monaco Editor 中編寫 PHP 程式碼
4. 點擊「執行」按鈕查看結果

### 快捷鍵

- `Ctrl/Cmd + S` - 儲存腳本
- `Ctrl/Cmd + Enter` - 執行腳本

## 範例腳本

### 資料庫查詢
```php
<?php
global $wpdb;
$users = $wpdb->get_results("SELECT ID, user_login, user_email FROM {$wpdb->users} LIMIT 10");
print_r($users);
```

### 查看所有選項
```php
<?php
global $wpdb;
$options = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE autoload = 'yes' LIMIT 50");
format_as_table($options);
```

### 測試 API
```php
<?php
$response = wp_remote_get('https://api.example.com/endpoint');
$body = wp_remote_retrieve_body($response);
echo json_encode(json_decode($body), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
```

## 系統需求

- WordPress 6.4 或更高版本
- PHP 8.0 或更高版本
- MySQL 5.7 或更高版本

## 開發

### 目錄結構

```
test-script-manager/
├── test-script-manager.php  # 主外掛檔案
├── includes/
│   ├── class-plugin.php     # 外掛載入器
│   ├── class-database.php   # 資料庫結構
│   ├── class-security.php   # 安全性檢查
│   ├── services/            # 服務層
│   ├── api/                 # REST API
│   └── admin/               # 後台頁面
├── assets/
│   ├── css/                 # 樣式檔案
│   └── js/                  # JavaScript
└── scripts/
    └── release.sh           # 發布腳本
```

### 發布新版本

```bash
./scripts/release.sh 1.0.1
```

## 授權

GPL v2 or later

## 作者

[Fish TV](https://test.buygo.me)
