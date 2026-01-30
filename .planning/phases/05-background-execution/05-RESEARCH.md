# Phase 5: Background Execution - Research

**Researched:** 2026-01-30
**Domain:** WordPress Background Job Processing with Action Scheduler
**Confidence:** HIGH

## Summary

此階段需要實作背景執行功能，讓長時間執行的測試腳本可以在背景運行。研究結果確認 **Action Scheduler** 是 WordPress 生態系統中最成熟、最可靠的背景任務處理方案，由 WooCommerce 團隊維護，每月處理數百萬筆任務。

Action Scheduler 提供完整的 API 用於排程、執行、取消和追蹤任務，並有內建的管理介面和日誌系統。其自動版本解析機制可確保多個外掛使用不同版本時載入最新版本。

研究也確認了 WordPress 原生的 Admin Notice 和 `wp_mail()` 機制足以滿足通知需求，不需要額外的第三方套件。

**Primary recommendation:** 使用 Action Scheduler 3.9.x 作為背景執行引擎，透過 Composer 或 Git Subtree 引入，並實作自訂的重試邏輯以滿足「最多 3 次重試」的需求。

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Action Scheduler | 3.9.3 | 背景任務排程與執行 | WooCommerce 官方維護，每月處理數百萬任務，自動版本解析 |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| WordPress Transients API | Core | 跨頁面 Admin Notice 持久化 | 背景執行完成後顯示通知 |
| WordPress wp_mail() | Core | Email 通知 | 背景執行完成時發送郵件 |
| wp_admin_notice() | 6.4+ | 程式化建立 Admin Notice | 比直接輸出 HTML 更安全 |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Action Scheduler | WP-Cron | WP-Cron 不可靠、無追蹤、無重試機制 |
| Action Scheduler | 自建任務佇列 | 自建需處理並發、失敗重試、資料庫鎖定等複雜問題 |
| Transients | User Meta | User Meta 永久保存，需手動清理；Transients 自動過期 |

**Installation:**
```bash
# 方法 1: Composer (推薦)
composer require woocommerce/action-scheduler:^3.9

# 方法 2: Git Subtree
git remote add -f action-scheduler https://github.com/woocommerce/action-scheduler.git
git subtree add --prefix=includes/libraries/action-scheduler action-scheduler trunk --squash
```

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── services/
│   ├── class-background-execution-service.php  # 背景執行核心邏輯
│   └── class-notification-service.php          # 通知邏輯（Admin Notice + Email）
├── api/
│   └── class-background-api.php                # REST API for 背景執行狀態
├── libraries/
│   └── action-scheduler/                       # Action Scheduler (if bundled)
└── class-database.php                          # 新增背景執行相關欄位
```

### Pattern 1: Action Scheduler Integration
**What:** 使用 Action Scheduler 排程背景任務
**When to use:** 需要在背景執行長時間運行的 PHP 腳本
**Example:**
```php
// Source: https://actionscheduler.org/api/
// 排程單次背景執行
$action_id = as_schedule_single_action(
    time(),                                    // 立即執行
    'tsm_execute_script_background',           // Hook 名稱
    array(
        'script_id'    => $script_id,
        'execution_id' => $execution_id,
        'user_id'      => get_current_user_id(),
        'attempt'      => 1,
    ),
    'test-script-manager'                      // Group 名稱
);

