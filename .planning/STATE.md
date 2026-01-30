# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-30)

**Core value:** 讓開發者能在 WordPress 後台一鍵編寫並執行測試腳本，立即看到格式化的結果，無需離開瀏覽器或處理檔案路徑問題
**Current focus:** Phase 6 - Version History (VERIFIED ✓)

## Current Position

Phase: 6 of 7 (Version History) - VERIFIED ✓
Plan: 2 of 2 in current phase - COMPLETE
Status: Phase 6 verified (5/5 success criteria ✓), ready for Phase 7
Last activity: 2026-01-30 — Verified Phase 6 goal achievement

Progress: [█████████░] 86% overall (6 of 7 phases)

## Performance Metrics

**Velocity:**
- Total plans completed: 17
- Average duration: 8.8 min
- Total execution time: 149 min

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 01-security-foundation | 2 | 12 min | 6 min |
| 02-script-crud-storage | 3 | 47 min | 16 min |
| 03-monaco-editor-integration | 3 | 46 min | 15.3 min |
| 04-execution-engine | 4 | 28 min | 7 min |
| 05-background-execution | 3 | 9 min | 3 min |
| 06-version-history | 2 | 7 min | 3.5 min |

**Recent Trend:**
- Last 5 plans: 05-02 (3 min), 05-03 (3 min), 06-01 (3 min), 06-02 (4 min)
- Trend: Maintaining efficient sub-5-minute execution for focused plans

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
| 10MB max output size | 04-01 | Prevent memory exhaustion from infinite loops |
| 1-300 second timeout range | 04-01 | Balance quick scripts and long operations |
| Tri-layer error capture | 04-01 | Catch errors/exceptions/fatal without crashing WP |
| Cap execution list limit at 100 | 04-02 | Prevent excessive data transfer |
| LEFT JOIN for script_name | 04-02 | Convenient for frontend display |
| Result page in new tab | 04-03 | Preserve editor state during result viewing |
| Hidden admin menu page for results | 04-03 | Accessible via URL without cluttering menu |
| JSON viewer via CDN | 04-03 | Zero-config collapsible tree display |
| Auto-save before execute | 04-03 | Ensure latest code is executed |
| Table detection only for JSON arrays | 04-04 | Preserves JSON viewer for non-table data |
| Template table check before json/html | 04-04 | Pre-formatted table HTML takes priority |
| Action Scheduler via Composer to includes/libraries | 05-01 | Keep vendor code within plugin structure |
| Fatal errors (E_ERROR, E_PARSE, etc.) skip retry | 05-01 | These errors won't succeed on retry |
| 5-minute retry delay, max 3 retries | 05-01 | Balance retry responsiveness with server load |
| Admin notices stored in transients (1 hour expiry) | 05-02 | Per-user notification queue |
| Plain text email for notifications | 05-02 | Better email deliverability |
| Status polling endpoint with is_cancellable flag | 05-02 | UI can show/hide cancel button based on status |
| Execute buttons grouped in container | 05-03 | Visual cohesion for related actions |
| Mode icons: cloud (background), lightning (sync) | 05-03 | Intuitive visual metaphors |
| Cancel confirmation dialog | 05-03 | Prevent accidental cancellation |
| Inline notices auto-dismiss after 5 seconds | 05-03 | Balance visibility with reduced clutter |
| Retention policy: 50 versions OR 30 days | 06-01 | Dual-threshold keeps more data, configurable via filters |
| Version snapshot only on code change | 06-01 | Prevents duplicate snapshots when updating metadata |
| Daily WP-Cron for cleanup | 06-01 | Better performance than cleanup on each save |
| User attribution via get_current_user_id() ?: null | 06-01 | Handles both logged-in users and background processes |
| Compare API accepts 'current' or version ID | 06-02 | Flexible diff combinations (old vs current, old vs old) |
| Monaco Diff Editor uses synced theme | 06-02 | Consistent visual experience with main editor |
| Side-by-side/inline mode preference persisted | 06-02 | User preference saved in localStorage |
| Version history lifecycle integrated into edit mode | 06-02 | Clean resource management, prevents memory leaks |

Pending:
- None

### Pending Todos

None.

### Blockers/Concerns

From research:
- ~~Symlink path resolution must be solved in Phase 1~~ **RESOLVED in 01-01** - TSM_PLUGIN_DIR works correctly
- ~~Monaco Editor bundle size (>2MB) requires lazy loading~~ **RESOLVED in 03-01** - CDN loading with AMD loader
- ~~Action Scheduler may conflict with FluentCart's bundled version~~ **RESOLVED in 05-01** - Using 3.9.3 via Composer with function_exists() check

## Session Continuity

Last session: 2026-01-30 (UTC+8)
Stopped at: Phase 6 verified (5/5 success criteria ✓)
Resume file: None

**Phase 6 完成並驗證通過 (5/5 ✓):**
- ✅ Auto-snapshot before each save (06-01)
- ✅ Version history sidebar with timestamps (06-02)
- ✅ Monaco Diff Editor with added/removed highlighting (06-02)
- ✅ One-click restore with confirmation (06-02)
- ✅ Auto-cleanup with dual retention policy (06-01)
- ✅ Phase goal verified: Version tracking, diff comparison, rollback ✓

**Key achievement:** Users can now track every script change with automatic snapshots, compare versions side-by-side with Monaco Diff Editor, and restore previous versions with one click. Old versions automatically cleaned up (50 versions OR 30 days retention).

---
*Next step: Plan Phase 7 (Output Enhancements & Polish)*
