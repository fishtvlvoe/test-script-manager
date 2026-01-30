# Architecture Research

**Domain:** WordPress Admin Development Tools (Code Editor & Script Execution)
**Researched:** 2026-01-30
**Confidence:** HIGH

## Standard Architecture

### System Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         PRESENTATION LAYER                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐              │
│  │  Monaco Editor  │  │  Script List    │  │  Output Panel   │              │
│  │  (VS Code Core) │  │  & Navigation   │  │  (Results View) │              │
│  └────────┬────────┘  └────────┬────────┘  └────────┬────────┘              │
│           │                    │                    │                        │
│           └────────────────────┼────────────────────┘                        │
│                                ↓                                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                         REST API LAYER                                       │
├─────────────────────────────────────────────────────────────────────────────┤
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐              │
│  │  Scripts API    │  │  Execution API  │  │  Versions API   │              │
│  │  (CRUD)         │  │  (Run/Schedule) │  │  (History)      │              │
│  └────────┬────────┘  └────────┬────────┘  └────────┬────────┘              │
│           │                    │                    │                        │
│           └────────────────────┼────────────────────┘                        │
│                                ↓                                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                         SERVICE LAYER                                        │
├─────────────────────────────────────────────────────────────────────────────┤
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐              │
│  │  Script Service │  │  Execution      │  │  Version        │              │
│  │  (Storage)      │  │  Engine         │  │  Service        │              │
│  └────────┬────────┘  └────────┬────────┘  └────────┬────────┘              │
│           │                    │                    │                        │
├───────────┼────────────────────┼────────────────────┼────────────────────────┤
│           ↓                    ↓                    ↓                        │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                     STORAGE LAYER                                    │    │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐               │    │
│  │  │  Database    │  │  Filesystem  │  │  Action      │               │    │
│  │  │  (Scripts +  │  │  (test-      │  │  Scheduler   │               │    │
│  │  │   Versions)  │  │   scripts/)  │  │  (Jobs)      │               │    │
│  │  └──────────────┘  └──────────────┘  └──────────────┘               │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

| Component | Responsibility | Typical Implementation |
|-----------|----------------|------------------------|
| Monaco Editor | Code editing with syntax highlighting, autocomplete, error markers | Webpack-bundled monaco-editor npm package |
| Script List UI | Display scripts, folders, search, navigation | React or vanilla JS with WordPress admin styles |
| Output Panel | Show execution results in various formats | Tabbed interface: Text/Table/JSON/Export |
| Scripts API | CRUD operations for scripts | WP_REST_Controller extension |
| Execution API | Run scripts immediately or schedule background | WP_REST_Controller with Action Scheduler |
| Versions API | Retrieve, compare, restore script versions | WP_REST_Controller for version history |
| Script Service | Dual-write to database + filesystem, validation | PHP service class |
| Execution Engine | Load wp-load.php, capture output, handle errors | Isolated PHP execution with output buffering |
| Version Service | Store snapshots, diff generation, restore logic | PHP service with database operations |
| Database Layer | Scripts table + versions table | Custom tables via dbDelta() |
| Filesystem Layer | Write PHP files to test-scripts/ directory | Direct file write for browser access |
| Action Scheduler | Background job queue for async execution | FluentCart's bundled Action Scheduler |

## Recommended Project Structure

```
test-script-manager/
├── test-script-manager.php           # Main plugin file, activation hooks
├── includes/
│   ├── class-plugin.php              # Singleton loader, hook registration
│   ├── class-database.php            # Table creation via dbDelta()
│   │
│   ├── services/
│   │   ├── class-script-service.php      # Script CRUD, dual-write logic
│   │   ├── class-execution-service.php   # Script execution engine
│   │   ├── class-version-service.php     # Version history management
│   │   └── class-output-formatter.php    # Format results (text/table/JSON)
│   │
│   ├── api/
│   │   ├── class-scripts-api.php         # REST /scripts endpoint
│   │   ├── class-execution-api.php       # REST /execute endpoint
│   │   └── class-versions-api.php        # REST /versions endpoint
│   │
│   └── admin/
│       └── class-admin-page.php          # WordPress admin menu registration
│
├── assets/
│   ├── src/                          # Source files for build
│   │   ├── js/
│   │   │   ├── editor.js             # Monaco Editor initialization
│   │   │   ├── script-manager.js     # Main app logic
│   │   │   └── output-panel.js       # Results display
│   │   └── css/
│   │       └── admin.css             # Admin page styles
│   │
│   └── dist/                         # Webpack build output
│       ├── editor.js
│       ├── editor.worker.js          # Monaco web workers
│       └── admin.css
│
├── views/
│   └── admin-page.php                # Main admin page template
│
├── test-scripts/                     # Runtime directory (created on activation)
│   └── (user scripts stored here)    # Direct browser/CLI access
│
└── webpack.config.js                 # Build configuration for Monaco
```

