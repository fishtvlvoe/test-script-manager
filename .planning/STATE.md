# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-30)

**Core value:** 讓開發者能在 WordPress 後台一鍵編寫並執行測試腳本，立即看到格式化的結果，無需離開瀏覽器或處理檔案路徑問題
**Current focus:** Phase 2 - Script CRUD Storage (COMPLETE)

## Current Position

Phase: 2 of 7 (Script CRUD Storage) - COMPLETE
Plan: 2 of 2 in current phase - COMPLETE
Status: Phase complete, ready for Phase 3
Last activity: 2026-01-30 — Completed 02-02-PLAN.md (Scripts REST API endpoints)

Progress: [████░░░░░░] 29%

## Performance Metrics

**Velocity:**
- Total plans completed: 4
- Average duration: 7 min
- Total execution time: 26 min

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 01-security-foundation | 2 | 12 min | 6 min |
| 02-script-crud-storage | 2 | 14 min | 7 min |

**Recent Trend:**
- Last 5 plans: 01-01 (5 min), 01-02 (7 min), 02-01 (8 min), 02-02 (6 min)
- Trend: Stable

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

Pending:
- Monaco Editor via CDN vs Webpack
- Action Scheduler for background jobs

### Pending Todos

None.

### Blockers/Concerns

From research:
- ~~Symlink path resolution must be solved in Phase 1~~ **RESOLVED in 01-01** - TSM_PLUGIN_DIR works correctly
- Monaco Editor bundle size (>2MB) requires lazy loading
- Action Scheduler may conflict with FluentCart's bundled version

## Session Continuity

Last session: 2026-01-30 14:45 (UTC+8)
Stopped at: Completed 02-02-PLAN.md
Resume file: None

---
*Next step: Execute Phase 3 (Script Execution Engine)*
