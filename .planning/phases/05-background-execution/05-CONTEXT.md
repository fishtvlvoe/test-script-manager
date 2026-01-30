# Phase 5: Background Execution - Context

**Gathered:** 2026-01-30
**Status:** Ready for planning

<domain>
## Phase Boundary

此階段提供**背景執行功能**，讓長時間執行的測試腳本可以在背景運行，不受瀏覽器 timeout 限制。使用者可以關閉瀏覽器後繼續執行，並在完成時收到通知。

核心能力包含：
- 背景執行選項（使用 Action Scheduler）
- 執行狀態追蹤（Running / Completed / Failed / Cancelled）
- 失敗自動重試機制（最多 3 次）
- 任務取消功能

</domain>

<decisions>
## Implementation Decisions

### 執行狀態通知

- **通知方式**：混合模式 - Admin Notice + Email
- **預設行為**：Admin Notice 和 Email 都預設開啟（使用者可在設定中關閉）
- **Admin Notice 樣式**：
  - 成功：使用 `success` notice 樣式
  - 失敗：使用 `error` notice 樣式
  - 點擊 notice 可導向查看詳細結果
- **Email 內容**：簡潔摘要模式
  - 腳本名稱
  - 執行狀態（成功/失敗）
  - 執行時間
  - 查看完整結果的連結
  - **不包含**完整 output 內容（避免 Email 過長）

### 失敗重試策略

- **重試間隔**：固定間隔 5 分鐘
  - 第 1 次失敗 → 等待 5 分鐘後重試
  - 第 2 次失敗 → 等待 5 分鐘後重試
  - 第 3 次失敗 → 標記為最終失敗，不再重試
- **不重試的情況**：
  - PHP Fatal Error（例如：Syntax Error, Parse Error, Fatal Error）
  - 這類錯誤重試也不會成功，直接標記失敗
- **重試狀態顯示**：顯示重試次數
  - 狀態文字：`Running (Retry 1/3)`, `Running (Retry 2/3)`
- **重試記錄**：記錄每次嘗試
  - 在 `tsm_execution_logs` 表中記錄每次重試的錯誤訊息
  - 便於使用者追蹤問題模式

### 執行歷史顯示

- **側邊欄顯示資訊**：狀態標籤
  - Running / Completed / Failed / Cancelled
  - 不顯示執行時間、執行時長（保持簡潔）
- **視覺區分背景 vs 即時**：圖示區分
  - 背景執行：雲圖示（☁️）
  - 即時執行：閃電圖示（⚡）
- **執行中任務的進度**：不顯示進度百分比
  - 只顯示 `Running` 狀態
  - 原因：PHP 腳本無法回報進度百分比
- **點擊歷史項目行為**：顯示完整結果
  - 在右側面板顯示完整 output 內容和執行統計
  - 不開啟新視窗或自動下載

### 取消執行功能

- **可取消的狀態**：Running / Pending / Retry（全部）
  - Running：正在執行中的任務
  - Pending：排程但尚未開始的任務
  - Retry：等待重試的任務
- **部分結果處理**：保留部分結果
  - 將已執行的 output 保存到資料庫
  - 狀態標記為 `Cancelled`（而非 `Failed`）
  - 使用者可以查看取消前的執行內容
- **取消按鈕位置**：執行歷史項目旁
  - 在 Running/Pending/Retry 狀態的項目旁顯示紅色 ❌ 按鈕
  - 滑鼠 hover 時顯示提示文字
- **取消確認**：需要確認對話框
  - 顯示：「確定要取消執行 [腳本名稱] 嗎？」
  - 防止誤觸取消按鈕

### Claude's Discretion

- Action Scheduler 的具體實作方式（hook 名稱、排程邏輯）
- 資料庫 schema 調整（新增欄位記錄重試次數、取消狀態等）
- Email 模板的 HTML 樣式
- 錯誤處理的實作細節

</decisions>

<specifics>
## Specific Ideas

- **參考 WordPress Cron 系統**：使用 Action Scheduler（WooCommerce 使用的排程系統）而非 WP-Cron，因為 Action Scheduler 更可靠
- **通知機制類似 WooCommerce**：訂單完成時會發送 Admin Notice + Email，使用者體驗良好
- **取消功能參考 Chrome 下載管理**：項目旁直接顯示 ❌ 按鈕，簡單直覺

</specifics>

<deferred>
## Deferred Ideas

無 - 討論內容保持在 Phase 5 範圍內

</deferred>

---

*Phase: 05-background-execution*
*Context gathered: 2026-01-30*
