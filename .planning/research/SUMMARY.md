# Project Research Summary

**Project:** WordPress Test Script Manager
**Domain:** WordPress Admin Development Tools (Code Editor & Script Execution)
**Researched:** 2026-01-30
**Confidence:** HIGH

## Executive Summary

WordPress developers currently use fragmented tools for testing and debugging: phpMyAdmin for SQL queries, manual PHP files for script execution, WP-CLI for automation, and separate code editors. The Test Script Manager consolidates these workflows into a single WordPress admin interface powered by Monaco Editor (the VS Code engine) with Action Scheduler for background execution.

The recommended approach prioritizes developer experience through Monaco Editor's VS Code-quality editing, WordPress ecosystem compatibility via standard REST API patterns and Action Scheduler, and security through layered authentication appropriate for development tools. The stack uses Monaco Editor 0.55.x with AMD loader (avoiding Webpack complexity), @wordpress/scripts for build tooling, Action Scheduler 3.9.x for background jobs, and dual-write storage (database + filesystem) for both queryability and direct execution.

Key risks center on security (RCE exposure, XSS through output, CSRF) and performance (Monaco bundle size, version history bloat, long-running script timeouts). These are mitigated through multi-layer authentication (nonce + capability + environment checks), output sanitization with CSP, lazy-loading Monaco only on editor pages, version cleanup automation, and Action Scheduler for async execution. The architecture follows WordPress plugin patterns with clear service/API separation, making it testable and maintainable.

## Key Findings

### Recommended Stack

The stack balances developer experience, WordPress compatibility, and reliability. Monaco Editor provides VS Code-quality editing with syntax highlighting for PHP/SQL/JS/CSS and multi-cursor support without requiring complex Webpack configuration. Action Scheduler handles background execution at 10,000+ jobs/hour scale with built-in retry logic and admin UI, far superior to WP-Cron's page-visit-triggered, silent-fail approach. WordPress REST API with proper nonce/capability authentication provides secure endpoints, while custom database tables enable efficient querying and version history that wp_options or post meta cannot match.

**Core technologies:**
- **Monaco Editor 0.55.1**: VS Code editing engine — same syntax highlighting, autocomplete, and multi-cursor as professional IDE, loaded via CDN to avoid Webpack complexity
- **Action Scheduler 3.9.3**: Background job queue — proven at 10,000+ jobs/hour with retry logic and monitoring UI, bundled via Composer with "first loaded wins" pattern
- **@wordpress/scripts 31.4.0**: Official WordPress build tooling — preconfigured Webpack + Babel for asset pipeline with hot reload and minification
- **Custom database tables**: Structured storage — enables efficient querying, version history, and indexing that post meta or wp_options cannot provide
- **WordPress REST API**: Standardized endpoints — namespace pattern (tsm/v1) with capability-based permission callbacks and nonce verification

### Expected Features

Research reveals users expect basic code editor features (syntax highlighting, autocomplete, undo/redo) and script management (save/load/execute) as table stakes — anything less and developers will stick with phpMyAdmin + manual PHP files. Differentiators include WordPress-aware autocomplete, version history with rollback, and background execution for long-running scripts. The MVP focuses on immediate execution with Monaco Editor integration, while deferring complex features like breakpoint debugging and AI code generation that add substantial complexity without core value.

**Must have (table stakes):**
- Syntax highlighting and line numbers — every modern code editor has this; users can't read unformatted code
- Code execution with formatted output — core product function; without this it's just an editor
- Error display with line numbers — debugging is impossible without knowing where errors occur
- Multi-language support (PHP, SQL, JS, CSS) — WordPress development involves multiple languages
- Script save/load/list — users expect to reuse code
- Basic autocomplete — modern editors suggest methods after `$wpdb->`
- SQL result display as table — phpMyAdmin shows tables; anything less is regression
- Admin-only access with nonce verification — script execution is dangerous; must verify user intent

