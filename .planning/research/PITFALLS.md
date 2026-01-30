# Pitfalls Research

**Domain:** WordPress Admin Code Editor / Script Execution Tools
**Researched:** 2026-01-30
**Confidence:** HIGH

## Critical Pitfalls

### Pitfall 1: Remote Code Execution (RCE) Without Proper Sandbox

**Severity:** CRITICAL

**What goes wrong:**
允許執行任意 PHP 程式碼（含 `eval`、`exec`、`system`、`shell_exec`）時，若無適當的隔離機制，攻擊者可能透過 XSS 或 CSRF 注入惡意程式碼，取得伺服器完整控制權。

**Why it happens:**
- 開發者為了「方便」在開發環境允許所有危險函數
- `WP_DEBUG` 判斷不足以區分「安全的開發環境」與「可能被入侵的環境」
- 假設只有管理員可以訪問，忽略管理員帳號可能被盜用

**How to avoid:**
1. **明確的環境白名單**: 除了 `WP_DEBUG`，還要檢查 `WP_ENVIRONMENT_TYPE`、特定 IP 白名單、或額外的 "ALLOW_DANGEROUS_FUNCTIONS" 常數
2. **多重驗證**: nonce + capability + IP 白名單 + 額外密碼
3. **函數禁止清單**: 生產環境使用 `disable_functions` 級別的防護，直接在 PHP 層禁止
4. **執行隔離**: 考慮使用獨立的 PHP 進程或 container 執行使用者腳本

**Warning signs:**
- 只用 `WP_DEBUG` 判斷是否允許危險函數
- 沒有 IP 白名單機制
- 沒有額外的存取密碼保護
- 程式碼執行前沒有任何警告訊息

**Phase to address:** Phase 1 (Security Foundation) — 在任何程式碼執行功能之前必須建立

---

### Pitfall 2: XSS Through Script Output Display

**Severity:** CRITICAL

**What goes wrong:**
腳本執行結果直接輸出到頁面，若結果包含使用者可控的 HTML/JavaScript，可能造成 XSS 攻擊。例如腳本查詢資料庫中的使用者輸入資料，該資料包含 `<script>` 標籤。

**Why it happens:**
- 為了「還原」執行結果，直接使用 `innerHTML` 或 `echo` 輸出
- 假設「管理員看到的內容都是安全的」
- JSON 格式輸出時沒有正確 escape HTML entities

**How to avoid:**
1. **輸出分層處理**:
   - 純文字輸出: 使用 `<pre>` + `htmlspecialchars()`
   - HTML 輸出: 使用 DOMPurify 或類似的 HTML sanitizer
   - JSON 輸出: 使用 JSON viewer 元件（不是直接渲染 HTML）
2. **Content Security Policy**: 設定嚴格的 CSP 防止 inline script 執行
3. **Sandbox iframe**: 將輸出結果放在 sandboxed iframe 中顯示

**Warning signs:**
- 使用 `innerHTML` 或 jQuery `.html()` 顯示執行結果
- 沒有對輸出結果進行 escape
- 沒有設定 CSP header

**Phase to address:** Phase 2 (Result Display) — 實作結果顯示功能時

---

### Pitfall 3: CSRF on Script Execution API

**Severity:** CRITICAL

**What goes wrong:**
攻擊者製作惡意頁面，當管理員瀏覽該頁面時自動觸發腳本執行 API，執行預先設計的惡意程式碼。

**Why it happens:**
- REST API 端點沒有正確驗證 nonce
- 使用 GET 請求執行腳本（應該用 POST）
- Cookie-based 認證讓跨站請求自動帶上認證資訊

**How to avoid:**
1. **嚴格的 Nonce 驗證**: 每個執行請求都需要有效的 `wp_rest` nonce
2. **使用 POST 方法**: 所有修改操作和執行操作只接受 POST
3. **加入確認步驟**: 危險操作需要使用者二次確認
4. **SameSite Cookie**: 確保 WordPress session cookie 設定為 `SameSite=Strict`

**Warning signs:**
- 執行 API 使用 GET 方法
- API 沒有 `permission_callback` 或只檢查 capability
- 沒有使用 `wp_verify_nonce()` 或 `check_ajax_referer()`

**Phase to address:** Phase 1 (Security Foundation)

---

