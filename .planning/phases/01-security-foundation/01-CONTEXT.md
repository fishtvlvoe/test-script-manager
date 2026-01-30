# Phase 1: Security Foundation - Context

**Gathered:** 2026-01-30
**Status:** Ready for planning

<domain>
## Phase Boundary

建立安全邊界和核心基礎設施，包含：
- 資料庫 schema（5 個表）
- 認證層（管理員權限驗證）
- 危險函數阻擋機制
- WordPress 路徑解析（處理符號連結問題）

**目標**：在任何程式碼執行功能之前，先建立完整的安全防護機制。

</domain>

<decisions>
## Implementation Decisions

### 安全策略

**環境判斷：**
- 開發環境（WP_DEBUG = true）：允許所有函數，便於測試
- 生產環境（WP_DEBUG = false）：阻擋危險函數（eval, exec, system, shell_exec, passthru, popen, proc_open）

**權限控制：**
- 只有具備 `manage_options` capability 的管理員可以存取
- 所有 AJAX/REST 請求必須包含有效的 nonce
- nonce action 格式：`tsm_[action_name]`

**防護機制：**
- Nonce 驗證失敗：返回 403 Forbidden
- 權限不足：返回 401 Unauthorized
- 危險函數偵測：執行前掃描程式碼，包含危險函數時拒絕執行（生產環境）

### 資料庫設計

**5 個自訂資料表：**

1. **tsm_scripts** - 腳本主表
   - `id` (bigint, auto_increment, primary key)
   - `name` (varchar 255) - 腳本名稱
   - `slug` (varchar 255, unique) - URL 友善識別碼
   - `code` (longtext) - 腳本程式碼
   - `language` (varchar 20, default 'php') - 程式語言
   - `created_at` (datetime)
   - `updated_at` (datetime)
   - `last_executed_at` (datetime, nullable)
   - INDEX: `slug`, `created_at`, `last_executed_at`

2. **tsm_script_versions** - 版本歷史表
   - `id` (bigint, auto_increment, primary key)
   - `script_id` (bigint, foreign key)
   - `code` (longtext)
   - `created_at` (datetime)
   - INDEX: `script_id`, `created_at`

3. **tsm_execution_logs** - 執行記錄表
   - `id` (bigint, auto_increment, primary key)
   - `script_id` (bigint, foreign key)
   - `output` (longtext)
   - `error` (text, nullable)
   - `execution_time` (float) - 執行時間（秒）
   - `memory_usage` (bigint) - 記憶體使用（bytes）
   - `status` (varchar 20) - success/error/timeout
   - `executed_at` (datetime)
   - INDEX: `script_id`, `executed_at`, `status`

4. **tsm_categories** - 分類表
   - `id` (bigint, auto_increment, primary key)
   - `name` (varchar 255, unique)
   - `slug` (varchar 255, unique)
   - `created_at` (datetime)

5. **tsm_script_tags** - 腳本標籤關聯表（多對多）
   - `script_id` (bigint, foreign key)
   - `category_id` (bigint, foreign key)
   - PRIMARY KEY: (`script_id`, `category_id`)

**清理政策：**
- 版本歷史：保留最近 50 個版本或 30 天內的版本（以先達到者為準）
- 執行記錄：保留最近 100 筆或 30 天內的記錄

### 路徑解析

**問題**：符號連結導致相對路徑計算錯誤

**解決方案：**
1. 使用 `ABSPATH` 常數（WordPress 根目錄的絕對路徑）
2. 腳本檔案儲存位置：`{ABSPATH}/test-scripts/`
3. 不依賴 `__DIR__` 或相對路徑計算 wp-load.php 位置

**檔名格式：**
- `test-{slug}.php`
- 例如：`test-check-orders.php`

**wp-load.php 載入：**
- 在執行腳本前，先在臨時檔案開頭插入：
  ```php
  <?php
  require_once ABSPATH . 'wp-load.php';
  ```
- 執行完畢後清理臨時檔案

### 管理介面

**選單位置：**
- 頂層選單（不放在 Tools 或 Settings 下）
- 選單名稱：「測試腳本」
- 選單 slug：`test-script-manager`
- Icon：`dashicons-editor-code`

**初始 UI 架構：**
- 左側：腳本列表（20% 寬度）
- 右側：編輯區 + 輸出區（80% 寬度）
- 採用 WordPress 原生 UI 元件（admin-color-scheme）

### Claude's Discretion

- 資料庫 charset/collation 選擇（使用 WordPress 預設）
- SQL 查詢優化細節
- 錯誤訊息的具體文字內容
- UI 元件的精確間距和排版

</decisions>

<specifics>
## Specific Ideas

**參考專案：**
- 此專案要解決的問題：CLAUDE.md 中記錄的「路徑問題、重複工作、版本控制、結果查看、管理混亂」
- 符號連結環境：`/Users/fishtv/Development/buygo-line-notify` → `/Users/fishtv/Local Sites/buygo/app/public/wp-content/plugins/buygo-line-notify`

**使用場景優先級：**
1. WordPress API 測試（最高頻率）
2. 外掛功能測試
3. 資料庫操作
4. API 整合測試
5. 前端測試

**安全原則：**
- 寧可過度保護，也不要留下安全漏洞
- 開發環境的便利性 > 生產環境的嚴格性
- 但外掛預設應該假設在生產環境執行

</specifics>

<deferred>
## Deferred Ideas

**Deferred to Phase 4 (Execution Engine):**
以下為執行相關的進階安全功能，需要有完整的執行引擎後才能實作：

- **SEC-04 (Execution Timeout)**: 執行時間限制（可設定 timeout）
  - 需要 Execution Service 存在才能實作 timeout 機制
  - 預設 30 秒，可調整至 300 秒

- **SEC-05 (IP Whitelist)**: 輸出大小限制 / IP 白名單
  - 需要 Settings Page 存在才能配置
  - 在 Phase 7 的 Settings Page 實作

- **SEC-06 (Approval Workflow)**: 審批工作流程（如需要）
  - 進階功能，視專案需求在後續 phase 決定是否實作
  - 目前僅限單一管理員使用，暫不需要審批流程

**理由：** Phase 1 專注於建立安全「基礎設施」（資料庫、認證、危險函數偵測），執行相關的安全功能需要等 Phase 4 的 Execution Engine 完成後才能實作。

</deferred>

---

*Phase: 01-security-foundation*
*Context gathered: 2026-01-30*
*Updated: 2026-01-30 — Added deferred items (SEC-04, SEC-05, SEC-06)*
