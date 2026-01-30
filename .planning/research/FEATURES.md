# Feature Research: WordPress Test Script Manager

**Domain:** WordPress admin development tools (code editors, script runners, database query tools)
**Researched:** 2026-01-30
**Confidence:** HIGH (based on extensive competitor analysis and current ecosystem research)

## Feature Landscape

### Table Stakes (Users Expect These)

Features users assume exist. Missing these = product feels incomplete. Developers will use phpMyAdmin, WP-CLI, or manual PHP files instead.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| **Syntax highlighting** | Every modern code editor has this; developers can't read unformatted code | LOW | Monaco Editor provides this out of the box |
| **Multi-language support (PHP, JS, CSS, SQL)** | WordPress development involves multiple languages | LOW | Monaco has built-in support; just enable modes |
| **Line numbers** | Basic navigation and debugging requirement | LOW | Standard Monaco feature |
| **Code execution with output display** | Core product function; without this it's just an editor | MEDIUM | Need safe execution context, output capture |
| **Error display with line numbers** | Debugging is impossible without knowing where errors occur | MEDIUM | Capture PHP errors, format nicely |
| **Basic autocomplete** | Modern editors all have this; typing `$wpdb->` should suggest methods | MEDIUM | Monaco IntelliSense; need WordPress-aware dictionaries |
| **Save/Load scripts** | Users expect to reuse code | LOW | Store in wp_options or custom table |
| **Undo/Redo** | Standard editing feature | LOW | Monaco built-in |
| **Search and replace** | Basic text editing | LOW | Monaco built-in (Ctrl+F, Ctrl+H) |
| **Script list/management** | Need to organize multiple scripts | LOW | Simple CRUD UI |
| **SQL result display as table** | phpMyAdmin shows tables; anything less is regression | MEDIUM | Parse query results, render as HTML table |
| **Copy results to clipboard** | Users need to use results elsewhere | LOW | JavaScript clipboard API |
| **Basic security (nonce verification)** | Script execution is dangerous; must verify user intent | LOW | Standard WordPress practice |
| **Admin-only access** | Only admins should execute arbitrary code | LOW | capability check: `manage_options` |

### Differentiators (Competitive Advantage)