### Pitfall 4: Symlink Path Resolution Failure

**Severity:** HIGH

**What goes wrong:**
使用符號連結開發時，`__DIR__`、`__FILE__`、`plugin_dir_url()` 等函數回傳的是實際路徑而非符號連結路徑，導致：
- `wp-load.php` 找不到
- 資源檔案 404
- 腳本執行失敗

**Why it happens:**
- PHP 的 `__FILE__` 自動解析符號連結到實際路徑
- WordPress 的 `ABSPATH` 常數也會受影響
- 開發者習慣使用符號連結管理多個專案

**How to avoid:**
1. **使用 `$_SERVER["SCRIPT_FILENAME"]`**: 取代 `__FILE__` 來保留符號連結路徑
2. **提供手動設定選項**: 讓使用者可以自訂 WordPress 根目錄路徑
3. **自動偵測 + 驗證**: 嘗試多種路徑策略，驗證 `wp-load.php` 是否存在
4. **參考 `WP_CONTENT_DIR` 和 `WP_CONTENT_URL`**: 這些常數是可靠的

```php
// 偵測順序
$possible_paths = [
    $_SERVER['DOCUMENT_ROOT'],
    realpath(__DIR__ . '/../../../'),
    get_home_path(),
    ABSPATH,
];
```

**Warning signs:**
- 硬編碼 `require_once __DIR__ . '/../../../wp-load.php'`
- 沒有提供路徑設定選項
- 沒有驗證 wp-load.php 是否存在

**Phase to address:** Phase 1 (Core Setup) — 初始化時就要處理

---

### Pitfall 5: Database + Filesystem Dual-Write Race Condition

**Severity:** HIGH

**What goes wrong:**
腳本同時儲存到資料庫和檔案系統時，可能發生：
- 資料庫寫入成功但檔案寫入失敗（或相反）
- 兩個請求同時修改，造成資料不一致
- 檔案系統的腳本版本與資料庫版本不同

**Why it happens:**
- 沒有使用 transaction 或 atomic 操作
- 沒有 locking 機制
- 快取導致讀取到舊資料
- WordPress 不是 thread-safe 的

**How to avoid:**
1. **定義明確的 source of truth**: 資料庫為主，檔案系統為備份/快取
2. **延遲檔案寫入**: 只在「執行」時才寫入檔案，不在「儲存」時同步
3. **加入 checksum 驗證**: 儲存時計算 md5，讀取時驗證
4. **Optimistic locking**: 使用 `version` 欄位，更新時檢查版本號
5. **檔案系統 fallback**: 資料庫無法讀取時，從檔案系統恢復

```php
// Optimistic locking 範例
UPDATE wp_test_scripts
SET code = %s, version = version + 1
WHERE id = %d AND version = %d
```

**Warning signs:**
- 沒有 source of truth 的定義
- 兩個儲存操作是獨立的（不在同一個 transaction）
- 沒有 version 欄位
- 沒有處理寫入失敗的情況

**Phase to address:** Phase 2 (Script Storage)

---

### Pitfall 6: Monaco Editor Performance Degradation

**Severity:** HIGH

**What goes wrong:**
Monaco Editor 載入時間過長（2MB+ 的 JS 檔案），或編輯大型腳本時卡頓，導致：
- 首次載入需要 5-10 秒
- 輸入延遲（lag）
- 記憶體使用過高
- 與其他 WordPress 外掛衝突

**Why it happens:**
- 載入完整的 Monaco Editor 包含所有語言支援
- 沒有延遲載入（lazy loading）
- 沒有限制檔案大小
- `loader.js` 與其他外掛衝突

**How to avoid:**
1. **延遲載入 Monaco**: 只在編輯器頁面載入，使用 dynamic import
2. **精簡語言支援**: 只載入 PHP、JavaScript、SQL 語言包
3. **限制腳本大小**: 設定上限（如 1MB），超過時顯示警告
4. **使用 Web Worker**: Monaco 的 TypeScript worker 應該在背景執行
5. **避免全域衝突**: 使用 `require.config()` 設定獨立的命名空間

```javascript
// 延遲載入範例
if (document.getElementById('monaco-editor-container')) {
    import('monaco-editor').then((monaco) => {
        // 初始化編輯器
    });
}
```