**Should have (competitive):**
- WordPress-aware autocomplete — suggests WP hooks, functions, $wpdb methods unique to this tool
- Version history with diff view — Git-like experience without leaving WordPress; SnipVault does this
- One-click rollback — undo mistakes instantly
- Background execution for long scripts — WP-CLI can do long scripts; GUI tools can't without this
- Multiple editor tabs — work on several scripts simultaneously like WPIDE
- Export results (CSV, JSON, Excel) — phpMyAdmin does CSV; we do better with JSON/Excel
- Script categories/tags — organize scripts by purpose (DB, API, testing)
- Execution history log — audit trail showing what ran, when, by whom

**Defer (v2+):**
- Breakpoint debugging — requires Xdebug integration; very high complexity
- Script scheduling — cron complexity with edge cases
- REST API testing interface — separate product category
- AI code generation — API costs, privacy concerns, not table stakes yet
- Theme/plugin file editing — massive security risk; one wrong save can brick site

### Architecture Approach

The architecture uses a layered service pattern with clear separation of concerns: presentation layer (Monaco Editor, Script List, Output Panel), REST API layer (Scripts API, Execution API, Versions API), service layer (Script Service, Execution Service, Version Service), and storage layer (database, filesystem, Action Scheduler). Dual-write storage (database + filesystem) provides both queryability and direct execution — database is source of truth, filesystem is secondary mirror. Version snapshots are created before each save with cleanup automation to prevent bloat. Scripts execute in isolated context with output buffering, timeout handling, and proper error capture.

**Major components:**
1. **Monaco Editor Integration** — AMD loader via CDN (not Webpack) loads editor from jsDelivr with language-specific web workers, avoiding bundle complexity while providing VS Code-quality editing
2. **Dual-Write Storage System** — Script Service writes to database (primary, queryable) and filesystem (secondary, executable) with database as source of truth; filesystem failures log warnings but don't fail saves
3. **Isolated Execution Engine** — Execution Service runs user code in closure with output buffering, timeout limits, try/catch for all Throwables, and memory/time stats collection; no true PHP sandboxing but layered security controls
4. **Action Scheduler Background Jobs** — Long-running scripts queue through Action Scheduler with job UUID tracking, status polling API, and automatic retry on failure; avoids PHP/browser timeout issues
5. **Version History Service** — Creates immutable snapshots before overwrites, stores full content (not diffs) with automated cleanup keeping last 50 versions or 30 days; enables restore and diff comparison

### Critical Pitfalls

Research identified 10 major pitfalls with varying severity. The top 5 require specific architectural decisions and prevention strategies built into each phase.

1. **Remote Code Execution Without Sandbox (CRITICAL)** — eval() with any user code requires multi-layer protection: environment checks beyond WP_DEBUG (check WP_ENVIRONMENT_TYPE, IP whitelist, additional password), function monitoring (log dangerous functions like exec/system), and explicit warnings before execution. Prevention must be in Phase 1 before any execution capability.

2. **XSS Through Script Output Display (CRITICAL)** — Script results can contain user-controlled HTML/JavaScript if querying database with user input. Prevent with output sanitization (htmlspecialchars() for text, DOMPurify for HTML, JSON viewer for JSON), Content Security Policy headers, and sandboxed iframe for result display. Address in Phase 2 when implementing output panel.

3. **CSRF on Script Execution API (CRITICAL)** — Attacker-crafted pages could trigger script execution when admin visits. Prevent with strict nonce verification on all endpoints, POST-only for execution, and SameSite=Strict cookies. Verify nonce exists and is valid before any execution. Phase 1 security foundation.

4. **Symlink Path Resolution Failure (HIGH)** — Development using symlinks breaks `__DIR__`/`__FILE__` path resolution to wp-load.php. Prevent with multiple path detection strategies ($_SERVER["DOCUMENT_ROOT"], realpath(), ABSPATH), manual path configuration option, and existence verification before use. Handle in Phase 1 core setup.

