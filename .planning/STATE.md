# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-30)

**Core value:** 讓開發者能在 WordPress 後台一鍵編寫並執行測試腳本，立即看到格式化的結果，無需離開瀏覽器或處理檔案路徑問題
**Current focus:** Phase 3 - Monaco Editor Integration (COMPLETE)

## Current Position

Phase: 3 of 7 (Monaco Editor Integration) - COMPLETE
Plan: 3 of 3 in current phase - COMPLETE
Status: Phase 3 complete, ready for Phase 4
Last activity: 2026-01-30 — Completed 03-03-PLAN.md (Theme switching and verification)

Progress: [███████░░░] 67%

## Performance Metrics

**Velocity:**
- Total plans completed: 8
- Average duration: 13.1 min
- Total execution time: 105 min

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 01-security-foundation | 2 | 12 min | 6 min |
| 02-script-crud-storage | 3 | 47 min | 16 min |
| 03-monaco-editor-integration | 3 | 46 min | 15.3 min |

**Recent Trend:**
- Last 5 plans: 02-03 (33 min), 03-01 (15 min), 03-02 (12 min), 03-03 (19 min)
- Trend: Consistent pace, checkpoint validation adds thoroughness

*Updated after each plan completion*

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions affecting current work:

| Decision | Phase | Rationale |
|----------|-------|-----------|
| TSM namespace for all PHP classes | 01-01 | Clear separation, consistent naming |
| Database version in tsm_db_version option | 01-01 | Standard WordPress pattern |
| Tables preserved on deactivation | 01-01 | User data retention |
| plugin_dir_path() for TSM_PLUGIN_DIR | 01-01 | Symlink-safe path resolution |
| Security methods all static | 01-02 | Utility class pattern, no state needed |
| CodeScanner uses token_get_all | 01-02 | More accurate than regex, ignores comments/strings |
| Dangerous functions allowed when WP_DEBUG=true | 01-02 | Dev flexibility without compromising production |
| Admin menu slug: test-script-manager | 01-02 | Consistent with plugin name |
| Scripts dir at {ABSPATH}/test-scripts/ | 02-01 | Browser-accessible, wp-load.php relative path works |
| File header with wp-load.php | 02-01 | Scripts execute with full WordPress environment |
| Rollback on file failure | 02-01 | Maintain data consistency, prevent orphan records |
| Array callback for permission_callback | 02-02 | WordPress standard pattern for class methods |
| jQuery for AJAX handling | 02-03 | WordPress core includes jQuery, no extra dependencies |
| Client-side search implementation | 02-03 | Fast response, reduces server requests |
| Auto-slug generation from name | 02-03 | Reduces user input, ensures correct format |
| Monaco Editor v0.55.1 via CDN | 03-01 | Latest stable, jsDelivr for reliability |
| AMD loader pattern for Monaco | 03-01 | WordPress compatibility, avoid ESM issues |
| vs-dark theme default | 03-01 | Developer-friendly dark theme |
| isLoadingScript flag | 03-01 | Prevent auto-save trigger during setValue() |
| Curated WP function list (35+) | 03-02 | Avoid bloat, focus on most-used functions |
| 3 second auto-save delay | 03-02 | Balance responsiveness with API load |
| Smart $wpdb-> prefix detection | 03-02 | Only show database methods after $wpdb-> |

Pending:
- Action Scheduler for background jobs

### Pending Todos

None.

### Blockers/Concerns

From research:
- ~~Symlink path resolution must be solved in Phase 1~~ **RESOLVED in 01-01** - TSM_PLUGIN_DIR works correctly
- ~~Monaco Editor bundle size (>2MB) requires lazy loading~~ **RESOLVED in 03-01** - CDN loading with AMD loader
- Action Scheduler may conflict with FluentCart's bundled version

## Session Continuity

Last session: 2026-01-30 (UTC+8)
Stopped at: Completed 03-02-PLAN.md (WordPress autocomplete, auto-save, shortcuts)
Resume file: None

**Phase 3 完成總結:**
- ✅ Monaco CDN 載入 (03-01)
- ✅ WordPress 自動完成和快捷鍵 (03-02)
- ✅ 主題切換和驗證 (03-03)
- ✅ Bug fix: 建立表單 Monaco 初始化 (commit b06ba46)
- ⚠️ 已知小問題: WordPress 自動完成需手動觸發 (Ctrl+Space)

---
*Next step: Plan and execute Phase 4 (Execution Engine)*