**Warning signs:**
- 在所有後台頁面都載入 Monaco
- 沒有使用 lazy loading
- 沒有限制腳本大小
- 使用 `require.js` 的全域命名空間

**Phase to address:** Phase 3 (Monaco Integration)

---

### Pitfall 7: Version History Storage Bloat

**Severity:** MEDIUM

**What goes wrong:**
每次儲存都建立完整的版本快照，導致：
- 資料庫快速膨脹（100 次修改 = 100 份完整程式碼）
- 查詢版本列表變慢
- 差異比較（diff）時載入大量資料

**Why it happens:**
- 使用「完整快照」而非「差異儲存」
- 沒有版本清理機制
- 沒有限制版本數量

**How to avoid:**
1. **差異儲存（Delta Compression）**: 只儲存與上一版本的差異
2. **版本數量限制**: 保留最近 50 個版本，或最近 30 天
3. **定期壓縮**: 合併舊版本，只保留里程碑版本
4. **延遲建立版本**: 只在有意義的變更時建立版本（如 30 秒後或手動儲存）

```php
// 版本清理範例
DELETE FROM wp_test_script_versions
WHERE script_id = %d
AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
AND id NOT IN (SELECT id FROM (
    SELECT id FROM wp_test_script_versions
    WHERE script_id = %d
    ORDER BY created_at DESC LIMIT 10
) AS keep_versions)
```

**Warning signs:**
- 儲存完整的 `code` 欄位到版本表
- 沒有版本清理的 cron job
- 沒有版本數量的上限設定

**Phase to address:** Phase 4 (Version History)

---

### Pitfall 8: Long-Running Script Timeout Handling

**Severity:** MEDIUM

**What goes wrong:**
腳本執行超過 PHP `max_execution_time` 限制時：
- 腳本突然中斷，沒有錯誤訊息
- 輸出不完整
- 資料庫 transaction 可能處於不一致狀態
- 使用者不知道發生了什麼

**Why it happens:**
- PHP 預設 30 秒執行限制
- 沒有提供「超時即將發生」的警告
- 沒有使用背景執行機制
- 共享主機無法修改 `max_execution_time`

**How to avoid:**
1. **使用 Action Scheduler**: 長時間腳本透過 WordPress Action Scheduler 背景執行
2. **分段執行**: 大型操作分成多個小批次，透過 AJAX 串接
3. **進度回報**: 定期回報執行進度，讓使用者知道還在運行
4. **Timeout 警告**: 在接近時間限制時發出警告
5. **設定腳本 timeout**: 提供可調整的 timeout 設定（最大 300 秒）

```php
// 進度回報範例
$total = count($items);
foreach ($items as $i => $item) {
    process($item);
    if ($i % 100 === 0) {
        update_progress($script_id, $i / $total * 100);
    }
}
```

**Warning signs:**
- 沒有 timeout 設定選項
- 沒有背景執行機制
- 長時間腳本沒有進度回報
- 依賴主機的 `max_execution_time` 設定

**Phase to address:** Phase 5 (Background Execution)

---

### Pitfall 9: Action Scheduler Queue Bloat

**Severity:** MEDIUM

**What goes wrong:**
使用 Action Scheduler 執行背景腳本時：
- 失敗的 action 不斷重試，佔滿 queue
- `wp_actionscheduler_actions` 和 `wp_actionscheduler_logs` 表膨脹到 GB 等級
- 網站整體效能下降

**Why it happens:**
- 沒有設定失敗重試次數上限
- 沒有清理已完成的 action
- Action 執行失敗後沒有正確標記
- WP-Cron 不穩定導致 action 堆積

**How to avoid:**
1. **限制重試次數**: 設定最大重試次數（如 3 次）
2. **主動清理**: 使用 `action_scheduler_retention_period` filter 縮短保留期間
3. **使用真正的 Cron**: 設定伺服器 cron job 取代 WP-Cron
4. **監控 queue 長度**: 超過閾值時發出警告
5. **執行狀態追蹤**: 在自己的表中追蹤執行狀態，不完全依賴 Action Scheduler

```php
// 清理過期 action
add_filter('action_scheduler_retention_period', function() {
    return DAY_IN_SECONDS; // 1 天後清理
});
```

**Warning signs:**
- 沒有設定 `action_scheduler_retention_period`
- 沒有重試次數限制
- 沒有監控 Action Scheduler 表的大小
- 完全依賴 WP-Cron

