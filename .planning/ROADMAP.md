# Roadmap: WordPress Test Script Manager

## Overview

This roadmap delivers a WordPress admin tool that lets developers write, manage, and execute test scripts directly in the browser. The journey starts with security foundations and database schema, progresses through script storage and Monaco Editor integration, delivers execution capabilities (both immediate and background), adds version history for safety, and concludes with output enhancements and polish features. Each phase builds on previous work, with security boundaries established first to protect all subsequent code execution features.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 1: Security Foundation** - Database schema, authentication layer, path resolution
- [x] **Phase 2: Script CRUD & Storage** - Script service, REST API endpoints, basic admin UI
- [x] **Phase 3: Monaco Editor Integration** - VS Code-quality code editor with multi-language support
- [ ] **Phase 4: Execution Engine** - Script execution with output capture, formatting, and stats
- [ ] **Phase 5: Background Execution** - Action Scheduler integration for long-running scripts
- [ ] **Phase 6: Version History** - Snapshot creation, diff view, rollback functionality
- [ ] **Phase 7: Output Enhancements & Polish** - Export formats, themes, categories, keyboard shortcuts

## Phase Details

### Phase 1: Security Foundation
**Goal**: Establish security boundaries and core infrastructure before any code execution capability
**Depends on**: Nothing (first phase)
**Requirements**: SEC-01, SEC-02, SEC-03, DB-01, DB-02, DB-03, DB-04, DB-05, UI-01
**Success Criteria** (what must be TRUE):
  1. Only users with manage_options capability can access the admin menu
  2. All AJAX/REST requests are rejected without valid nonce
  3. Dangerous functions (eval, exec, system) are blocked when WP_DEBUG is false
  4. Database tables (tsm_scripts, tsm_script_versions, tsm_execution_logs, tsm_categories, tsm_script_tags) exist after plugin activation
  5. WordPress path resolution works correctly even with symlinked plugin directories
**Plans**: 2 plans

Plans:
- [x] 01-01-PLAN.md - Plugin skeleton, database schema with dbDelta, activation hooks
- [x] 01-02-PLAN.md - Security layer (nonce/capability), code scanner, admin menu

### Phase 2: Script CRUD & Storage
**Goal**: Users can create, read, update, and delete scripts through REST API and basic admin interface
**Depends on**: Phase 1
**Requirements**: STORE-01, STORE-02, STORE-03, STORE-04, STORE-05, STORE-06, STORE-07, STORE-08, STORE-09, STORE-10, STORE-11, API-01, API-02, API-03, API-04, API-05, UI-02
**Success Criteria** (what must be TRUE):
  1. User can create a new script with name, slug, and code via admin UI
  2. User can see a list of all scripts with name, created time, and last executed time
  3. Script is saved to both database and filesystem ({WordPress root}/test-scripts/test-{slug}.php)
  4. User can access script file directly via browser URL
  5. User can search scripts by name or code content
**Plans**: 3 plans

Plans:
- [x] 02-01-PLAN.md - Script service and dual-write storage (ScriptService, StorageService)
- [x] 02-02-PLAN.md - Scripts REST API endpoints (Scripts_API with 5 endpoints)
- [x] 02-03-PLAN.md - Basic admin UI (script list, search, create form)

### Phase 3: Monaco Editor Integration
**Goal**: Users can edit scripts with VS Code-quality syntax highlighting and autocomplete
**Depends on**: Phase 2
**Requirements**: EDIT-01, EDIT-02, EDIT-03, EDIT-04, EDIT-06, EDIT-07, EDIT-08, EDIT-09, UI-03
**Success Criteria** (what must be TRUE):
  1. Monaco Editor loads on the script edit page with syntax highlighting for PHP, SQL, JavaScript, CSS
  2. Editor provides autocomplete suggestions for WordPress functions and hooks
  3. Changes are auto-saved after a brief delay (debounced)
  4. User can switch between dark and light editor themes
  5. Keyboard shortcuts work (Ctrl+S to save, Ctrl+Enter to execute)
**Plans**: 3 plans