// 監聽執行 Hook
add_action( 'tsm_execute_script_background', function( $args ) {
    $service = new BackgroundExecutionService();
    $service->execute( $args );
}, 10, 1 );
```

### Pattern 2: Status Tracking via Database
**What:** 在 `tsm_execution_logs` 表中追蹤背景執行狀態
**When to use:** 需要查詢執行狀態和重試次數
**Example:**
```php
// Source: Existing ExecutionService pattern
// 新增欄位支援背景執行
ALTER TABLE wp_tsm_execution_logs ADD COLUMN (
    execution_mode VARCHAR(20) DEFAULT 'sync',     -- 'sync' | 'background'
    action_id BIGINT(20) UNSIGNED DEFAULT NULL,    -- Action Scheduler action ID
    retry_count TINYINT(3) UNSIGNED DEFAULT 0,     -- 重試次數
    scheduled_at DATETIME DEFAULT NULL,            -- 排程時間
    started_at DATETIME DEFAULT NULL               -- 實際開始執行時間
);
```

### Pattern 3: Retry with Fatal Error Detection
**What:** 智慧重試機制，偵測 Fatal Error 時不重試
**When to use:** 失敗後決定是否重試
**Example:**
```php
// Source: Based on user decisions in CONTEXT.md
public function handle_execution_failure( $execution_id, $error_type ) {
    // Fatal errors should not be retried
    $non_retryable_errors = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR );

    if ( in_array( $error_type, $non_retryable_errors, true ) ) {
        $this->mark_as_final_failure( $execution_id );
        $this->send_failure_notification( $execution_id );
        return;
    }

    $retry_count = $this->get_retry_count( $execution_id );
    if ( $retry_count >= 3 ) {
        $this->mark_as_final_failure( $execution_id );
        $this->send_failure_notification( $execution_id );
        return;
    }

    // Schedule retry in 5 minutes
    as_schedule_single_action(
        time() + 300,  // 5 minutes = 300 seconds
        'tsm_execute_script_background',
        array(
            'execution_id' => $execution_id,
            'attempt'      => $retry_count + 1,
        ),
        'test-script-manager'
    );

    $this->update_status( $execution_id, 'retry', $retry_count + 1 );
}
```

### Pattern 4: Admin Notice with Transients
**What:** 使用 Transients 實現跨頁面的 Admin Notice
**When to use:** 背景執行完成後通知使用者
**Example:**
```php
// Source: https://developer.wordpress.org/reference/hooks/admin_notices/
// 儲存通知（執行完成時呼叫）
function tsm_store_completion_notice( $user_id, $execution_id, $status ) {
    $notices = get_transient( 'tsm_admin_notices_' . $user_id ) ?: array();
    $notices[] = array(
        'execution_id' => $execution_id,
        'status'       => $status,  // 'success' | 'failed'
        'time'         => time(),
    );
    set_transient( 'tsm_admin_notices_' . $user_id, $notices, HOUR_IN_SECONDS );
}

// 顯示通知
add_action( 'admin_notices', function() {
    $user_id = get_current_user_id();
    $notices = get_transient( 'tsm_admin_notices_' . $user_id );

    if ( empty( $notices ) ) {
        return;
    }

    foreach ( $notices as $notice ) {
        $class = $notice['status'] === 'success' ? 'notice-success' : 'notice-error';
        $url   = admin_url( 'admin.php?page=test-script-manager&execution=' . $notice['execution_id'] );
        ?>
        <div class="notice <?php echo esc_attr( $class ); ?> is-dismissible">
            <p>
                <?php if ( $notice['status'] === 'success' ) : ?>
                    <?php esc_html_e( 'Background script execution completed.', 'test-script-manager' ); ?>
                <?php else : ?>
                    <?php esc_html_e( 'Background script execution failed.', 'test-script-manager' ); ?>
                <?php endif; ?>
                <a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'View Result', 'test-script-manager' ); ?></a>
            </p>
        </div>
        <?php
    }

    // Clear notices after display
    delete_transient( 'tsm_admin_notices_' . $user_id );
});
```

### Anti-Patterns to Avoid
- **直接修改 Action Scheduler 資料表:** 可能導致 fatal errors 和狀態不一致，使用 API 函數操作
- **在背景任務中輸出 HTML:** 背景任務沒有瀏覽器，所有輸出應存入資料庫
- **忽略 Action Scheduler 初始化時機:** 必須在 `init` hook 之後才能呼叫 `as_*` 函數
- **使用 `update-nag` 作為 notice class:** 會導致不正確的 CSS 樣式和語意

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| 背景任務排程 | 自建 WP-Cron jobs | Action Scheduler | 內建重試、日誌、管理介面、並發處理 |
| 任務狀態追蹤 | 自建狀態表 | Action Scheduler + 自訂欄位 | Action Scheduler 已有完整的狀態機 |
| 跨頁面通知 | Session 變數 | Transients API | WordPress 標準做法，自動過期清理 |
| HTML Email | 手寫 HTML | WordPress wp_mail() + ob_start() | WordPress 會處理 headers 和編碼 |

**Key insight:** Action Scheduler 已處理了背景任務的所有複雜問題：並發鎖定、失敗偵測、資源限制、佇列管理。自建方案需要重新發明這些輪子且容易出錯。

## Common Pitfalls

### Pitfall 1: Action Scheduler 版本衝突
**What goes wrong:** 多個外掛使用不同版本的 Action Scheduler，舊版本 (<=3.2.0) 可能被錯誤載入
**Why it happens:** Action Scheduler 3.2.0 及更早版本有版本解析 bug
**How to avoid:**
- 使用 3.9.x 版本
- 使用 `function_exists()` 檢查關鍵函數
- 使用 `as_supports()` 檢查版本相容性
**Warning signs:** `as_has_scheduled_action()` 函數不存在（3.3.0+ 才有）

### Pitfall 2: 在 plugins_loaded 之前呼叫 Action Scheduler
**What goes wrong:** Fatal error 或函數未定義
**Why it happens:** Action Scheduler 在 `plugins_loaded` priority 0 載入
**How to avoid:**
- 在 `init` hook 或之後呼叫 `as_*` 函數
- 使用 `Action_Scheduler::is_initialized()` 檢查
- 監聽 `action_scheduler_init` hook
**Warning signs:** "Call to undefined function as_schedule_single_action()"

### Pitfall 3: Transients 在 Object Cache 環境消失
**What goes wrong:** Admin Notice 未顯示
**Why it happens:** Persistent object cache 可能在過期前清除 transients
**How to avoid:**
- 程式碼處理 transient 不存在的情況
- 考慮使用 user meta 作為備用（需手動清理）
- 設定合理的過期時間（1 小時足夠）
**Warning signs:** 完成通知有時顯示、有時不顯示

### Pitfall 4: 取消正在執行中的任務
**What goes wrong:** 任務仍繼續執行，只是狀態被改變
**Why it happens:** `as_unschedule_action()` 只取消尚未開始的任務
**How to avoid:**
- 在任務執行邏輯中檢查取消標記
- 使用自訂的 `cancelled` 欄位在資料庫中標記
- 在任務中定期檢查此標記並提前退出
**Warning signs:** 已取消的任務仍有輸出結果

### Pitfall 5: Email 進入垃圾郵件
**What goes wrong:** WordPress 發送的 Email 被標記為垃圾郵件
**Why it happens:** 預設的 `wp_mail()` 使用 PHP mail()，缺少 SPF/DKIM 驗證
**How to avoid:**
- 建議使用者安裝 SMTP 外掛（如 WP Mail SMTP）
- Email 內容簡潔，避免過多連結
- 使用純文字 Email 作為預設
**Warning signs:** 測試 Email 從未收到

## Code Examples

Verified patterns from official sources:

### Schedule Background Execution
```php
// Source: https://actionscheduler.org/api/
/**
 * Schedule a script for background execution.
 *
 * @param int $script_id Script ID to execute.
 * @param int $user_id   User ID who requested execution.
 * @return int|WP_Error Execution ID or error.
 */