5. **Database + Filesystem Dual-Write Race Condition (HIGH)** — Simultaneous saves or DB success with filesystem failure creates inconsistency. Prevent by defining database as source of truth, using optimistic locking (version column), adding checksum verification, and treating filesystem as non-critical mirror. Address in Phase 2 script storage implementation.

Additional notable pitfalls: Monaco Editor performance degradation (lazy load only on editor page, limit languages), version history storage bloat (cleanup automation, delta compression), long-running script timeout handling (Action Scheduler queue), Action Scheduler queue bloat (retention filters, retry limits), and output buffer overflow (size limits, truncation warnings).

## Implications for Roadmap

Based on research, the project naturally divides into 7 phases driven by architectural dependencies and pitfall mitigation requirements. Security foundation and core storage must come first, followed by editor integration, execution capability, and finally enhancement features.

### Phase 1: Security Foundation & Core Setup
**Rationale:** All execution features depend on having security boundaries in place first. Path resolution issues block everything, so solve early.

**Delivers:**
- Database schema (scripts + versions + execution_logs tables via dbDelta)
- Plugin activation/deactivation hooks
- Security layer (multi-layer auth: capability + nonce + environment checks + IP whitelist)
- Path resolution with symlink handling (multiple detection strategies + manual config)
- Basic admin page registration

**Addresses:**
- FEATURES.md: Admin-only access, nonce verification (table stakes)
- PITFALLS.md: RCE prevention, CSRF protection, symlink path failures

**Avoids:**
- Pitfall #1 (RCE) — establishes security boundaries before any code execution
- Pitfall #3 (CSRF) — implements nonce verification architecture
- Pitfall #4 (Symlink) — solves path resolution before script execution needs it

**Research Flag:** Standard WordPress plugin activation patterns — no additional research needed.

### Phase 2: Script CRUD & Storage
**Rationale:** Need to create and manage scripts before editing or executing them. Dual-write architecture must be established early as it affects all later features.

**Delivers:**
- Script Service (CRUD operations, dual-write to DB + filesystem)
- Scripts REST API (CRUD endpoints with proper authentication)
- Basic admin UI (script list view, create, delete)
- Output sanitization and XSS prevention architecture
- Database as source of truth pattern with filesystem mirror

**Addresses:**
- FEATURES.MD: Script save/load/list (table stakes)
- PITFALLS.md: DB/filesystem race conditions, XSS through output

**Uses:**
- STACK.md: Custom database tables, WordPress REST API, $wpdb prepared statements

**Implements:**
- ARCHITECTURE.md: Dual-Write Storage pattern, Script Service with optimistic locking

**Avoids:**
- Pitfall #5 (Dual-write race) — implements source of truth pattern with version column
- Pitfall #2 (XSS) — establishes output sanitization architecture

**Research Flag:** Standard WordPress REST API patterns — no additional research needed.

### Phase 3: Monaco Editor Integration
**Rationale:** Core value proposition is the code editor. Depends on Phase 2 for data persistence. Performance pitfalls require lazy loading architecture.

**Delivers:**
- Monaco Editor 0.55.1 integration via AMD loader (CDN approach, not Webpack)
- Language support configuration (PHP, JavaScript, SQL, CSS with appropriate workers)
- Edit screen with save functionality
- @wordpress/scripts build pipeline for remaining assets
- Lazy loading (only on editor page, not all admin pages)

**Addresses:**
- FEATURES.md: Syntax highlighting, line numbers, multi-language support, basic autocomplete, undo/redo (all table stakes)
- PITFALLS.md: Monaco performance degradation

**Uses:**
- STACK.md: Monaco Editor via jsDelivr CDN, @wordpress/scripts build tooling
- ARCHITECTURE.md: Monaco AMD loader pattern with web workers

**Avoids:**
- Pitfall #6 (Monaco performance) — lazy load only on editor page, limit to 4 languages
- Webpack complexity — use CDN approach per STACK.md recommendation

**Research Flag:** Monaco Editor AMD integration may need specific CDN configuration research if issues arise, but well-documented in STACK.md.