**Phase to address:** Phase 5 (Background Execution)

---

### Pitfall 10: Output Buffer Overflow

**Severity:** MEDIUM

**What goes wrong:**
腳本輸出大量資料（如大型 SQL 查詢結果）時：
- PHP 記憶體耗盡
- 瀏覽器 tab crash
- JSON response 太大無法解析
- Monaco Editor 嘗試渲染大量資料時當機

**Why it happens:**
- 沒有限制輸出大小
- 沒有串流輸出機制
- 查詢結果一次全部載入到記憶體
- 前端沒有虛擬捲動（virtual scrolling）

**How to avoid:**
1. **輸出大小限制**: 設定輸出上限（如 5MB），超過時截斷並提示
2. **分頁顯示**: 大量資料使用分頁或虛擬捲動
3. **串流輸出**: 使用 chunked response 或 Server-Sent Events
4. **記憶體監控**: 在腳本中監控 `memory_get_usage()`
5. **SQL 結果限制**: 自動為 SELECT 查詢加上 LIMIT

```php
// 輸出截斷範例
$output = ob_get_clean();
if (strlen($output) > MAX_OUTPUT_SIZE) {
    $output = substr($output, 0, MAX_OUTPUT_SIZE);
    $output .= "\n\n[輸出已截斷，超過 " . MAX_OUTPUT_SIZE . " bytes 限制]";
}
```

**Warning signs:**
- 沒有設定 `MAX_OUTPUT_SIZE` 常數
- 使用 `print_r($large_array)` 而沒有限制
- 前端一次載入所有輸出
- SQL 查詢沒有 LIMIT

**Phase to address:** Phase 2 (Result Display)

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| 使用 `eval()` 執行使用者程式碼 | 實作簡單 | 無法進行程式碼沙盒隔離，安全風險極高 | 僅在開發環境 + 多重驗證 |
| 完整快照版本儲存 | 實作簡單，回溯方便 | 資料庫膨脹 | MVP 階段可接受，後期需優化 |
| 同步執行所有腳本 | 不需要 Action Scheduler | 長時間腳本會 timeout | 僅適用於短腳本（<10秒） |
| 單一 API 端點處理所有操作 | 開發快速 | 難以維護和測試 | Never — 一開始就分離端點 |
| 沒有輸入驗證就儲存 | 開發快速 | SQL injection、XSS 風險 | Never — 必須驗證 |

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| Monaco Editor | 在所有頁面載入完整 bundle | 只在編輯器頁面 lazy load，只載入需要的語言 |
| Action Scheduler | 不設定清理機制 | 設定 retention filter，限制重試次數 |
| WordPress REST API | 只用 capability 驗證 | Capability + Nonce + IP 白名單 |
| wp-load.php | 硬編碼相對路徑 | 多重路徑偵測 + 手動設定選項 |
| 檔案系統寫入 | 假設有寫入權限 | 檢查權限、處理錯誤、提供 fallback |

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| Monaco 全量載入 | 頁面載入 5+ 秒 | Lazy load + 精簡語言包 | 每次頁面載入 |
| 版本表無限增長 | 資料庫查詢變慢 | 定期清理 + 版本數量限制 | 100+ 版本時 |
| 同步執行長腳本 | PHP timeout | Action Scheduler 背景執行 | 執行 >30 秒時 |
| 輸出無限制 | 記憶體溢出 | 輸出大小限制 + 分頁 | 輸出 >10MB 時 |
| 無快取查詢 | 每次請求都查資料庫 | Object cache + Transient | 高頻率操作時 |

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| 只用 `WP_DEBUG` 判斷安全性 | 生產環境開啟 DEBUG 時暴露危險函數 | 多重條件驗證 |
| 輸出結果不 escape | XSS 攻擊 | 使用 `htmlspecialchars()` + Content Security Policy |
| API 沒有 nonce 驗證 | CSRF 攻擊 | 每個請求都驗證 `wp_rest` nonce |
| 允許任意檔案路徑 | 路徑遍歷攻擊 | 限制在特定目錄 + `realpath()` 驗證 |
| 錯誤訊息暴露系統資訊 | 資訊洩漏 | 生產環境隱藏詳細錯誤 |

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| 執行後沒有即時回饋 | 使用者以為卡住了 | 顯示 loading 狀態 + 進度條 |
| 版本回溯沒有確認 | 意外覆蓋當前程式碼 | 彈出確認對話框 + diff 預覽 |
| Auto-save 太頻繁 | 每次打字都建立版本 | Debounce 30 秒 + 只在有意義變更時儲存 |
| 錯誤訊息不清楚 | 使用者不知道如何修正 | 提供具體的錯誤原因和建議 |
| 編輯器設定不持久 | 每次重開都要重設主題/字體大小 | 儲存到 user meta |

