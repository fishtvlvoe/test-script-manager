# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-30)

**Core value:** 讓開發者能在 WordPress 後台一鍵編寫並執行測試腳本，立即看到格式化的結果，無需離開瀏覽器或處理檔案路徑問題
**Current focus:** Phase 3 - Monaco Editor Integration (In Progress)

## Current Position

Phase: 3 of 7 (Monaco Editor Integration) - IN PROGRESS
Plan: 1 of 3 in current phase - COMPLETE
Status: 03-01 complete, ready for 03-02 (auto-save and shortcuts)
Last activity: 2026-01-30 — Completed 03-01-PLAN.md (Monaco CDN loading)

Progress: [█████░░░░░] 40%

## Performance Metrics

**Velocity:**
- Total plans completed: 6
- Average duration: 12.3 min
- Total execution time: 74 min

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 01-security-foundation | 2 | 12 min | 6 min |
| 02-script-crud-storage | 3 | 47 min | 16 min |
| 03-monaco-editor-integration | 1 | 15 min | 15 min |

**Recent Trend:**
- Last 5 plans: 02-01 (8 min), 02-02 (6 min), 02-03 (33 min), 03-01 (15 min)
- Trend: 03-01 returned to normal pace

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

Last session: 2026-01-30 18:06 (UTC+8)
Stopped at: Completed 03-01-PLAN.md (Monaco CDN loading)
Resume file: None

**Phase 3 進度:**
- ✅ Monaco CDN 載入 (03-01)
- ⬜ Auto-save 和快捷鍵 (03-02)
- ⬜ Theme 切換和進階功能 (03-03)

---
*Next step: Execute 03-02-PLAN.md (Auto-save and keyboard shortcuts)*
