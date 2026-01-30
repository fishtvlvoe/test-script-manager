# Requirements: WordPress Test Script Manager

**Defined:** 2026-01-30
**Core Value:** 讓開發者能在 WordPress 後台一鍵編寫並執行測試腳本，立即看到格式化的結果，無需離開瀏覽器或處理檔案路徑問題

## v1 Requirements

### Security & Access Control

- [ ] **SEC-01**: 只有管理員可訪問（manage_options capability）
- [ ] **SEC-02**: 所有 AJAX 請求都需要 nonce 驗證
- [ ] **SEC-03**: 根據 WP_DEBUG 環境變數控制危險函數（eval, exec, system）
- [ ] **SEC-04**: 腳本執行 timeout 限制（可設定，預設 30 秒）
- [ ] **SEC-05**: IP 白名單功能（可選啟用）
- [ ] **SEC-06**: 腳本審核工作流程（需要主管審核才能執行）

### Monaco Editor Integration

- [ ] **EDIT-01**: PHP 語法高亮和自動完成
- [ ] **EDIT-02**: SQL 語法高亮和自動完成
- [ ] **EDIT-03**: JavaScript 語法高亮和自動完成
- [ ] **EDIT-04**: CSS 語法高亮和自動完成
- [ ] **EDIT-05**: Vue.js 語法高亮支援
- [ ] **EDIT-06**: 自動儲存功能（Auto-save）
- [ ] **EDIT-07**: WordPress 函數和 Hooks 自動完成提示
- [ ] **EDIT-08**: 主題切換功能（暗黑/明亮模式）
- [ ] **EDIT-09**: 鍵盤快捷鍵支援（執行、儲存、搜尋）

### Script Storage & Management

- [ ] **STORE-01**: 建立新腳本（名稱、slug、程式碼）
- [ ] **STORE-02**: 編輯現有腳本
- [ ] **STORE-03**: 刪除腳本
- [ ] **STORE-04**: 腳本列表顯示（名稱、建立時間、最後執行時間、最後修改時間）
- [ ] **STORE-05**: 腳本儲存到資料庫（tsm_scripts 表）
- [ ] **STORE-06**: 腳本同時儲存到檔案系統（{WordPress 根目錄}/test-scripts/）
- [ ] **STORE-07**: 腳本檔名格式：test-{slug}.php
- [ ] **STORE-08**: 可透過瀏覽器直接訪問腳本檔案
- [ ] **STORE-09**: 域名設定選項（自動偵測 ABSPATH + 手動自訂路徑）
- [ ] **STORE-10**: 腳本搜尋功能（名稱、程式碼內容）
- [ ] **STORE-11**: 腳本過濾功能（建立日期、最後執行日期、狀態）
- [ ] **STORE-12**: 腳本分類系統（可自訂分類）
- [ ] **STORE-13**: 腳本標籤系統（多標籤支援）
- [ ] **STORE-14**: 腳本範例庫（常用範例快速建立）

### Script Execution

- [ ] **EXEC-01**: 執行 PHP 腳本並載入 WordPress 環境（wp-load.php）
- [ ] **EXEC-02**: 捕獲腳本輸出（stdout）
- [ ] **EXEC-03**: 錯誤訊息紅色高亮顯示
- [ ] **EXEC-04**: 執行時間統計（開始時間、結束時間、總耗時）
- [ ] **EXEC-05**: 執行記憶體使用統計
- [ ] **EXEC-06**: 背景執行支援（使用 Action Scheduler）
- [ ] **EXEC-07**: 背景執行狀態查詢（執行中、已完成、失敗）
- [ ] **EXEC-08**: 背景執行完成通知
- [ ] **EXEC-09**: 資料庫查詢結果用表格呈現
- [ ] **EXEC-10**: JSON/陣列資料可折疊展開（類似 Chrome DevTools）
- [ ] **EXEC-11**: 執行結果匯出為 CSV
- [ ] **EXEC-12**: 執行結果匯出為 JSON
- [ ] **EXEC-13**: 執行結果匯出為 Excel（.xlsx）
- [ ] **EXEC-14**: 執行歷史記錄（儲存最近 100 次執行）
- [ ] **EXEC-15**: 執行歷史可查看輸出和錯誤
- [ ] **EXEC-16**: 輸出大小限制（避免記憶體溢出，預設 10MB）