public function schedule_background_execution( $script_id, $user_id ) {
    // Create execution log entry first
    $execution_id = $this->create_pending_execution( $script_id, 'background' );

    if ( is_wp_error( $execution_id ) ) {
        return $execution_id;
    }

    // Schedule with Action Scheduler
    $action_id = as_schedule_single_action(
        time(),
        'tsm_execute_script_background',
        array(
            'execution_id' => $execution_id,
            'script_id'    => $script_id,
            'user_id'      => $user_id,
            'attempt'      => 1,
        ),
        'test-script-manager',
        false,  // not unique
        10      // priority
    );

    if ( 0 === $action_id ) {
        return new WP_Error( 'tsm_schedule_failed', 'Failed to schedule background execution.' );
    }

    // Update execution log with action ID
    $this->update_execution_action_id( $execution_id, $action_id );

    return $execution_id;
}
```

### Cancel Background Execution
```php
// Source: https://actionscheduler.org/api/ + custom logic
/**
 * Cancel a background execution.
 *
 * @param int $execution_id Execution ID to cancel.
 * @return bool True on success.
 */
public function cancel_execution( $execution_id ) {
    $execution = $this->get_execution( $execution_id );

    if ( ! $execution || ! in_array( $execution['status'], array( 'pending', 'running', 'retry' ), true ) ) {
        return false;
    }

    // Mark as cancelled in our database
    $this->update_status( $execution_id, 'cancelled' );

    // If pending in Action Scheduler, unschedule it
    if ( $execution['action_id'] && function_exists( 'as_unschedule_action' ) ) {
        as_unschedule_action(
            'tsm_execute_script_background',
            array( 'execution_id' => $execution_id ),
            'test-script-manager'
        );
    }

    return true;
}
```

### Check Cancellation During Execution
```php
// Source: Custom implementation
/**
 * Execute script with cancellation check.
 */