## "Looks Done But Isn't" Checklist

- [ ] **執行功能**: 是否有 timeout 處理？是否有輸出大小限制？
- [ ] **版本歷史**: 是否有清理機制？是否有版本數量上限？
- [ ] **背景執行**: 是否有失敗重試限制？是否有進度回報？
- [ ] **安全機制**: 是否在所有 API 驗證 nonce？是否有 IP 白名單？
- [ ] **路徑處理**: 是否處理符號連結？是否有手動設定選項？
- [ ] **錯誤處理**: 是否有 try-catch？是否有 graceful degradation？
- [ ] **效能**: Monaco 是否 lazy load？是否有輸出分頁？

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| 資料庫/檔案不同步 | LOW | 以資料庫為準，重新生成檔案 |
| 版本表膨脹 | MEDIUM | 執行清理 SQL，設定 cron job |
| Action Scheduler 堵塞 | MEDIUM | 清空 queue，修正失敗原因 |
| 安全漏洞被利用 | HIGH | 立即停用外掛、檢查所有腳本、審計 log |
| Monaco 載入失敗 | LOW | Fallback 到 textarea，顯示錯誤訊息 |

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| RCE Without Sandbox | Phase 1: Security Foundation | 嘗試在非開發環境執行危險函數，應被阻擋 |
| XSS Through Output | Phase 2: Result Display | 輸出包含 `<script>` 的結果，應被 escape |
| CSRF on Execution | Phase 1: Security Foundation | 沒有 nonce 的請求應被拒絕 |
| Symlink Path Failure | Phase 1: Core Setup | 在符號連結目錄測試執行 |
| DB/FS Dual-Write Race | Phase 2: Script Storage | 同時發送兩個儲存請求，檢查一致性 |
| Monaco Performance | Phase 3: Monaco Integration | 測量頁面載入時間 <3 秒 |
| Version History Bloat | Phase 4: Version History | 修改 100 次後檢查資料庫大小 |
| Script Timeout | Phase 5: Background Execution | 執行 60 秒腳本，應正常完成 |
| Action Scheduler Bloat | Phase 5: Background Execution | 檢查 30 天後的表大小 |
| Output Buffer Overflow | Phase 2: Result Display | 執行產生 10MB 輸出的腳本，應被截斷 |

## Sources

- [WordPress Vulnerabilities Database 2026](https://wpsecurityninja.com/wordpress-vulnerabilities-database/)
- [WPBeginner - WordPress Security](https://www.wpbeginner.com/wordpress-security/)
- [WordPress Developer Handbook - Hardening](https://developer.wordpress.org/advanced-administration/security/hardening/)
- [Smashing Magazine - PHP Functions That Make Site Insecure](https://www.smashingmagazine.com/2018/01/php-wordpress-functions-site-insecure/)
- [Action Scheduler FAQ](https://actionscheduler.org/faq/)
- [Monaco Editor GitHub Issues](https://github.com/microsoft/monaco-editor/issues)
- [WordPress Trac #16199 - Symlink Issues](https://core.trac.wordpress.org/ticket/16199)
- [WordPress Trac #25623 - Race Conditions](https://core.trac.wordpress.org/ticket/25623)
- [WPShout - Avoiding PHP Timeout with Ajax](https://wpshout.com/beyond-avoiding-php-timeout-memory-limit-errors-ajax/)
- [Git Packfiles Documentation](https://git-scm.com/book/en/v2/Git-Internals-Packfiles)
- [WordPress Developer Blog - Using Nonces Properly](https://developer.wordpress.org/news/2023/08/understand-and-use-wordpress-nonces-properly/)

---
*Pitfalls research for: WordPress Test Script Manager*
*Researched: 2026-01-30*