### Structure Rationale

- **includes/services/:** Business logic isolated from WordPress hooks - testable, reusable
- **includes/api/:** Each REST endpoint in its own controller class following WP_REST_Controller pattern
- **assets/src/ vs assets/dist/:** Separate source and build for Monaco Editor webpack compilation
- **test-scripts/:** External directory enables direct script execution via browser URL or WP-CLI

## Architectural Patterns

### Pattern 1: Dual-Write Storage (Database + Filesystem)

**What:** Every script save writes to both database (metadata, content) and filesystem (executable .php file)
**When to use:** When scripts need both:
1. Database querying (search, list, version history)
2. Direct file execution (browser access, include, require)

**Trade-offs:**
- Pro: Best of both worlds - queryable AND executable
- Pro: Filesystem provides natural "export" and backup
- Con: Must keep in sync (single source of truth = database)
- Con: Filesystem write failures need handling

**Example:**
```php
class ScriptService {
    public function save(int $script_id, string $code): bool {
        global $wpdb;

        // Primary: Database write
        $result = $wpdb->update(
            $this->table_name,
            ['code' => $code, 'updated_at' => current_time('mysql')],
            ['id' => $script_id]
        );

        if ($result === false) {
            return false;
        }

        // Secondary: Filesystem write (fail-safe, not fail-fatal)
        $script = $this->get($script_id);
        $filepath = $this->get_script_path($script->slug);

        // Use WP_Filesystem for compatibility
        global $wp_filesystem;
        WP_Filesystem();

        if (!$wp_filesystem->put_contents($filepath, $code)) {
            // Log warning but don't fail - DB is source of truth
            error_log("TSM: Failed to write filesystem copy: {$filepath}");
        }

        return true;
    }
}
```

### Pattern 2: Version Snapshot on Save

**What:** Create immutable version record before overwriting script content
**When to use:** Any script modification that user might want to undo

**Trade-offs:**
- Pro: Complete history, easy restore
- Pro: Can diff between versions
- Con: Database grows with each save
- Con: Need cleanup strategy for old versions

**Example:**
```php
class VersionService {
    public function create_snapshot(int $script_id): int {
        global $wpdb;

        // Get current state before modification
        $current = $wpdb->get_row($wpdb->prepare(
            "SELECT code, updated_at FROM {$this->scripts_table} WHERE id = %d",
            $script_id
        ));

        // Insert as version record
        $wpdb->insert($this->versions_table, [
            'script_id'  => $script_id,
            'code'       => $current->code,
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id(),
        ]);

        return $wpdb->insert_id;
    }

    // Cleanup: Keep last N versions per script
    public function prune_old_versions(int $script_id, int $keep = 50): void {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->versions_table}
             WHERE script_id = %d
             AND id NOT IN (
                 SELECT id FROM (
                     SELECT id FROM {$this->versions_table}
                     WHERE script_id = %d
                     ORDER BY created_at DESC
                     LIMIT %d
                 ) AS recent
             )",
            $script_id, $script_id, $keep
        ));
    }
}
```

### Pattern 3: Isolated Script Execution

**What:** Execute user scripts in controlled environment with output capture
**When to use:** Any time user code runs - both immediate and background

**Trade-offs:**
- Pro: Captures all output types (echo, var_dump, errors)
- Pro: Can timeout runaway scripts
- Pro: WordPress environment fully available
- Con: No true sandboxing in PHP (dangerous functions still accessible)
- Con: Memory/time limits are suggestions, not guarantees