Features that set the product apart from phpMyAdmin, WP-CLI, and manual PHP files. Not required, but valuable.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| **WordPress-aware autocomplete** | Suggests WP hooks, functions, $wpdb methods - unique to this tool | HIGH | Need to parse WordPress codebase or maintain dictionary; see [WPCodeBox](https://wpcodebox.com/) approach |
| **FluentCart/WooCommerce aware** | Plugin-specific completions for common integrations | HIGH | Parse plugin code or maintain curated lists |
| **Version history with diff view** | Git-like experience without leaving WordPress; [SnipVault](https://wpmayor.com/snipvault-review/) does this | MEDIUM | Use WordPress revisions API or custom implementation |
| **One-click rollback** | Undo mistakes instantly | MEDIUM | Store version history, allow restore |
| **Export results (CSV, JSON, Excel)** | phpMyAdmin does CSV; we do better with JSON/Excel | MEDIUM | Add format conversion; common need per [WP All Export](https://wordpress.org/plugins/wp-all-export/) |
| **Background execution for long scripts** | WP-CLI can do long scripts; GUI tools can't | HIGH | Use Action Scheduler or async processing; see [deliciousbrains/wp-background-processing](https://github.com/deliciousbrains/wp-background-processing) |
| **Execution timeout configuration** | Control how long scripts run before kill | MEDIUM | PHP `set_time_limit()` with UI control |
| **Script categories/tags** | Organize scripts by purpose (DB, API, testing) | LOW | Simple taxonomy |
| **Quick templates** | Pre-built scripts for common tasks | LOW | Curated library of useful snippets |
| **Keyboard shortcuts** | Power users expect IDE-like shortcuts | LOW | Monaco built-in + custom bindings |
| **Dark mode / Theme selection** | Developer preference; reduces eye strain | LOW | Monaco has 6+ built-in themes |
| **Multiple editor tabs** | Work on several scripts simultaneously like [WPIDE](https://wordpress.org/plugins/wpide/) | MEDIUM | Tab state management |
| **Script sharing/import/export** | Share scripts between sites or with team | LOW | JSON export/import |
| **Execution history log** | See what ran, when, by whom, with results | MEDIUM | Audit trail for security |
| **Prepared statement helper** | Make SQL safe by auto-wrapping in `$wpdb->prepare()` | MEDIUM | Code transformation; critical for security |
| **Variable inspector/dump** | Nice `var_dump()` with collapsible trees | MEDIUM | Parse PHP output, render as interactive tree |
| **Breakpoint debugging** | Real debugging like Xdebug but in browser | VERY HIGH | Would require PHP extension integration; likely out of scope |
| **Script scheduling** | Run scripts on cron | HIGH | Integrate with WP Cron or Action Scheduler |
| **REST API testing interface** | Test endpoints without Postman | MEDIUM | HTTP client built-in |

### Anti-Features (Commonly Requested, Often Problematic)

Features that seem good but create problems. Explicitly NOT building these.

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| **Theme/Plugin file editing** | "WPIDE does this" | File editing introduces massive security risk; one wrong save can brick site; [WPIDE security concerns](https://wordpress.org/plugins/wpide/) well documented | Keep scripts in database, not touching filesystem |
| **Multi-user collaboration** | "Google Docs for code" | Adds immense complexity (OT/CRDT algorithms, WebSocket); WordPress admin is single-user context | Script export/import for sharing |
| **AI code generation** | "Cursor/Copilot has it" | Requires API costs, rate limiting, privacy concerns, hallucination risks; [not table stakes yet](https://dev.to/farhadrahimiklie/which-code-editor-is-best-for-developers-in-2026-2dn7) | Provide good templates instead |
| **Real-time syntax validation** | "Catch errors before run" | PHP parsing in browser is complex; false positives frustrate users | Good error display after execution |
| **Full IDE with file tree** | "I want VS Code in WordPress" | Scope creep; this is a script runner, not a development environment | Recommend VS Code/Cursor for full development |
| **Automatic database backups before query** | "Safety net" | Performance overhead for every query; complex rollback logic | Warn on destructive queries, manual backup button |
| **Sandboxed execution environment** | "Run untrusted code safely" | True sandboxing in PHP requires separate process/container; [WordPress Playground](https://developer.wordpress.org/news/2026/01/whats-new-for-developers-january-2026/) uses WASM but impractical for our use case | Clear warnings, admin-only access, execution limits |
| **Visual query builder** | "Drag and drop SQL" | Complex UI for marginal benefit; developers know SQL | Provide SQL templates and $wpdb->prepare() helper |
| **Code formatting on save** | "Prettier for PHP" | PHP-CS-Fixer integration is complex; different teams have different standards | Manual formatting, clear style guide in templates |
| **Git integration** | "Push to repo from admin" | Authentication complexity, merge conflicts, out of scope | Export scripts, commit manually |

## Feature Dependencies

```
[Monaco Editor Core]
    |
    |---> [Syntax Highlighting] (immediate)
    |---> [Line Numbers] (immediate)
    |---> [Search/Replace] (immediate)
    |---> [Undo/Redo] (immediate)
    |---> [Basic Autocomplete] (immediate)
    |           |
    |           └──-> [WordPress-aware Autocomplete] (requires dictionary)
    |
    └---> [Multi-language Support] (immediate)

[Script Storage System]
    |
    |---> [Save/Load Scripts] (requires DB table)
    |           |
    |           |---> [Script List/Management] (requires save/load)
    |           |---> [Script Categories/Tags] (enhances management)
    |           |---> [Version History] (requires save/load)
    |           |           |
    |           |           └---> [One-click Rollback] (requires version history)
    |           |           └---> [Diff View] (requires version history)
    |           |
    |           └---> [Script Import/Export] (requires save/load)
    |
    └---> [Quick Templates] (requires storage)

[Execution Engine]
    |
    |---> [Code Execution] (core)
    |           |
    |           |---> [Output Display] (requires execution)
    |           |---> [Error Display] (requires execution)
    |           |---> [Execution Time/Memory Stats] (requires execution)
    |           |---> [Variable Inspector] (enhances output)
    |           |
    |           └---> [Background Execution] (alternative path)
    |                       |
    |                       └---> [Progress Indicator] (requires background)
    |                       └---> [Result Notification] (requires background)
    |
    |---> [SQL Execution]
    |           |
    |           |---> [Table Display] (requires SQL execution)
    |           |---> [Result Export (CSV/JSON)] (requires table display)
    |           |---> [Prepared Statement Helper] (enhances SQL)
    |
    └---> [Execution History Log] (requires execution)

[Security Layer] (must be present for all execution)
    |
    |---> [Admin-only Access]
    |---> [Nonce Verification]
    |---> [Timeout Configuration]
    └---> [Destructive Query Warning]
```

### Dependency Notes

- **Monaco Editor Core** is prerequisite for everything visual - must be loaded and working first
- **Script Storage System** is independent of execution but needed for persistence
- **Execution Engine** can work without storage (run once, discard) but less useful
- **Security Layer** is non-negotiable - every execution path must go through it
- **Background Execution** is complex and can be deferred to post-MVP
- **WordPress-aware Autocomplete** is high value but high complexity - good differentiator for v1.x

## MVP Definition

### Launch With (v1)

Minimum viable product - what's needed to validate the concept and be useful daily.

- [x] **Monaco Editor with PHP/SQL/JS support** - core editing experience
- [x] **PHP code execution with formatted output** - primary use case
- [x] **Error display with file/line info** - debugging essential
- [x] **SQL execution with table display** - second most common use case
- [x] **Basic autocomplete (Monaco defaults)** - expected
- [x] **Script save/load/list** - reusability
- [x] **Copy results to clipboard** - utility
- [x] **CSV export for SQL results** - data portability
- [x] **Admin capability check** - security baseline
- [x] **Nonce verification** - security baseline
- [x] **Dark/Light theme toggle** - developer preference

**MVP Rationale:** These features match what developers currently do with phpMyAdmin + manual PHP files, but in one unified interface. If this isn't useful, additional features won't help.

### Add After Validation (v1.x)

Features to add once core is working and users are engaged.

- [ ] **Version history with rollback** - trigger: users ask "how do I undo?"
- [ ] **WordPress-aware autocomplete** - trigger: users report typing friction
- [ ] **Script categories/tags** - trigger: users have 10+ scripts
- [ ] **JSON export for SQL results** - trigger: API testing use case grows
- [ ] **Quick templates library** - trigger: users ask for common patterns
- [ ] **Execution timeout config** - trigger: users run long-running scripts
- [ ] **Multiple tabs** - trigger: users want to reference while editing
- [ ] **Execution history log** - trigger: multi-admin sites need audit

### Future Consideration (v2+)

Features to defer until product-market fit is established.

- [ ] **Background execution** - why defer: complex implementation, needs Action Scheduler or async processing
- [ ] **Breakpoint debugging** - why defer: requires external tools (Xdebug), complex integration
- [ ] **Script scheduling** - why defer: cron complexity, edge cases
- [ ] **REST API testing interface** - why defer: separate product category
- [ ] **Plugin-specific autocomplete (FluentCart, WooCommerce)** - why defer: maintenance burden per plugin

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| Monaco Editor + syntax highlighting | HIGH | LOW | P1 |
| PHP code execution | HIGH | MEDIUM | P1 |
| SQL execution + table display | HIGH | MEDIUM | P1 |
| Error display with line numbers | HIGH | MEDIUM | P1 |
| Script save/load | HIGH | LOW | P1 |
| Copy to clipboard | MEDIUM | LOW | P1 |
| CSV export | MEDIUM | LOW | P1 |
| Security (nonce/capability) | HIGH | LOW | P1 |
| Basic autocomplete | MEDIUM | LOW | P1 |
| Dark/Light theme | LOW | LOW | P1 |
| Script list management | MEDIUM | LOW | P1 |
| Version history | HIGH | MEDIUM | P2 |
| WordPress autocomplete | HIGH | HIGH | P2 |
| Script categories | MEDIUM | LOW | P2 |
| Quick templates | MEDIUM | LOW | P2 |
| Multiple tabs | MEDIUM | MEDIUM | P2 |
| JSON export | MEDIUM | LOW | P2 |
| Execution timeout config | MEDIUM | MEDIUM | P2 |
| Execution history | MEDIUM | MEDIUM | P2 |
| Background execution | HIGH | HIGH | P3 |
| Variable inspector | MEDIUM | MEDIUM | P3 |
| REST API testing | MEDIUM | HIGH | P3 |
| Script scheduling | MEDIUM | HIGH | P3 |

**Priority key:**
- P1: Must have for launch (MVP)
- P2: Should have, add after core validated
- P3: Nice to have, future consideration

## Competitor Feature Analysis

| Feature | phpMyAdmin | WP-CLI | WPIDE | WPCodeBox | Our Approach |
|---------|------------|--------|-------|-----------|--------------|
| SQL execution | Excellent | Good (wp db query) | No | No | Match phpMyAdmin + better export |
| PHP execution | No | Yes (wp shell, wp eval) | No | Yes (snippets) | Direct in-browser execution |
| Code editor quality | Basic textarea | N/A (terminal) | CodeMirror | Monaco | Monaco (same as VS Code) |
| WordPress autocomplete | No | Tab completion | Partial | Excellent | Start basic, expand over time |
| Version history | No | No | No | Pro feature | Built-in from v1.x |
| Background execution | N/A | Excellent | No | No | Planned for v2 |
| GUI access | Yes (web) | No (CLI only) | Yes | Yes | Yes (WP admin) |
| Multi-language | SQL only | PHP focused | Yes | Yes | Yes (PHP, SQL, JS, CSS) |
| Output formatting | Table | Text | N/A | Text | Table + JSON tree |
| Export options | CSV, SQL, more | Limited | No | No | CSV, JSON, Excel planned |

### Competitive Positioning

**vs phpMyAdmin:** We handle PHP too, with a better editor, all within WordPress admin. No separate tool login.

**vs WP-CLI:** We have a GUI. Better for one-off testing, exploring APIs, quick debugging. WP-CLI still wins for scripting/automation.

**vs WPIDE:** We focus on execution, not file editing. Less risk, more utility.

**vs WPCodeBox:** We're simpler (no snippet management complexity), focused on testing/debugging rather than production code injection.

## Sources

### Code Editors
- [InstaWP WordPress Code Editor Plugins](https://instawp.com/wordpress-code-editor-plugins/)
- [WPIDE Plugin](https://wordpress.org/plugins/wpide/)
- [WPCodeBox](https://wpcodebox.com/)
- [Monaco Editor](https://microsoft.github.io/monaco-editor/)
- [CadyIO wp-monaco-editor](https://github.com/CadyIO/wp-monaco-editor)
- [DBlocks CodePro](https://wordpress.org/plugins/dblocks-codepro/)
- [Hostinger Best Code Editors 2026](https://www.hostinger.com/tutorials/best-code-editors)

### Script Execution
- [WP Utility Script Runner](https://wordpress.com/plugins/wp-utility-script-runner)
- [WP PHP Console](https://wordpress.com/plugins/wp-php-console)
- [WP-CLI Documentation](https://wp-cli.org/)
- [Action Scheduler](https://actionscheduler.org/perf/)
- [WP Background Processing](https://github.com/deliciousbrains/wp-background-processing)

### Version Control
- [Code Snippets Plugin](https://wordpress.org/plugins/code-snippets/)
- [SnipVault Review](https://wpmayor.com/snipvault-review/)
- [WordPress Revisions Guide](https://www.wpbeginner.com/beginners-guide/complete-guide-to-wordpress-post-revisions/)

### Database Tools
- [phpMyAdmin Alternatives](https://www.beekeeperstudio.io/blog/phpmyadmin-alternatives-free)
- [Adminer vs phpMyAdmin](https://www.wpoven.com/blog/adminer-vs-phpmyadmin/)
- [WP All Export](https://wordpress.org/plugins/wp-all-export/)
- [WP-CLI SQL Output Export](https://derrick.blog/2020/11/30/quick-tip-export-wordpress-sql-output-via-wp-cli/)

### Security
- [WordPress SQL Injection Prevention - Patchstack](https://patchstack.com/articles/sql-injection/)
- [WordPress Security Guide 2026](https://www.wpbeginner.com/wordpress-security/)
- [PHP Try Catch Guide 2026](https://www.carmatec.com/blog/php-try-catch-a-complete-exception-handling-guide/)
- [WordPress Debugging Handbook](https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/)

### AI/Modern Editors
- [Top AI Code Editors 2026](https://www.syncfusion.com/blogs/post/ai-code-editors-2026)
- [DEV Community Best Editors 2026](https://dev.to/farhadrahimiklie/which-code-editor-is-best-for-developers-in-2026-2dn7)

---
*Feature research for: WordPress Test Script Manager*
*Researched: 2026-01-30*
