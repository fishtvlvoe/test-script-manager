# WordPress Test Script Manager

## What This Is

一個 WordPress 後台管理工具外掛，讓開發者可以直接在後台編寫、管理、執行測試腳本，無需手動建立 PHP 檔案或處理路徑問題。提供強大的 Monaco 編輯器（VS Code 同款）、版本歷史管理、多格式執行結果（表格、JSON、匯出）、背景執行支援，是開發者每天多次使用的主力測試工具。

## Core Value

讓開發者能在 WordPress 後台一鍵編寫並執行測試腳本，立即看到格式化的結果，無需離開瀏覽器或處理檔案路徑問題。

## Requirements

### Validated

(None yet — ship to validate)

### Active

#### 核心編輯與執行系統
- [ ] 後台選單只有管理員可訪問（manage_options capability）
- [ ] 左側顯示腳本列表（名稱、建立時間、最後執行時間）
- [ ] 右側顯示 Monaco 編輯器（語法高亮、自動完成）
- [ ] 點擊「執行」按鈕在新分頁開啟執行結果
- [ ] 腳本執行時自動載入 WordPress 環境（wp-load.php）
- [ ] 執行結果格式化輸出（HTML + CSS 美化）
- [ ] 錯誤訊息紅色高亮顯示

#### Monaco 編輯器功能
- [ ] 支援 PHP 語法高亮和自動完成
- [ ] 支援 JavaScript/Vue.js 語法高亮
- [ ] 支援 CSS 語法高亮
- [ ] 支援 SQL 語法高亮
- [ ] WordPress 函數自動完成提示
- [ ] 即時儲存功能（Auto-save）

#### 腳本儲存與管理
- [ ] 腳本儲存到資料庫（名稱、slug、程式碼、時間戳）
- [ ] 腳本同時儲存到檔案系統（{WordPress 根目錄}/test-scripts/）
- [ ] 檔名格式：test-{slug}.php
- [ ] 可透過瀏覽器直接訪問腳本檔案
- [ ] 提供域名設定選項（自動偵測 ABSPATH + 手動自訂路徑）

#### 版本歷史管理
- [ ] 每次修改前自動建立版本快照
- [ ] 版本列表顯示（時間、修改摘要）
- [ ] 可回溯到任意歷史版本
- [ ] 版本比較功能（Diff View）
- [ ] 版本恢復功能

#### 執行結果美化
- [ ] 格式化文字輸出（<pre> 標籤 + CSS）
- [ ] 資料庫查詢結果用表格呈現
- [ ] JSON/陣列資料可折疊展開（類似 Chrome DevTools）
- [ ] 執行結果可匯出（CSV、JSON、SQL）
- [ ] 執行時間和記憶體使用統計

#### 背景執行機制
- [ ] 長時間腳本可選擇背景執行
- [ ] 背景執行後可關閉分頁
- [ ] 執行完成後發送通知
- [ ] 執行歷史記錄（輸出和錯誤）
- [ ] 執行狀態即時查詢

#### 安全機制
- [ ] Nonce 驗證所有 AJAX 請求
- [ ] 只有管理員可訪問（manage_options）
- [ ] 生產環境禁止危險函數（eval、exec、system）
- [ ] 開發環境（WP_DEBUG）允許所有函數
- [ ] 執行時間限制（可設定 timeout）
- [ ] 輸出大小限制（避免記憶體溢出）

#### REST API
- [ ] POST /wp-json/test-script-manager/v1/scripts - 建立腳本
- [ ] GET /wp-json/test-script-manager/v1/scripts - 列表
- [ ] GET /wp-json/test-script-manager/v1/scripts/{id} - 讀取
- [ ] PUT /wp-json/test-script-manager/v1/scripts/{id} - 更新
- [ ] DELETE /wp-json/test-script-manager/v1/scripts/{id} - 刪除
- [ ] POST /wp-json/test-script-manager/v1/scripts/{id}/execute - 執行
- [ ] GET /wp-json/test-script-manager/v1/scripts/{id}/versions - 版本列表
- [ ] POST /wp-json/test-script-manager/v1/scripts/{id}/restore/{version} - 恢復版本

### Out of Scope

- 多使用者協作編輯 — 本機開發工具，單人使用即可
- 腳本排程執行（WordPress Cron）— 初版專注於手動執行
- 腳本分享/匯入匯出（.php 檔案）— 可手動複製檔案
- 腳本分類或標籤系統 — 初版用搜尋即可
- 前端 JavaScript 即時執行環境 — 需要額外的 sandbox，複雜度高
- 生產環境使用 — 僅限開發環境，避免安全風險

## Context

### 技術環境
- **Framework**: WordPress 外掛
- **Backend**: PHP 8.0+
- **Frontend**: Monaco Editor（VS Code 同款）
- **Database**: WordPress 自訂資料表（wp_test_scripts + wp_test_script_versions）
- **測試環境**: Local by Flywheel (buygo.local) + https://test.buygo.me

### 使用場景
1. **WordPress API 測試**: 測試 WordPress 函數、Hooks、資料庫查詢
2. **外掛功能測試**: 測試自己開發的外掛（FluentCart、buygo-line-notify 等）
3. **資料庫操作**: 直接執行 SQL 查詢、資料修正、資料匯出
4. **API 整合測試**: 測試第三方 API（LINE、PayUNi、金流等）
5. **前端測試**: 執行 JavaScript/Vue.js 測試腳本

### 已知痛點（要解決的問題）
1. **路徑問題**: 符號連結導致 `wp-load.php` 找不到，必須把測試腳本放在特定目錄
2. **重複工作**: 每次測試都要手動建立 PHP 檔案、寫入 wp-load.php 載入代碼
3. **版本控制**: 測試腳本經常需要修改，但沒有版本歷史，改壞了無法回溯
4. **結果查看**: 執行結果是純文字，資料庫查詢結果難以閱讀
5. **管理混亂**: 測試腳本散落各處，沒有統一管理界面

### 開發者習慣
- 每天多次使用測試工具
- 需要快速編寫和執行複雜腳本
- 經常需要回溯到之前的測試版本
- 需要將執行結果匯出給其他人
- 希望編輯器功能強大（接近 IDE）

## Constraints

- **環境約束**: 僅限開發環境使用（不建議在生產環境啟用）
- **安全約束**: 生產環境必須禁止危險函數，開發環境可允許
- **效能約束**: 執行時間限制（預設 30 秒，可調整至 300 秒）
- **儲存約束**: 腳本輸出大小限制（避免記憶體溢出）
- **相容性約束**: WordPress 6.4+、PHP 8.0+
- **權限約束**: 只有管理員（manage_options）可以使用
- **技術約束**: 使用 Monaco Editor（需要載入較大的 JS 檔案）

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| 使用 Monaco Editor 而非 CodeMirror | Monaco 功能更強大（VS Code 同款），支援多語言、自動完成、語法檢查 | — Pending |
| 腳本同時儲存到資料庫和檔案系統 | 資料庫方便管理，檔案系統允許瀏覽器直接訪問 | — Pending |
| 版本歷史使用獨立資料表 | 避免主表資料過大，查詢更快 | — Pending |
| 背景執行使用 WordPress Action Scheduler | 可靠性高，FluentCart 已內建 | — Pending |
| 開發環境允許所有函數 | 開發者需要完整的 PHP 功能進行測試 | — Pending |
| 執行結果支援多種格式匯出 | 開發者經常需要分享測試結果 | — Pending |

---
*Last updated: 2026-01-30 after initialization*