### Version History

- [ ] **VER-01**: 每次儲存前自動建立版本快照
- [ ] **VER-02**: 版本列表顯示（時間戳、修改摘要、儲存者）
- [ ] **VER-03**: 版本比較功能（Diff View，顯示新增/刪除/修改行）
- [ ] **VER-04**: 版本恢復功能（一鍵回溯到任意歷史版本）
- [ ] **VER-05**: 版本自動清理機制（保留最後 50 版本或 30 天內版本）
- [ ] **VER-06**: 版本儲存到獨立資料表（tsm_script_versions）

### REST API Endpoints

- [ ] **API-01**: POST /wp-json/test-script-manager/v1/scripts - 建立腳本
- [ ] **API-02**: GET /wp-json/test-script-manager/v1/scripts - 腳本列表（支援分頁、搜尋、過濾）
- [ ] **API-03**: GET /wp-json/test-script-manager/v1/scripts/{id} - 讀取單一腳本
- [ ] **API-04**: PUT /wp-json/test-script-manager/v1/scripts/{id} - 更新腳本
- [ ] **API-05**: DELETE /wp-json/test-script-manager/v1/scripts/{id} - 刪除腳本
- [ ] **API-06**: POST /wp-json/test-script-manager/v1/scripts/{id}/execute - 執行腳本（同步或背景）
- [ ] **API-07**: GET /wp-json/test-script-manager/v1/scripts/{id}/versions - 版本歷史列表
- [ ] **API-08**: POST /wp-json/test-script-manager/v1/scripts/{id}/restore/{version} - 恢復到指定版本
- [ ] **API-09**: GET /wp-json/test-script-manager/v1/executions - 執行歷史列表
- [ ] **API-10**: GET /wp-json/test-script-manager/v1/executions/{id} - 讀取單一執行記錄

### Database Schema

- [ ] **DB-01**: 建立 tsm_scripts 資料表（id, name, slug, code, created_at, updated_at, last_executed_at, category_id）
- [ ] **DB-02**: 建立 tsm_script_versions 資料表（id, script_id, code, created_at, created_by）
- [ ] **DB-03**: 建立 tsm_execution_logs 資料表（id, script_id, output, errors, execution_time, memory_usage, status, created_at）
- [ ] **DB-04**: 建立 tsm_categories 資料表（id, name, slug, description）
- [ ] **DB-05**: 建立 tsm_script_tags 資料表（id, script_id, tag）

### Admin UI

- [ ] **UI-01**: WordPress 後台新增「測試腳本」選單項目
- [ ] **UI-02**: 左側顯示腳本列表（卡片或表格視圖）
- [ ] **UI-03**: 右側顯示 Monaco 編輯器
- [ ] **UI-04**: 執行按鈕（同步執行 + 背景執行選項）
- [ ] **UI-05**: 新分頁顯示執行結果（格式化輸出）
- [ ] **UI-06**: 設定頁面（域名設定、timeout、IP 白名單）
- [ ] **UI-07**: 版本歷史側邊欄（可展開/收合）
- [ ] **UI-08**: 執行歷史側邊欄（可展開/收合）

## v2 Requirements

延後到下一個版本的功能：

### Advanced Features

- **ADV-01**: 腳本排程執行（WordPress Cron 整合）
- **ADV-02**: 腳本協作編輯（多使用者同時編輯）
- **ADV-03**: 腳本分享/匯入匯出（.php 檔案或 JSON 格式）
- **ADV-04**: 腳本效能分析（profiling、query monitor 整合）
- **ADV-05**: 腳本除錯器（斷點、變數監控）
- **ADV-06**: 腳本測試框架（PHPUnit 整合）
- **ADV-07**: 即時語法檢查（linting）
- **ADV-08**: Git 版本控制整合
- **ADV-09**: 多語系支援（i18n）
- **ADV-10**: 腳本權限細分（不同角色可執行不同腳本）

## Out of Scope