**Example:**
```php
class ExecutionService {
    public function execute(string $code, array $options = []): array {
        $start_time = microtime(true);
        $memory_start = memory_get_usage();

        // Set execution limits
        $timeout = $options['timeout'] ?? 30;
        set_time_limit($timeout);

        // Capture output
        ob_start();

        $result = [
            'success' => true,
            'output'  => '',
            'error'   => null,
            'stats'   => [],
        ];

        try {
            // Execute in closure for variable isolation
            $executor = function() use ($code) {
                return eval($code);
            };

            $return_value = $executor();

            if ($return_value !== null) {
                // If script returns something, include it
                var_export($return_value);
            }

        } catch (Throwable $e) {
            $result['success'] = false;
            $result['error'] = [
                'type'    => get_class($e),
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ];
        }

        $result['output'] = ob_get_clean();
        $result['stats'] = [
            'execution_time' => round((microtime(true) - $start_time) * 1000, 2),
            'memory_used'    => memory_get_usage() - $memory_start,
            'peak_memory'    => memory_get_peak_usage(),
        ];

        return $result;
    }
}
```

### Pattern 4: Action Scheduler Background Jobs

**What:** Queue script execution for background processing
**When to use:** Long-running scripts, scheduled tasks, avoiding timeout

**Trade-offs:**
- Pro: No timeout issues for long operations
- Pro: Doesn't block user interface
- Pro: Retry on failure built-in
- Con: Results not immediately available
- Con: Debugging harder (no live output)

**Example:**
```php
class ExecutionService {
    public function schedule_execution(int $script_id, array $options = []): string {
        $job_id = wp_generate_uuid4();

        // Store job metadata
        update_option("tsm_job_{$job_id}", [
            'script_id' => $script_id,
            'status'    => 'pending',
            'options'   => $options,
            'created'   => current_time('mysql'),
        ]);

        // Schedule with Action Scheduler
        as_enqueue_async_action(
            'tsm_execute_script',
            ['job_id' => $job_id, 'script_id' => $script_id],
            'test-script-manager'
        );

        return $job_id;
    }

    public function handle_scheduled_execution(string $job_id, int $script_id): void {
        $job = get_option("tsm_job_{$job_id}");

        // Update status
        $job['status'] = 'running';
        $job['started'] = current_time('mysql');
        update_option("tsm_job_{$job_id}", $job);

        // Get script and execute
        $script = $this->script_service->get($script_id);
        $result = $this->execute($script->code, $job['options']);

        // Store result
        $job['status'] = $result['success'] ? 'completed' : 'failed';
        $job['result'] = $result;
        $job['finished'] = current_time('mysql');
        update_option("tsm_job_{$job_id}", $job);
    }
}

// Hook registration
add_action('tsm_execute_script', function($job_id, $script_id) {
    $execution_service = new ExecutionService();
    $execution_service->handle_scheduled_execution($job_id, $script_id);
}, 10, 2);
```

## Data Flow

### Request Flow: Save Script

```
[User types in Monaco Editor]
    ↓
[Click "Save" button]
    ↓
[JavaScript: POST /wp-json/test-script-manager/v1/scripts/{id}]
    ↓
[Scripts API Controller]
    ├── validate_code() → check syntax with php -l
    ├── create_version_snapshot() → Version Service
    └── save() → Script Service
              ├── UPDATE wp_tsm_scripts → Database
              └── write to test-scripts/{slug}.php → Filesystem
    ↓
[Return JSON response with updated script]
    ↓
[JavaScript: Show success notification]
```

### Request Flow: Execute Script

```
[User clicks "Run" button]
    ↓
[JavaScript: POST /wp-json/test-script-manager/v1/execute]
    ├── body: { script_id: 123, mode: "immediate" }
    ↓
[Execution API Controller]
    ├── permission_check() → current_user_can('manage_options')
    ├── get_script() → Script Service
    └── execute() → Execution Service
              ├── set_time_limit()
              ├── ob_start()
              ├── eval($code)
              ├── catch Throwable
              └── ob_get_clean()
    ↓
[Return JSON: { success, output, error, stats }]
    ↓
[JavaScript: Display in Output Panel]
    ├── Text tab: raw output
    ├── Table tab: if structured data
    ├── JSON tab: formatted/collapsible
    └── Export tab: download options
```

### Request Flow: Background Execution

