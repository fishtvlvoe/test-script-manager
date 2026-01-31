#!/bin/bash
#
# Test Script Manager Release Script
#
# Usage:
#   ./scripts/release.sh <version>
#   ./scripts/release.sh 1.0.1
#   ./scripts/release.sh 1.1.0 --notes "新增功能..."
#
# This script will:
# 1. Update version in plugin files
# 2. Create release zip
# 3. Commit and tag
# 4. Push to GitHub
# 5. Create GitHub release with zip asset
#

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Plugin info
PLUGIN_SLUG="test-script-manager"
PLUGIN_FILE="test-script-manager.php"
GITHUB_USER="fishtv"
GITHUB_REPO="test-script-manager"

# Check arguments
if [ -z "$1" ]; then
    echo -e "${RED}錯誤: 請提供版本號${NC}"
    echo "用法: ./scripts/release.sh <version>"
    echo "範例: ./scripts/release.sh 1.0.1"
    exit 1
fi

VERSION=$1
RELEASE_NOTES="${2:-}"

# Validate version format
if ! [[ $VERSION =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo -e "${RED}錯誤: 版本號格式不正確${NC}"
    echo "請使用語義化版本，例如: 1.0.0, 1.0.1, 1.1.0"
    exit 1
fi

echo -e "${GREEN}📦 準備發布 Test Script Manager v${VERSION}${NC}"
echo ""

# Get script directory (so we can run from anywhere)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PLUGIN_DIR"

# Check if we're in a git repo
if [ ! -d ".git" ]; then
    echo -e "${RED}錯誤: 不在 git 倉庫中${NC}"
    exit 1
fi

# Check for uncommitted changes
if [ -n "$(git status --porcelain)" ]; then
    echo -e "${YELLOW}警告: 有未提交的變更${NC}"
    git status --short
    echo ""
    read -p "是否繼續？(y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Get current version
CURRENT_VERSION=$(grep -m1 "Version:" "$PLUGIN_FILE" | sed 's/.*Version: *//')
echo "目前版本: $CURRENT_VERSION"
echo "新版本: $VERSION"
echo ""

# Step 1: Update version numbers
echo -e "${YELLOW}步驟 1/5: 更新版本號...${NC}"

# Update plugin header
sed -i '' "s/Version: .*/Version: $VERSION/" "$PLUGIN_FILE"

# Update TSM_VERSION constant
sed -i '' "s/define( 'TSM_VERSION', '.*' )/define( 'TSM_VERSION', '$VERSION' )/" "$PLUGIN_FILE"

echo "✓ 版本號已更新"

# Step 2: Build release zip
echo -e "${YELLOW}步驟 2/5: 建立發布 ZIP...${NC}"

BUILD_DIR="/tmp/${PLUGIN_SLUG}-build"
ZIP_FILE="/tmp/${PLUGIN_SLUG}-${VERSION}.zip"

# Clean up
rm -rf "$BUILD_DIR"
rm -f "$ZIP_FILE"

# Create build directory
mkdir -p "$BUILD_DIR/$PLUGIN_SLUG"

# Copy files (excluding dev files)
rsync -av --progress . "$BUILD_DIR/$PLUGIN_SLUG" \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='.DS_Store' \
    --exclude='.planning' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='scripts' \
    --exclude='tests' \
    --exclude='*.log' \
    --exclude='*.md' \
    --exclude='composer.json' \
    --exclude='composer.lock' \
    --exclude='package.json' \
    --exclude='package-lock.json' \
    --exclude='phpcs.xml' \
    --exclude='phpunit.xml' \
    --exclude='.phpcs.xml' \
    --exclude='*.zip'

# Keep README.md
cp README.md "$BUILD_DIR/$PLUGIN_SLUG/" 2>/dev/null || true

# Create zip
cd "$BUILD_DIR"
zip -r "$ZIP_FILE" "$PLUGIN_SLUG"
cd "$PLUGIN_DIR"

# Clean up build dir
rm -rf "$BUILD_DIR"

ZIP_SIZE=$(du -h "$ZIP_FILE" | cut -f1)
echo "✓ ZIP 已建立: $ZIP_FILE ($ZIP_SIZE)"

# Step 3: Commit changes
echo -e "${YELLOW}步驟 3/5: 提交變更...${NC}"

git add -A
git commit -m "chore: release v${VERSION}" || echo "No changes to commit"

echo "✓ 變更已提交"

# Step 4: Create and push tag
echo -e "${YELLOW}步驟 4/5: 建立並推送標籤...${NC}"

git tag -a "v${VERSION}" -m "Release v${VERSION}"
git push origin main --tags

echo "✓ 標籤已推送"

# Step 5: Create GitHub release
echo -e "${YELLOW}步驟 5/5: 建立 GitHub Release...${NC}"

if [ -z "$RELEASE_NOTES" ]; then
    # Generate release notes from recent commits
    RELEASE_NOTES=$(git log --oneline "v${CURRENT_VERSION}..v${VERSION}" 2>/dev/null | head -20 | sed 's/^/- /')
    if [ -z "$RELEASE_NOTES" ]; then
        RELEASE_NOTES="- 版本 ${VERSION} 發布"
    fi
fi

# Create release with gh CLI
gh release create "v${VERSION}" "$ZIP_FILE" \
    --title "v${VERSION}" \
    --notes "$RELEASE_NOTES"

echo "✓ GitHub Release 已建立"

# Done!
echo ""
echo -e "${GREEN}🎉 發布完成！${NC}"
echo ""
echo "GitHub Release: https://github.com/${GITHUB_USER}/${GITHUB_REPO}/releases/tag/v${VERSION}"
echo "下載連結: https://github.com/${GITHUB_USER}/${GITHUB_REPO}/releases/download/v${VERSION}/${PLUGIN_SLUG}-${VERSION}.zip"
echo ""
echo "WordPress 網站現在可以透過後台的更新通知自動更新了！"