### Phase 4: Execution Engine
**Rationale:** Second core value prop is running scripts. Depends on Phases 1 (security) and 2 (storage). Enables immediate testing workflow.

**Delivers:**
- Execution Service (isolated execution with output buffering)
- Execution REST API (immediate execution mode)
- Output Panel UI (text output, table formatting for SQL, JSON viewer)
- Error display with file/line information
- Execution stats (time, memory, peak usage)
- Output size limits and truncation

**Addresses:**
- FEATURES.md: Code execution with output, error display, SQL table display, copy to clipboard (all table stakes)
- PITFALLS.md: Output buffer overflow

**Uses:**
- STACK.md: PHP try/catch for Throwable, output buffering pattern
- ARCHITECTURE.md: Isolated Script Execution pattern

**Implements:**
- ARCHITECTURE.md: Execution Service with closure-based isolation

**Avoids:**
- Pitfall #10 (Output overflow) — implements MAX_OUTPUT_SIZE with truncation warnings
- Global scope pollution — uses closure pattern per ARCHITECTURE.md

**Research Flag:** Standard PHP execution patterns — no additional research needed.

### Phase 5: Background Execution
**Rationale:** Enhancement for long-running scripts. Depends on Phase 4 (execution engine). Solves timeout issues but adds Action Scheduler complexity.

**Delivers:**
- Action Scheduler 3.9.x integration via Composer
- Background execution mode (alternative to immediate)
- Job status polling API (UUID-based tracking)
- Execution queue UI with progress indicators
- Retry logic and failure handling
- Action Scheduler cleanup automation (retention filters, retry limits)

**Addresses:**
- FEATURES.md: Background execution for long scripts (competitive differentiator)
- PITFALLS.md: Long-running script timeouts, Action Scheduler queue bloat

**Uses:**
- STACK.md: Action Scheduler 3.9.3 bundled via Composer
- ARCHITECTURE.md: Action Scheduler Background Jobs pattern

**Implements:**
- ARCHITECTURE.md: Background execution with job metadata storage

**Avoids:**
- Pitfall #8 (Script timeout) — queues long scripts through Action Scheduler
- Pitfall #9 (Queue bloat) — implements retention filters and retry limits

**Research Flag:** Action Scheduler integration patterns well-documented in STACK.md, but monitoring/cleanup strategies may need validation during implementation.

### Phase 6: Version History
**Rationale:** Important safety feature but scripts work without it. Depends on Phase 2 (storage). Bloat prevention requires cleanup automation.

**Delivers:**
- Version Service (snapshot creation, restore, diff generation)
- Versions REST API (list versions, compare, restore)
- Version snapshot automation (before each save)
- Version comparison UI with diff view
- One-click rollback functionality
- Automated version cleanup (keep last 50 versions or 30 days)

**Addresses:**
- FEATURES.md: Version history with diff, one-click rollback (competitive differentiators)
- PITFALLS.md: Version history storage bloat

**Uses:**
- STACK.md: Custom database tables (versions table with foreign key to scripts)
- ARCHITECTURE.md: Version Snapshot on Save pattern

**Implements:**
- ARCHITECTURE.md: Version Service with automated cleanup

**Avoids:**
- Pitfall #7 (Version bloat) — implements automated cleanup with retention policy
- Anti-pattern: No version history — prevents data loss

**Research Flag:** Diff generation algorithms may need library research (PHP diff libraries), but snapshot/restore patterns are standard.

### Phase 7: Output Enhancements & Polish
**Rationale:** Polish features that enhance core execution workflow. Lowest dependency — can be done anytime after Phase 4.

**Delivers:**
- Enhanced table formatting for SQL results
- JSON formatter with collapse/expand tree view
- Export functionality (CSV, JSON, Excel download)
- Copy results to clipboard (all formats)
- Variable inspector with collapsible trees (enhanced var_dump)
- Multiple editor tabs (work on multiple scripts simultaneously)
- Script categories/tags taxonomy
- Quick templates library (common patterns)
- Dark/light theme toggle (already in Monaco, just expose)
- Keyboard shortcuts configuration