```
[User clicks "Run in Background"]
    ↓
[POST /wp-json/test-script-manager/v1/execute]
    ├── body: { script_id: 123, mode: "background" }
    ↓
[Execution API Controller]
    └── schedule_execution() → Execution Service
              ├── Generate job_id UUID
              ├── Store job metadata in options
              └── as_enqueue_async_action('tsm_execute_script')
    ↓
[Return JSON: { job_id: "abc-123", status: "queued" }]
    ↓
[JavaScript: Start polling GET /jobs/{job_id}]
    ↓
[Later: Action Scheduler processes queue]
    ├── tsm_execute_script action fires
    ├── Execute script, store result
    └── Update job status to "completed"
    ↓
[JavaScript polling detects completion]
    ↓
[Display results in Output Panel]
```

### State Management: Monaco Editor

```
[Monaco Editor Instance]
    ↓ (onDidChangeContent)
[Local State: unsaved changes detected]
    ├── Enable "Save" button
    ├── Show unsaved indicator (dot)
    └── Warn on page leave (beforeunload)
    ↓
[Save triggered]
    ↓
[API call succeeds]
    ├── Clear unsaved state
    ├── Update version history list
    └── Reset change tracking
```

### Key Data Flows

1. **Script Save:** Editor → API → Database (primary) → Filesystem (secondary)
2. **Script Execute:** API → Execution Engine → Output Buffer → JSON Response
3. **Version Restore:** Select version → API → Swap content in DB → Update filesystem
4. **Background Job:** API → Action Scheduler → Async execution → Job result storage → Polling retrieval

## Scaling Considerations

| Scale | Architecture Adjustments |
|-------|--------------------------|
| 0-100 scripts | Default architecture sufficient, single DB table, filesystem mirror |
| 100-1000 scripts | Add pagination to API, consider folder organization, index on script slug |
| 1000+ scripts | Split versions to separate table with foreign key, implement version pruning cron |
| High execution volume | Move job results to dedicated table, implement result cleanup |

### Scaling Priorities

1. **First bottleneck:** Version history growth
   - Fix: Implement prune_old_versions() with configurable limit (default 50)
   - Add scheduled cleanup via Action Scheduler

2. **Second bottleneck:** Large script content in listings
   - Fix: API returns code only when specifically requested (GET /{id})
   - List endpoints return metadata only

3. **Third bottleneck:** Many concurrent executions
   - Fix: Queue all executions through Action Scheduler
   - Implement execution rate limiting

## Anti-Patterns

### Anti-Pattern 1: Storing Code Only in Filesystem

**What people do:** Store scripts as files only, treating filesystem as database
**Why it's wrong:**
- No metadata (created_by, tags, description)
- No version history without Git
- Search/filter requires reading every file
- No atomic operations
**Do this instead:** Database as primary storage with filesystem as secondary mirror

### Anti-Pattern 2: Executing Scripts Directly via URL

**What people do:** Place scripts in public directory, access via `https://site.com/test-scripts/my-script.php`
**Why it's wrong:**
- No authentication/authorization
- No execution logging
- No output capture
- Direct exposure of potentially sensitive operations
**Do this instead:** Execute through REST API with capability checks, log all executions

### Anti-Pattern 3: Global eval() Without Isolation

**What people do:** `eval($user_code)` in global scope
**Why it's wrong:**
- Variables leak between executions
- Harder to capture errors
- Security analysis more difficult
**Do this instead:** Wrap in closure or function scope, use try/catch for Throwable

### Anti-Pattern 4: Synchronous Long-Running Execution

**What people do:** Execute 5-minute scripts synchronously, hoping PHP timeout won't hit
**Why it's wrong:**
- Browser timeout (2 minutes default)
- PHP timeout (30-60 seconds default)
- Locks up user interface
- No recovery if interrupted
**Do this instead:** Queue through Action Scheduler for anything >30 seconds

### Anti-Pattern 5: No Version History

**What people do:** Overwrite script content on save
**Why it's wrong:**
- No undo for mistakes
- No audit trail
- Lost context on how script evolved
**Do this instead:** Create snapshot before every save, implement version comparison/restore

## Integration Points

### External Services