public function execute_with_cancellation_check( $execution_id, $script_id ) {
    // Check if cancelled before starting
    if ( $this->is_cancelled( $execution_id ) ) {
        return;
    }

    $this->update_status( $execution_id, 'running' );

    // Get script and execute
    $script = ScriptService::get( $script_id );
    $file_path = StorageService::get_script_path( $script['slug'] );

    ob_start();

    try {
        include $file_path;
        $output = ob_get_clean();

        // Check cancellation after execution
        if ( $this->is_cancelled( $execution_id ) ) {
            $this->save_partial_result( $execution_id, $output );
            return;
        }

        $this->save_result( $execution_id, $output, 'success' );
        $this->send_completion_notification( $execution_id, 'success' );

    } catch ( \Throwable $e ) {
        $output = ob_get_clean();
        $this->handle_failure( $execution_id, $e, $output );
    }
}
```

### Email Notification
```php
// Source: WordPress Codex + Best Practices
/**
 * Send execution completion email.
 */
public function send_email_notification( $execution_id, $status, $user_id ) {
    $user = get_userdata( $user_id );
    if ( ! $user || ! $user->user_email ) {
        return;
    }

    $execution = $this->get_execution( $execution_id );
    $result_url = admin_url( 'admin.php?page=test-script-manager&execution=' . $execution_id );

    $subject = $status === 'success'
        ? sprintf( '[%s] Script Execution Completed', get_bloginfo( 'name' ) )
        : sprintf( '[%s] Script Execution Failed', get_bloginfo( 'name' ) );

    // Plain text email (better deliverability)
    $message = sprintf(
        "Script: %s\nStatus: %s\nExecution Time: %.2f seconds\n\nView Result: %s",
        $execution['script_name'],
        ucfirst( $status ),
        $execution['execution_time'],
        $result_url
    );

    wp_mail( $user->user_email, $subject, $message );
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| WP-Cron | Action Scheduler | ~2018 | 可靠性大幅提升，WooCommerce 全面採用 |
| Custom post type 儲存任務 | Custom tables | Action Scheduler 3.0 (2020) | 效能提升 10 倍以上 |
| 手動 HTML notice | wp_admin_notice() | WordPress 6.4 | 更安全、更一致的 API |
| 直接 wp_mail() | SMTP plugins | 持續趨勢 | Email 送達率大幅提升 |

**Deprecated/outdated:**
- Action Scheduler 3.2.0 及更早版本: 有版本解析 bug，建議升級到 3.9.x
- 使用 `update-nag` class: WordPress 官方不建議，會導致樣式問題

## Open Questions

Things that couldn't be fully resolved:

1. **FluentCart 是否使用 Action Scheduler？**
   - What we know: 搜尋 fluentcart-payuni 外掛未找到 Action Scheduler 相關程式碼
   - What's unclear: FluentCart 核心是否內建 Action Scheduler
   - Recommendation: 假設可能有衝突，使用 function_exists() 檢查並確保使用 3.9.x 版本

2. **取消執行中任務的完整性**
   - What we know: Action Scheduler 無法真正停止正在執行的 PHP 程式碼
   - What's unclear: 如何確保部分結果完整保存
   - Recommendation: 在程式碼中加入 checkpoint 機制，定期檢查取消標記

3. **Object Cache 環境下 Transients 的可靠性**
   - What we know: Persistent object cache 可能提前清除 transients
   - What's unclear: 實際發生頻率
   - Recommendation: 實作 fallback 到 user meta，或接受偶爾遺失通知

## Sources

### Primary (HIGH confidence)
- [Action Scheduler Official Site](https://actionscheduler.org/) - API reference, usage guide
- [Action Scheduler GitHub](https://github.com/woocommerce/action-scheduler) - Version 3.9.3, source code
- [ActionScheduler_Store Source](https://raw.githubusercontent.com/woocommerce/action-scheduler/trunk/classes/abstracts/ActionScheduler_Store.php) - Status constants
- [WordPress admin_notices Hook](https://developer.wordpress.org/reference/hooks/admin_notices/) - Official documentation

### Secondary (MEDIUM confidence)
- [WooCommerce Best Practices for Action Scheduler](https://developer.woocommerce.com/2021/10/12/best-practices-for-deconflicting-different-versions-of-action-scheduler/) - Version conflict resolution
- [WordPress Transients API](https://developer.wordpress.org/apis/transients/) - Official documentation
- [wp-transient-admin-notices GitHub](https://github.com/wpscholar/wp-transient-admin-notices) - Community pattern

### Tertiary (LOW confidence)
- Web search results for FluentCart + Action Scheduler - No direct evidence found

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Action Scheduler 是 WooCommerce 官方維護，有完整文件
- Architecture: HIGH - 基於官方 API 和既有程式碼架構
- Pitfalls: MEDIUM - 部分來自社群經驗和推斷

**Research date:** 2026-01-30
**Valid until:** 30 days (Action Scheduler 更新頻率約 2-3 個月)