**Addresses:**
- FEATURES.md: Export results, multiple tabs, categories, templates, themes (all competitive differentiators)
- UX enhancements from PITFALLS.md

**Uses:**
- STACK.md: Monaco Editor theming API
- Frontend formatting libraries for JSON/CSV/Excel

**Avoids:**
- UX pitfalls: unclear output, no export options, lost context switching

**Research Flag:** Excel export library selection may need research (PHP libraries like PhpSpreadsheet), but other features use standard patterns.

### Phase Ordering Rationale

**Dependency-driven sequencing:**
- Security (Phase 1) → Storage (Phase 2) → Editor (Phase 3) → Execution (Phase 4) follows natural dependency chain
- Background execution (Phase 5) enhances but doesn't block execution (Phase 4)
- Version history (Phase 6) enhances but doesn't block storage (Phase 2)
- Polish (Phase 7) has minimal dependencies, can be parallelized

**Pitfall-driven early phases:**
- RCE, CSRF, Symlink (all CRITICAL/HIGH) must be solved in Phase 1 before any execution
- Dual-write race condition (HIGH) must be solved in Phase 2 before editor generates saves
- XSS through output (CRITICAL) architecture established in Phase 2, verified in Phase 4

**Value delivery pattern:**
- Phase 1-4 delivers MVP: secure script editing and immediate execution
- Phase 5-6 adds professional features: background execution and version control
- Phase 7 adds polish and competitive differentiators

**Architecture pattern alignment:**
- Service layer (Phases 2, 4, 6) separated from API layer for testability
- Storage patterns (Phase 2) established before consumers (Phases 3-7)
- Security boundaries (Phase 1) enforced by all subsequent phases

### Research Flags

**Phases likely needing deeper research during planning:**
- **Phase 3 (Monaco Integration):** AMD loader CDN configuration if standard jsDelivr approach fails; web worker setup for TypeScript/JSON workers may need troubleshooting
- **Phase 5 (Background Execution):** Action Scheduler edge cases with FluentCart's existing instance; monitoring strategies for queue health
- **Phase 6 (Version History):** PHP diff library selection (php-diff, sebastian/diff, or custom); optimal diff algorithm for code vs. text
- **Phase 7 (Output Enhancements):** Excel export library evaluation (PhpSpreadsheet vs. alternatives); virtual scrolling library for large result sets

**Phases with standard patterns (skip research-phase):**
- **Phase 1 (Security Foundation):** WordPress plugin activation, database creation via dbDelta, REST API authentication — all well-documented
- **Phase 2 (Script CRUD):** Standard WordPress REST CRUD patterns, $wpdb prepared statements — established patterns
- **Phase 4 (Execution Engine):** PHP try/catch, output buffering, execution stats — standard PHP patterns

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | Monaco Editor, Action Scheduler, @wordpress/scripts all verified via npm/GitHub API; versions current as of 2026-01-30; integration patterns documented in official sources |
| Features | HIGH | Extensive competitor analysis (phpMyAdmin, WP-CLI, WPIDE, WPCodeBox) validates feature set; table stakes vs. differentiators clear from user expectations research |
| Architecture | HIGH | Dual-write pattern, version snapshot, isolated execution, and Action Scheduler background jobs are proven patterns with documented implementations; component boundaries follow WordPress plugin standards |
| Pitfalls | HIGH | Security pitfalls (RCE, XSS, CSRF) well-documented in WordPress security handbooks; performance pitfalls (Monaco bloat, version bloat, timeout) validated through GitHub issues and community discussion |

**Overall confidence:** HIGH

All four research areas have strong foundations in official documentation, verified package versions, and established community patterns. Monaco Editor and Action Scheduler are mature technologies with clear integration paths. Security pitfalls are well-understood in WordPress context with documented prevention strategies. Architecture patterns follow standard WordPress plugin structure with proven service/API separation.

