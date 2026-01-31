#!/bin/bash
#
# Test Script Manager 首次發布腳本
#
# 這個腳本會：
# 1. 檢查 GitHub CLI 登入狀態
# 2. 建立 GitHub 倉庫
# 3. 推送所有程式碼
# 4. 建立 v1.0.0 Release
#

set -e

cd "$(dirname "$0")/.."

echo "🚀 Test Script Manager 首次發布"
echo "================================"
echo ""

# 檢查 GitHub CLI
if ! gh auth status &>/dev/null; then
    echo "❌ GitHub CLI 未登入"
    echo ""
    echo "請執行以下指令登入："
    echo ""
    echo "  gh auth login -h github.com"
    echo ""
    echo "然後重新執行此腳本"
    exit 1
fi

echo "✅ GitHub CLI 已登入"
echo ""

# 檢查是否已有 remote
if git remote get-url origin &>/dev/null; then
    echo "✅ Git remote 已設定"
    REPO_URL=$(git remote get-url origin)
else
    echo "📦 建立 GitHub 倉庫..."
    gh repo create test-script-manager --public --source=. --push --description "WordPress 後台測試腳本管理工具"
    echo "✅ 倉庫已建立"
fi

# 確保所有檔案已提交
if [ -n "$(git status --porcelain)" ]; then
    echo "📝 提交所有變更..."
    git add -A
    git commit -m "feat: Test Script Manager v1.0.0

Features:
- Monaco Editor 程式碼編輯器
- 即時執行或背景執行腳本
- 格式化輸出（表格、JSON、HTML）
- 版本控制和一鍵還原
- 匯出結果（CSV、JSON、Excel）
- 分類和範本管理
- 批次操作
- IP 白名單和逾時設定
- GitHub 自動更新

Co-Authored-By: Claude <noreply@anthropic.com>"
fi

# 推送到 GitHub
echo "📤 推送到 GitHub..."
git push -u origin main 2>/dev/null || git push -u origin master

# 建立 Release ZIP
echo "📦 建立發布 ZIP..."
BUILD_DIR="/tmp/test-script-manager-build"
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR/test-script-manager"

rsync -av . "$BUILD_DIR/test-script-manager" \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='.DS_Store' \
    --exclude='.planning' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='scripts' \
    --exclude='tests' \
    --exclude='*.log' \
    --quiet

cd "$BUILD_DIR"
zip -rq "test-script-manager-1.0.0.zip" "test-script-manager"
cd -

# 建立 GitHub Release
echo "🎉 建立 GitHub Release v1.0.0..."
gh release create "v1.0.0" "$BUILD_DIR/test-script-manager-1.0.0.zip" \
    --title "v1.0.0 - 首次發布" \
    --notes "## Test Script Manager v1.0.0

### 功能特色

- 🎨 **Monaco Editor** - VS Code 同款程式碼編輯器
- ⚡ **即時執行** - 一鍵執行腳本，立即查看結果
- 🔄 **背景執行** - 長時間運行的腳本可在背景執行
- 📝 **版本控制** - 自動儲存每次修改，支援還原
- 📊 **匯出功能** - 支援 CSV、JSON、Excel 格式
- 📁 **組織管理** - 分類、範本、批次操作
- 🔒 **安全性** - IP 白名單、危險函式偵測

### 安裝方式

1. 下載 \`test-script-manager-1.0.0.zip\`
2. 在 WordPress 後台上傳並啟用
3. 前往「工具 → Test Script Manager」開始使用

### 系統需求

- WordPress 6.4+
- PHP 8.0+"

# 清理
rm -rf "$BUILD_DIR"

echo ""
echo "================================"
echo "🎉 發布完成！"
echo ""
echo "GitHub 倉庫: https://github.com/fishtv/test-script-manager"
echo "Release: https://github.com/fishtv/test-script-manager/releases/tag/v1.0.0"
echo ""
echo "WordPress 網站現在可以透過後台更新通知自動更新了！"