Plans:
- [x] 03-01-PLAN.md - Monaco CDN loading and basic editor integration
- [x] 03-02-PLAN.md - WordPress autocomplete, auto-save, and keyboard shortcuts
- [x] 03-03-PLAN.md - Theme switching and final verification

### Phase 4: Execution Engine
**Goal**: Users can execute scripts and see formatted output with error handling
**Depends on**: Phase 1 (security), Phase 2 (storage)
**Requirements**: EXEC-01, EXEC-02, EXEC-03, EXEC-04, EXEC-05, EXEC-09, EXEC-10, EXEC-14, EXEC-15, EXEC-16, API-06, API-09, API-10, UI-04, UI-05, SEC-04
**Success Criteria** (what must be TRUE):
  1. User can click "Execute" and see script output in a new tab
  2. WordPress environment (wp-load.php) is loaded before script execution
  3. Errors are displayed with red highlighting and line numbers
  4. Database query results are displayed as formatted tables
  5. JSON/array data can be expanded/collapsed like Chrome DevTools
  6. Execution time and memory usage are shown after each run
**Plans**: 3 plans

Plans:
- [ ] 04-01-PLAN.md - ExecutionService with tri-layer error capture and output buffering
- [ ] 04-02-PLAN.md - Execution REST API endpoints and history management
- [ ] 04-03-PLAN.md - Result page UI with tables, JSON viewer, and stats display

### Phase 5: Background Execution
**Goal**: Long-running scripts can execute in background without browser timeout
**Depends on**: Phase 4
**Requirements**: EXEC-06, EXEC-07, EXEC-08, UI-08
**Success Criteria** (what must be TRUE):
  1. User can choose "Background Execute" option for any script
  2. User can close the browser tab after starting background execution
  3. User receives notification (admin notice) when background execution completes
  4. User can check execution status (running, completed, failed) in execution history sidebar
  5. Failed background jobs are automatically retried up to 3 times
**Plans**: TBD

Plans:
- [ ] 05-01: Action Scheduler integration
- [ ] 05-02: Background execution status UI

### Phase 6: Version History
**Goal**: Users can track script changes, compare versions, and rollback to previous states
**Depends on**: Phase 2
**Requirements**: VER-01, VER-02, VER-03, VER-04, VER-05, VER-06, API-07, API-08, UI-07
**Success Criteria** (what must be TRUE):
  1. A version snapshot is automatically created before each save
  2. User can see version history list with timestamps in the sidebar
  3. User can compare two versions with diff highlighting (added/removed lines)
  4. User can restore any previous version with one click
  5. Old versions are automatically cleaned up (keeps last 50 or 30 days)
**Plans**: TBD

Plans:
- [ ] 06-01: Version service and API
- [ ] 06-02: Diff view and restore UI

### Phase 7: Output Enhancements & Polish
**Goal**: Professional-grade output formatting and organization features
**Depends on**: Phase 4
**Requirements**: STORE-12, STORE-13, STORE-14, EXEC-11, EXEC-12, EXEC-13, UI-06, SEC-05, SEC-06
**Success Criteria** (what must be TRUE):
  1. User can export execution results as CSV, JSON, or Excel file
  2. User can organize scripts into custom categories
  3. User can add multiple tags to scripts for filtering
  4. User can create scripts from template library (common patterns)
  5. User can configure timeout and IP whitelist in settings page
**Plans**: TBD

Plans:
- [ ] 07-01: Export functionality (CSV, JSON, Excel)
- [ ] 07-02: Categories, tags, and templates
- [ ] 07-03: Settings page

## Progress

**Execution Order:**
Phases execute in numeric order: 1 -> 2 -> 3 -> 4 -> 5 -> 6 -> 7

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Security Foundation | 2/2 | Complete | 2026-01-30 |
| 2. Script CRUD & Storage | 3/3 | Complete | 2026-01-30 |
| 3. Monaco Editor Integration | 3/3 | Complete | 2026-01-30 |
| 4. Execution Engine | 0/3 | Planned | - |
| 5. Background Execution | 0/2 | Not started | - |
| 6. Version History | 0/2 | Not started | - |
| 7. Output Enhancements & Polish | 0/3 | Not started | - |

---
*Roadmap created: 2026-01-30*
*Last updated: 2026-01-30*