| Service | Integration Pattern | Notes |
|---------|---------------------|-------|
| Monaco Editor | NPM package with webpack build | Use MonacoWebpackPlugin, handle web workers |
| Action Scheduler | PHP library inclusion | Already bundled with FluentCart, check version |
| WordPress REST API | WP_REST_Controller extension | Follow namespace pattern: test-script-manager/v1 |
| WP_Filesystem | WordPress abstraction | Use for all file writes for hosting compatibility |

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| Frontend ↔ API | REST JSON | All state changes go through API |
| API ↔ Services | Direct PHP calls | Controllers are thin, services are fat |
| Services ↔ Database | $wpdb queries | Use prepared statements always |
| Services ↔ Filesystem | WP_Filesystem | Never direct fopen/fwrite for compatibility |
| Execution ↔ Action Scheduler | Action hooks | Use as_enqueue_async_action() |

### WordPress Admin Integration

| Integration | Hook | Pattern |
|-------------|------|---------|
| Admin menu | admin_menu | add_menu_page() for top-level page |
| Script enqueue | admin_enqueue_scripts | Conditional load only on plugin page |
| REST API init | rest_api_init | Register all controllers |
| Activation | register_activation_hook | Create tables via dbDelta() |
| Uninstall | uninstall.php | Optional: drop tables, remove files |

## Build Order Implications

Based on component dependencies, recommended build phases:

### Phase 1: Foundation
- Database schema (scripts + versions tables)
- Plugin activation/deactivation hooks
- Basic admin page registration

**Rationale:** Everything else depends on storage being available

### Phase 2: Script CRUD
- Script Service (save, get, list, delete)
- Scripts REST API
- Basic admin UI (list view, create/delete)

**Rationale:** Need to manage scripts before editing them

### Phase 3: Monaco Editor Integration
- Webpack build configuration
- Monaco initialization JavaScript
- WordPress asset enqueuing
- Edit screen with save functionality

**Rationale:** Core value prop - the code editor. Depends on Phase 2 for data

### Phase 4: Execution Engine
- Execution Service (immediate execution)
- Execution REST API
- Output Panel UI (basic text output)

**Rationale:** Second core value prop. Can now create and run scripts

### Phase 5: Version History
- Version Service (snapshot, list, restore)
- Versions REST API
- Version comparison UI

**Rationale:** Important for safety, but scripts work without it

### Phase 6: Background Execution
- Action Scheduler integration
- Background execution mode
- Job status polling
- Execution queue UI

**Rationale:** Enhancement for long-running scripts. Lower priority than core flow

### Phase 7: Output Formats
- Table format detection and rendering
- JSON formatter with collapse/expand
- Export functionality (download, copy)

**Rationale:** Polish features, core output works in Phase 4

## Sources

**Monaco Editor:**
- [Monaco Editor Webpack Plugin - npm](https://www.npmjs.com/package/monaco-editor-webpack-plugin)
- [Monaco Editor GitHub](https://github.com/microsoft/monaco-editor)
- [WPCodeBox - Uses Monaco](https://wpcodebox.com/)

**WordPress REST API:**
- [Adding Custom Endpoints - WordPress Developer Docs](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/)
- [Controller Classes - WordPress Developer Docs](https://developer.wordpress.org/rest-api/extending-the-rest-api/controller-classes/)

**Action Scheduler:**
- [Action Scheduler Official](https://actionscheduler.org/)
- [Action Scheduler Usage](https://actionscheduler.org/usage/)

**Database Tables:**
- [Creating Tables with Plugins - WordPress Developer Docs](https://developer.wordpress.org/plugins/creating-tables-with-plugins/)
- [WP-Migrations - Delicious Brains](https://github.com/deliciousbrains/wp-migrations)

**Code Snippet Plugins (Architecture Reference):**
- [WPCodeBox Documentation](https://docs.wpcodebox.com/)
- [Custom HTML Block Extension - WordPress.org](https://wordpress.org/plugins/custom-html-block-extension/)

**Security Considerations:**
- [PHP eval() Security - Sourcery](https://www.sourcery.ai/vulnerabilities/php-lang-security-eval-use)
- [JavaScript Sandboxing - DEV Community](https://dev.to/leapcell/a-deep-dive-into-javascript-sandboxing-97b)

---
*Architecture research for: WordPress Test Script Manager*
*Researched: 2026-01-30*