### Gaps to Address

Despite high overall confidence, several areas need validation during implementation:

- **Monaco Editor CDN stability:** jsDelivr uptime and version pinning strategy — validate CDN availability during Phase 3, establish fallback URL or self-hosted option if CDN fails
- **Action Scheduler version conflict:** FluentCart already bundles Action Scheduler; verify "first loaded wins" pattern works correctly — test in Phase 5 setup to ensure no version mismatch issues
- **PHP execution limits on shared hosting:** Some hosts disable set_time_limit() or have hard limits — provide clear documentation and fallback messaging if background execution isn't available
- **Symlink path variations:** Development environments vary (Local, XAMPP, Docker, Valet) — test path resolution across multiple setups in Phase 1 to ensure broad compatibility
- **Diff algorithm performance:** Large scripts (10,000+ lines) may cause diff generation timeout — benchmark during Phase 6 implementation, potentially limit diff to first N lines or use optimized algorithm
- **Excel export library licensing:** PhpSpreadsheet is LGPL; verify compatibility with plugin licensing — research during Phase 7 planning, potentially use CSV-only as alternative

**Mitigation strategy:** Each gap has a validation point during its relevant phase. Most have clear fallback options (CDN → self-hosted, full diff → truncated diff, Excel → CSV-only). None are blocking issues for MVP (Phases 1-4).

## Sources

### Primary (HIGH confidence)

**Official Documentation:**
- [WordPress Developer: REST API Authentication](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/) — nonce verification patterns
- [WordPress Developer: dbDelta()](https://developer.wordpress.org/reference/functions/dbdelta/) — database table creation
- [WordPress Developer: @wordpress/scripts](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/) — build tooling
- [Monaco Editor GitHub](https://github.com/microsoft/monaco-editor) — integration patterns
- [Action Scheduler Official Site](https://actionscheduler.org/) — usage and performance benchmarks

**Verified Package Versions (via npm CLI / GitHub API):**
- [npm: monaco-editor 0.55.1](https://www.npmjs.com/package/monaco-editor) — verified current version
- [npm: @wordpress/scripts 31.4.0](https://www.npmjs.com/package/@wordpress/scripts) — verified current version
- [GitHub: Action Scheduler 3.9.3](https://github.com/woocommerce/action-scheduler/releases) — release date 2025-07-15

### Secondary (MEDIUM confidence)

**Community Sources & Competitor Analysis:**
- [WPCodeBox](https://wpcodebox.com/) — Monaco Editor integration in WordPress context
- [InstaWP WordPress Code Editor Plugins](https://instawp.com/wordpress-code-editor-plugins/) — feature comparison
- [SnipVault Review](https://wpmayor.com/snipvault-review/) — version history implementation
- [Kinsta: wp-scripts development](https://kinsta.com/blog/wp-scripts-development/) — build patterns
- [Action Scheduler Usage](https://actionscheduler.org/usage/) — background job patterns

**Security & Best Practices:**
- [WordPress Developer Handbook - Hardening](https://developer.wordpress.org/advanced-administration/security/hardening/) — security patterns
- [WordPress Developer Blog - Using Nonces Properly](https://developer.wordpress.org/news/2023/08/understand-and-use-wordpress-nonces-properly/) — CSRF prevention
- [Patchstack: WordPress SQL Injection Prevention](https://patchstack.com/articles/sql-injection/) — database security

### Tertiary (LOW confidence)

**Community Discussion & Edge Cases:**
- [GitHub: monaco-editor ESM discussions](https://github.com/microsoft/monaco-editor/discussions/3771) — CDN loading patterns
- [WordPress Trac #16199 - Symlink Issues](https://core.trac.wordpress.org/ticket/16199) — path resolution edge cases
- [WPShout - Avoiding PHP Timeout with Ajax](https://wpshout.com/beyond-avoiding-php-timeout-memory-limit-errors-ajax/) — execution timeout strategies

---
*Research completed: 2026-01-30*
*Ready for roadmap: yes*