| Feature | Reason |
|---------|--------|
| 檔案系統編輯器 | 專注於測試腳本執行，不做通用檔案編輯器（避免安全風險） |
| AI 程式碼生成 | 初版不整合 AI，保持工具簡單專注 |
| 前端 JavaScript 即時執行環境 | 需要額外 sandbox，複雜度高 |
| 與 PhpMyAdmin 功能重疊 | 不做資料庫管理界面（表結構、索引等），專注於查詢執行 |
| WordPress 檔案修改 | 不允許修改 WordPress 核心檔案或外掛檔案（安全考量） |
| 生產環境使用 | 僅限開發環境，避免安全風險 |
| 行動裝置支援 | 開發工具專注於桌面瀏覽器體驗 |
| 腳本市集/社群分享 | 初版不建立腳本分享平台 |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| SEC-01 | Phase 1 | Pending |
| SEC-02 | Phase 1 | Pending |
| SEC-03 | Phase 1 | Pending |
| SEC-04 | Phase 1 | Pending |
| SEC-05 | Phase 1 | Pending |
| SEC-06 | Phase 1 | Pending |
| DB-01 | Phase 1 | Pending |
| DB-02 | Phase 1 | Pending |
| DB-03 | Phase 1 | Pending |
| DB-04 | Phase 1 | Pending |
| DB-05 | Phase 1 | Pending |
| UI-01 | Phase 1 | Pending |
| STORE-01 | Phase 2 | Pending |
| STORE-02 | Phase 2 | Pending |
| STORE-03 | Phase 2 | Pending |
| STORE-04 | Phase 2 | Pending |
| STORE-05 | Phase 2 | Pending |
| STORE-06 | Phase 2 | Pending |
| STORE-07 | Phase 2 | Pending |
| STORE-08 | Phase 2 | Pending |
| STORE-09 | Phase 2 | Pending |
| STORE-10 | Phase 2 | Pending |
| STORE-11 | Phase 2 | Pending |
| API-01 | Phase 2 | Pending |
| API-02 | Phase 2 | Pending |
| API-03 | Phase 2 | Pending |
| API-04 | Phase 2 | Pending |
| API-05 | Phase 2 | Pending |
| UI-02 | Phase 2 | Pending |
| EDIT-01 | Phase 3 | Pending |
| EDIT-02 | Phase 3 | Pending |
| EDIT-03 | Phase 3 | Pending |
| EDIT-04 | Phase 3 | Pending |
| EDIT-05 | Phase 3 | Pending |
| EDIT-06 | Phase 3 | Pending |
| EDIT-07 | Phase 3 | Pending |
| EDIT-08 | Phase 3 | Pending |
| EDIT-09 | Phase 3 | Pending |
| UI-03 | Phase 3 | Pending |
| EXEC-01 | Phase 4 | Pending |
| EXEC-02 | Phase 4 | Pending |
| EXEC-03 | Phase 4 | Pending |
| EXEC-04 | Phase 4 | Pending |
| EXEC-05 | Phase 4 | Pending |
| EXEC-09 | Phase 4 | Pending |
| EXEC-10 | Phase 4 | Pending |
| EXEC-14 | Phase 4 | Pending |
| EXEC-15 | Phase 4 | Pending |
| EXEC-16 | Phase 4 | Pending |
| API-06 | Phase 4 | Pending |
| API-09 | Phase 4 | Pending |
| API-10 | Phase 4 | Pending |
| UI-04 | Phase 4 | Pending |
| UI-05 | Phase 4 | Pending |
| EXEC-06 | Phase 5 | Pending |
| EXEC-07 | Phase 5 | Pending |
| EXEC-08 | Phase 5 | Pending |
| UI-08 | Phase 5 | Pending |
| VER-01 | Phase 6 | Pending |
| VER-02 | Phase 6 | Pending |
| VER-03 | Phase 6 | Pending |
| VER-04 | Phase 6 | Pending |
| VER-05 | Phase 6 | Pending |
| VER-06 | Phase 6 | Pending |
| API-07 | Phase 6 | Pending |
| API-08 | Phase 6 | Pending |
| UI-07 | Phase 6 | Pending |
| STORE-12 | Phase 7 | Pending |
| STORE-13 | Phase 7 | Pending |
| STORE-14 | Phase 7 | Pending |
| EXEC-11 | Phase 7 | Pending |
| EXEC-12 | Phase 7 | Pending |
| EXEC-13 | Phase 7 | Pending |
| UI-06 | Phase 7 | Pending |

**Coverage:**
- v1 requirements: 74 total
- Mapped to phases: 74
- Unmapped: 0

---
*Requirements defined: 2026-01-30*
*Last updated: 2026-01-30 after roadmap creation*
