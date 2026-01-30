# Phase 4: Execution Engine - Research

**Researched:** 2026-01-30
**Domain:** PHP Script Execution, Output Capture, Error Handling, Result Formatting
**Confidence:** HIGH

## Summary

This research covers the implementation of a PHP script execution engine for WordPress that captures output, handles errors, tracks performance metrics, and displays results in a formatted UI. The core challenge is executing user-written PHP code within WordPress context while safely capturing all output (including errors) and presenting results in a usable format.

The standard approach uses PHP's output buffering (`ob_start()` / `ob_get_clean()`) combined with custom error handlers (`set_error_handler()`, `set_exception_handler()`, `register_shutdown_function()`) to capture all script output and errors. Results are stored in the `tsm_execution_logs` table (already created in Phase 1). The UI displays results in a new tab with formatted tables for database queries and a Chrome DevTools-style JSON viewer for arrays/objects.

**Primary recommendation:** Use PHP's native output buffering with a tri-layer error capture system (error handler + exception handler + shutdown function), execute scripts via `include()` not `eval()`, and display results using a combination of server-side HTML tables and client-side json-view library for interactive JSON exploration.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Component | Version | Purpose | Why Standard |
|-----------|---------|---------|--------------|
| PHP Output Buffering | PHP Core | Capture script stdout | Built-in, supports nested buffers, ob_start()/ob_get_clean() |
| set_error_handler() | PHP Core | Capture non-fatal errors | Converts warnings/notices to structured data |
| set_exception_handler() | PHP Core | Capture uncaught exceptions | Handles thrown exceptions uniformly |
| register_shutdown_function() | PHP Core | Capture fatal errors | Only way to catch E_ERROR, E_PARSE |
| microtime(true) | PHP Core | Execution time tracking | Microsecond precision, standard approach |
| memory_get_peak_usage(true) | PHP Core | Memory tracking | Peak memory with real_usage flag for accuracy |

### Supporting
| Component | Version | Purpose | When to Use |
|-----------|---------|---------|-------------|
| json-view | latest | Collapsible JSON display | Display JSON/array data with DevTools-style UI |
| ErrorException | PHP Core | Convert errors to exceptions | Unified error handling pattern |
| error_get_last() | PHP Core | Get last error in shutdown | Fatal error detection in shutdown function |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| include() | eval() | eval() is more dangerous, cannot use PHP tags, parse errors harder to catch |
| json-view | react-json-view | json-view is vanilla JS, no React dependency needed |
| Custom HTML tables | DataTables.js | DataTables adds sorting/pagination but overkill for result display |

**Installation:**
```bash
# json-view can be loaded from CDN or bundled
# No npm install needed for WordPress admin context
```

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── services/
│   ├── class-execution-service.php    # Core execution logic
│   └── class-output-formatter.php     # Format results for display
├── api/
│   └── class-execution-api.php        # REST endpoints for execution
└── admin/
    └── views/
        └── execution-result.php       # Result display template
```

### Pattern 1: Tri-Layer Error Capture
**What:** Combine error handler, exception handler, and shutdown function to capture ALL errors
**When to use:** Any PHP code execution that needs comprehensive error capture
**Example:**
```php
// Source: PHP Manual and community best practices
class ExecutionService {
    private static $captured_errors = [];
    private static $captured_exception = null;

    public static function execute($code_file) {
        $result = [
            'output'    => '',
            'errors'    => [],
            'exception' => null,
            'fatal'     => null,
        ];

        // Layer 1: Error handler (warnings, notices)
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            self::$captured_errors[] = [
                'type'    => $errno,
                'message' => $errstr,
                'file'    => $errfile,
                'line'    => $errline,
            ];
            return true; // Don't execute PHP's default handler
        });

        // Layer 2: Exception handler (uncaught exceptions)
        set_exception_handler(function($e) {
            self::$captured_exception = [
                'type'    => get_class($e),
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ];
        });

        // Layer 3: Shutdown function (fatal errors)
        register_shutdown_function(function() use (&$result) {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                $result['fatal'] = $error;
            }
        });

        // Capture output
        ob_start();
        try {
            include $code_file;
        } catch (Throwable $e) {
            self::$captured_exception = [
                'type'    => get_class($e),
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ];
        }
        $result['output'] = ob_get_clean();

        // Restore handlers
        restore_error_handler();
        restore_exception_handler();

        $result['errors'] = self::$captured_errors;
        $result['exception'] = self::$captured_exception;

        return $result;
    }
}
```

### Pattern 2: Output Buffering with Performance Tracking
**What:** Capture output while tracking execution time and memory
**When to use:** Script execution with metrics collection
**Example:**
```php
// Source: PHP Manual - microtime, memory_get_peak_usage
public static function execute_with_metrics($code_file) {
    // Record start state
    $start_time = microtime(true);
    $start_memory = memory_get_usage(true);

    // Execute with output buffering
    ob_start();
    include $code_file;
    $output = ob_get_clean();

    // Calculate metrics
    $execution_time = microtime(true) - $start_time;
    $peak_memory = memory_get_peak_usage(true);
    $memory_used = $peak_memory - $start_memory;

    return [
        'output'         => $output,
        'execution_time' => $execution_time,
        'memory_usage'   => $memory_used,
        'peak_memory'    => $peak_memory,
    ];
}
```

### Pattern 3: Execution Timeout Control
**What:** Set per-script timeout limits
**When to use:** User-configurable execution timeouts
**Example:**
```php
// Source: PHP Manual - set_time_limit
public static function execute_with_timeout($code_file, $timeout = 30) {
    // Store original limit
    $original_limit = ini_get('max_execution_time');

    // Set new limit (0 = no limit, but we cap at 300 for safety)
    $timeout = min(max($timeout, 1), 300);
    set_time_limit($timeout);

    try {
        // Execute script
        $result = self::execute($code_file);
    } finally {
        // Restore original limit
        set_time_limit($original_limit);
    }

    return $result;
}
```

### Pattern 4: Execution Result Storage
**What:** Store execution history in database
**When to use:** Logging execution results for history/review
**Example:**
```php
// Source: Phase 1 Database schema
public static function log_execution($script_id, $result) {
    global $wpdb;

    $table = $wpdb->prefix . 'tsm_execution_logs';

    // Truncate output if over limit (EXEC-16: 10MB default)
    $output = $result['output'];
    $max_size = 10 * 1024 * 1024; // 10MB
    if (strlen($output) > $max_size) {
        $output = substr($output, 0, $max_size) . "\n\n[Output truncated at 10MB limit]";
    }

    $status = 'success';
    if (!empty($result['errors']) || !empty($result['exception']) || !empty($result['fatal'])) {
        $status = !empty($result['fatal']) ? 'fatal_error' : 'error';
    }

    $wpdb->insert($table, [
        'script_id'      => $script_id,
        'output'         => $output,
        'error'          => json_encode([
            'errors'    => $result['errors'] ?? [],
            'exception' => $result['exception'] ?? null,
            'fatal'     => $result['fatal'] ?? null,
        ]),
        'execution_time' => $result['execution_time'] ?? 0,
        'memory_usage'   => $result['memory_usage'] ?? 0,
        'status'         => $status,
        'executed_at'    => current_time('mysql'),
    ], ['%d', '%s', '%s', '%f', '%d', '%s', '%s']);

    return $wpdb->insert_id;
}
```

### Anti-Patterns to Avoid
- **Using eval() instead of include():** eval() cannot use `<?php` tags, parse errors behave differently, and it's harder to debug. Use temporary file + include() pattern.
- **Catching only Exception:** PHP 7+ has Error class that doesn't extend Exception. Catch `Throwable` to get both.
- **Forgetting to restore handlers:** Always use `restore_error_handler()` and `restore_exception_handler()` after execution to avoid affecting WordPress.
- **Infinite timeout:** Never allow `set_time_limit(0)` from user input. Cap at reasonable maximum (300 seconds).
- **Not truncating large output:** Scripts can generate massive output; always enforce size limits.

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| JSON tree display | Custom recursive HTML builder | json-view library | Handles collapse/expand, editing, large data sets |
| Error level names | Switch statement mapping | PHP's error constants | E_WARNING, E_NOTICE, etc. are built-in |
| Memory formatting | Manual division/rounding | size_format() or custom helper | Consistency with WordPress patterns |
| Execution isolation | pcntl_fork() | Output buffering + error handlers | pcntl not available in web context |

**Key insight:** PHP's output buffering and error handling are mature and well-documented. The complexity is in correctly combining them, not in reinventing them.

## Common Pitfalls

### Pitfall 1: Fatal Errors Bypass Error Handler
**What goes wrong:** E_ERROR, E_PARSE cannot be caught by set_error_handler()
**Why it happens:** These errors terminate PHP immediately, before custom handlers run
**How to avoid:** Use `register_shutdown_function()` with `error_get_last()` to detect fatal errors
**Warning signs:** Script dies silently with no output captured

### Pitfall 2: Output Buffering State Corruption
**What goes wrong:** Other plugins/themes start output buffers that interfere with capture
**Why it happens:** WordPress and plugins may use output buffering for their own purposes
**How to avoid:** Track buffer level with `ob_get_level()`, ensure you only clean your own buffer
**Warning signs:** Missing output, garbled content, PHP warnings about buffer levels

### Pitfall 3: Memory Limit vs Output Size
**What goes wrong:** Capturing very large output exhausts PHP memory
**Why it happens:** Output is held in memory as a string until processed
**How to avoid:** Implement output size checking and truncation during capture
**Warning signs:** Memory exhaustion errors, blank pages

### Pitfall 4: WordPress Environment Not Loaded
**What goes wrong:** Script execution fails because WordPress functions unavailable
**Why it happens:** Script file doesn't include wp-load.php header
**How to avoid:** StorageService already adds wp-load.php header to all scripts
**Warning signs:** "Call to undefined function" errors for WordPress functions

### Pitfall 5: Error Handler Not Restored
**What goes wrong:** Custom error handler affects all subsequent WordPress operations
**Why it happens:** Forgetting to call `restore_error_handler()` after execution
**How to avoid:** Use try/finally to ensure handlers are always restored
**Warning signs:** WordPress admin behaving strangely, errors not appearing in debug log

### Pitfall 6: Timeout Affects Post-Execution Code
**What goes wrong:** Post-execution cleanup code also times out
**Why it happens:** set_time_limit() applies to entire script, not just include()
**How to avoid:** Call `set_time_limit(0)` or restore original after include() returns
**Warning signs:** Partial execution logs, incomplete cleanup

## Code Examples

Verified patterns from official sources:

### Complete Execution Service Implementation
```php
// Source: PHP Manual output buffering, error handling
class ExecutionService {

    /**
     * Maximum output size in bytes (EXEC-16)
     */
    const MAX_OUTPUT_SIZE = 10 * 1024 * 1024; // 10MB

    /**
     * Maximum execution time in seconds (SEC-04)
     */
    const MAX_TIMEOUT = 300;

    /**
     * Default execution timeout
     */
    const DEFAULT_TIMEOUT = 30;

    private static $errors = [];
    private static $exception = null;

    /**
     * Execute a script file with full error capture and metrics
     */
    public static function execute($script_id, $timeout = self::DEFAULT_TIMEOUT) {
        // Get script data
        $script = ScriptService::get($script_id);
        if (null === $script) {
            return new \WP_Error('script_not_found', 'Script not found');
        }

        // Get file path
        $file_path = StorageService::get_script_path($script['slug']);
        if (!file_exists($file_path)) {
            return new \WP_Error('file_not_found', 'Script file not found');
        }

        // Security check
        $validation = CodeScanner::validate_code($script['code']);
        if (!$validation['valid']) {
            return new \WP_Error('dangerous_code', $validation['message']);
        }

        // Initialize capture state
        self::$errors = [];
        self::$exception = null;

        // Record start metrics
        $start_time = microtime(true);
        $start_memory = memory_get_usage(true);
        $original_timeout = ini_get('max_execution_time');

        // Set timeout
        $timeout = min(max((int) $timeout, 1), self::MAX_TIMEOUT);
        set_time_limit($timeout);

        // Set up error handlers
        $previous_error_handler = set_error_handler([self::class, 'error_handler']);
        $previous_exception_handler = set_exception_handler([self::class, 'exception_handler']);

        // Result container
        $result = [
            'output'         => '',
            'errors'         => [],
            'exception'      => null,
            'fatal'          => null,
            'execution_time' => 0,
            'memory_usage'   => 0,
            'status'         => 'success',
        ];

        // Capture output
        $buffer_level = ob_get_level();
        ob_start();

        try {
            include $file_path;
        } catch (\Throwable $e) {
            self::$exception = [
                'type'    => get_class($e),
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ];
        }

        // Get output
        $output = ob_get_clean();

        // Ensure buffer level is restored
        while (ob_get_level() > $buffer_level) {
            ob_end_clean();
        }

        // Restore handlers
        restore_error_handler();
        restore_exception_handler();
        set_time_limit($original_timeout);

        // Calculate metrics
        $result['execution_time'] = microtime(true) - $start_time;
        $result['memory_usage'] = memory_get_peak_usage(true) - $start_memory;

        // Truncate output if needed
        if (strlen($output) > self::MAX_OUTPUT_SIZE) {
            $output = substr($output, 0, self::MAX_OUTPUT_SIZE);
            $output .= "\n\n[Output truncated: exceeded " . size_format(self::MAX_OUTPUT_SIZE) . " limit]";
        }

        $result['output'] = $output;
        $result['errors'] = self::$errors;
        $result['exception'] = self::$exception;

        // Check for fatal error
        $last_error = error_get_last();
        if ($last_error && in_array($last_error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $result['fatal'] = $last_error;
        }

        // Determine status
        if (!empty($result['fatal'])) {
            $result['status'] = 'fatal_error';
        } elseif (!empty($result['exception']) || !empty($result['errors'])) {
            $result['status'] = 'error';
        }

        // Update last_executed_at
        ScriptService::update_last_executed($script_id);

        // Log execution
        self::log_execution($script_id, $result);

        return $result;
    }

    /**
     * Custom error handler
     */
    public static function error_handler($errno, $errstr, $errfile, $errline) {
        self::$errors[] = [
            'type'    => $errno,
            'level'   => self::error_level_name($errno),
            'message' => $errstr,
            'file'    => $errfile,
            'line'    => $errline,
        ];
        return true; // Don't execute PHP's default handler
    }

    /**
     * Custom exception handler
     */
    public static function exception_handler($e) {
        self::$exception = [
            'type'    => get_class($e),
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => $e->getTraceAsString(),
        ];
    }

    /**
     * Convert error level to name
     */
    private static function error_level_name($level) {
        $levels = [
            E_ERROR             => 'E_ERROR',
            E_WARNING           => 'E_WARNING',
            E_PARSE             => 'E_PARSE',
            E_NOTICE            => 'E_NOTICE',
            E_CORE_ERROR        => 'E_CORE_ERROR',
            E_CORE_WARNING      => 'E_CORE_WARNING',
            E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
            E_USER_ERROR        => 'E_USER_ERROR',
            E_USER_WARNING      => 'E_USER_WARNING',
            E_USER_NOTICE       => 'E_USER_NOTICE',
            E_STRICT            => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED        => 'E_DEPRECATED',
            E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
        ];
        return $levels[$level] ?? 'UNKNOWN';
    }

    /**
     * Log execution to database
     */
    private static function log_execution($script_id, $result) {
        global $wpdb;

        $table = $wpdb->prefix . Database::TABLE_EXECUTION_LOGS;

        $wpdb->insert($table, [
            'script_id'      => $script_id,
            'output'         => $result['output'],
            'error'          => wp_json_encode([
                'errors'    => $result['errors'],
                'exception' => $result['exception'],
                'fatal'     => $result['fatal'],
            ]),
            'execution_time' => $result['execution_time'],
            'memory_usage'   => $result['memory_usage'],
            'status'         => $result['status'],
            'executed_at'    => current_time('mysql'),
        ], ['%d', '%s', '%s', '%f', '%d', '%s', '%s']);

        return $wpdb->insert_id;
    }
}
```

### REST API Execute Endpoint
```php
// Source: Phase 2 API patterns
// POST /wp-json/test-script-manager/v1/scripts/{id}/execute
register_rest_route(self::NAMESPACE, '/scripts/(?P<id>\d+)/execute', [
    'methods'             => 'POST',
    'callback'            => [$this, 'execute_script'],
    'permission_callback' => ['TSM\Security', 'check_admin_permission'],
    'args'                => [
        'id' => [
            'required'          => true,
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
        ],
        'timeout' => [
            'default'           => 30,
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
        ],
    ],
]);

public function execute_script(WP_REST_Request $request) {
    $id = $request->get_param('id');
    $timeout = $request->get_param('timeout');

    $result = ExecutionService::execute($id, $timeout);

    if (is_wp_error($result)) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => $result->get_error_message(),
            'code'    => $result->get_error_code(),
        ], 400);
    }

    return new WP_REST_Response([
        'success' => true,
        'result'  => $result,
    ], 200);
}
```

### Execution History Endpoints
```php
// Source: Requirements API-09, API-10

// GET /wp-json/test-script-manager/v1/executions
public function get_executions(WP_REST_Request $request) {
    global $wpdb;

    $script_id = $request->get_param('script_id');
    $limit = $request->get_param('limit');
    $offset = $request->get_param('offset');

    $table = $wpdb->prefix . Database::TABLE_EXECUTION_LOGS;

    $where = '';
    $values = [];

    if ($script_id) {
        $where = 'WHERE script_id = %d';
        $values[] = $script_id;
    }

    $values[] = $limit;
    $values[] = $offset;

    $sql = "SELECT * FROM $table $where ORDER BY executed_at DESC LIMIT %d OFFSET %d";
    $executions = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);

    // Decode error JSON for each execution
    foreach ($executions as &$exec) {
        $exec['error'] = json_decode($exec['error'], true);
    }

    return new WP_REST_Response([
        'executions' => $executions,
        'total'      => $this->count_executions($script_id),
        'limit'      => $limit,
        'offset'     => $offset,
    ], 200);
}

// GET /wp-json/test-script-manager/v1/executions/{id}
public function get_execution(WP_REST_Request $request) {
    global $wpdb;

    $id = $request->get_param('id');
    $table = $wpdb->prefix . Database::TABLE_EXECUTION_LOGS;

    $execution = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id),
        ARRAY_A
    );

    if (null === $execution) {
        return new WP_REST_Response([
            'success' => false,
            'error'   => 'Execution log not found',
            'code'    => 'execution_not_found',
        ], 404);
    }

    $execution['error'] = json_decode($execution['error'], true);

    return new WP_REST_Response([
        'success'   => true,
        'execution' => $execution,
    ], 200);
}
```

### Output Formatter for Display
```php
// Source: Requirements UI-05, EXEC-09, EXEC-10
class OutputFormatter {

    /**
     * Detect if output is a database result (array of objects/arrays)
     */
    public static function is_table_data($data) {
        if (!is_array($data) || empty($data)) {
            return false;
        }

        // Check if first element is array/object with consistent keys
        $first = reset($data);
        if (!is_array($first) && !is_object($first)) {
            return false;
        }

        $keys = is_array($first) ? array_keys($first) : array_keys(get_object_vars($first));

        // Verify all rows have same structure
        foreach ($data as $row) {
            $row_keys = is_array($row) ? array_keys($row) : array_keys(get_object_vars($row));
            if ($row_keys !== $keys) {
                return false;
            }
        }

        return true;
    }

    /**
     * Format array as HTML table
     */
    public static function format_as_table($data) {
        if (empty($data)) {
            return '<p class="tsm-empty">No results</p>';
        }

        $first = reset($data);
        $headers = is_array($first) ? array_keys($first) : array_keys(get_object_vars($first));

        $html = '<div class="tsm-table-wrapper">';
        $html .= '<table class="tsm-result-table">';

        // Header row
        $html .= '<thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . esc_html($header) . '</th>';
        }
        $html .= '</tr></thead>';

        // Data rows
        $html .= '<tbody>';
        foreach ($data as $row) {
            $html .= '<tr>';
            $row = is_array($row) ? $row : get_object_vars($row);
            foreach ($row as $value) {
                $html .= '<td>' . esc_html(self::format_cell_value($value)) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';

        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Format single cell value
     */
    private static function format_cell_value($value) {
        if (is_null($value)) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value) || is_object($value)) {
            return wp_json_encode($value);
        }
        return (string) $value;
    }
}
```

### Result Display Page (New Tab)
```html
<!-- Source: Requirements UI-05 -->
<!DOCTYPE html>
<html>
<head>
    <title>Script Execution Result - <?php echo esc_html($script_name); ?></title>
    <link rel="stylesheet" href="<?php echo TSM_PLUGIN_URL; ?>assets/css/execution-result.css">
</head>
<body class="tsm-result-page">
    <header class="tsm-result-header">
        <h1>Execution Result: <?php echo esc_html($script_name); ?></h1>
        <div class="tsm-result-meta">
            <span class="tsm-status tsm-status-<?php echo esc_attr($result['status']); ?>">
                <?php echo esc_html(ucfirst($result['status'])); ?>
            </span>
            <span class="tsm-time">
                Time: <?php echo number_format($result['execution_time'], 4); ?>s
            </span>
            <span class="tsm-memory">
                Memory: <?php echo size_format($result['memory_usage']); ?>
            </span>
        </div>
    </header>

    <?php if (!empty($result['errors']) || !empty($result['exception']) || !empty($result['fatal'])): ?>
    <section class="tsm-errors">
        <h2>Errors</h2>
        <?php foreach ($result['errors'] as $error): ?>
        <div class="tsm-error-item tsm-error-<?php echo esc_attr(strtolower($error['level'])); ?>">
            <span class="tsm-error-level"><?php echo esc_html($error['level']); ?></span>
            <span class="tsm-error-message"><?php echo esc_html($error['message']); ?></span>
            <span class="tsm-error-location">
                <?php echo esc_html($error['file']); ?>:<?php echo esc_html($error['line']); ?>
            </span>
        </div>
        <?php endforeach; ?>

        <?php if (!empty($result['exception'])): ?>
        <div class="tsm-error-item tsm-error-exception">
            <span class="tsm-error-level"><?php echo esc_html($result['exception']['type']); ?></span>
            <span class="tsm-error-message"><?php echo esc_html($result['exception']['message']); ?></span>
            <span class="tsm-error-location">
                <?php echo esc_html($result['exception']['file']); ?>:<?php echo esc_html($result['exception']['line']); ?>
            </span>
            <pre class="tsm-stack-trace"><?php echo esc_html($result['exception']['trace']); ?></pre>
        </div>
        <?php endif; ?>

        <?php if (!empty($result['fatal'])): ?>
        <div class="tsm-error-item tsm-error-fatal">
            <span class="tsm-error-level">FATAL</span>
            <span class="tsm-error-message"><?php echo esc_html($result['fatal']['message']); ?></span>
            <span class="tsm-error-location">
                <?php echo esc_html($result['fatal']['file']); ?>:<?php echo esc_html($result['fatal']['line']); ?>
            </span>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="tsm-output">
        <h2>Output</h2>
        <pre class="tsm-output-content"><?php echo esc_html($result['output']); ?></pre>
    </section>

    <!-- json-view for structured data display -->
    <script src="<?php echo TSM_PLUGIN_URL; ?>assets/js/json-view.min.js"></script>
    <script>
    // Initialize JSON viewers for detected JSON output
    document.querySelectorAll('.tsm-json-data').forEach(function(el) {
        var data = JSON.parse(el.dataset.json);
        var view = new JSONView('json-viewer', data);
        view.expand(false); // Start collapsed
        el.appendChild(view.dom);
    });
    </script>
</body>
</html>
```

### CSS for Error Highlighting (EXEC-03)
```css
/* Source: Requirements EXEC-03 */
.tsm-errors {
    margin: 20px 0;
    padding: 15px;
    background: #fff5f5;
    border: 1px solid #feb2b2;
    border-radius: 4px;
}

.tsm-error-item {
    margin: 10px 0;
    padding: 10px;
    border-left: 4px solid;
}

.tsm-error-e_error,
.tsm-error-fatal {
    border-left-color: #e53e3e;
    background: #fff5f5;
}

.tsm-error-e_warning,
.tsm-error-e_user_warning {
    border-left-color: #ed8936;
    background: #fffaf0;
}

.tsm-error-e_notice,
.tsm-error-e_user_notice {
    border-left-color: #3182ce;
    background: #ebf8ff;
}

.tsm-error-exception {
    border-left-color: #9f7aea;
    background: #faf5ff;
}

.tsm-error-level {
    display: inline-block;
    padding: 2px 6px;
    margin-right: 10px;
    font-size: 12px;
    font-weight: bold;
    color: white;
    background: #e53e3e;
    border-radius: 3px;
}

.tsm-error-e_warning .tsm-error-level,
.tsm-error-e_user_warning .tsm-error-level {
    background: #ed8936;
}

.tsm-error-e_notice .tsm-error-level,
.tsm-error-e_user_notice .tsm-error-level {
    background: #3182ce;
}

.tsm-error-message {
    color: #2d3748;
}

.tsm-error-location {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: #718096;
    font-family: monospace;
}

.tsm-stack-trace {
    margin-top: 10px;
    padding: 10px;
    background: #2d3748;
    color: #e2e8f0;
    font-size: 12px;
    overflow-x: auto;
    border-radius: 4px;
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| eval() for code execution | include() with temp file | Always preferred | Better error handling, supports <?php tags |
| Catch Exception only | Catch Throwable | PHP 7.0 (2015) | Catches both Error and Exception classes |
| Custom JSON formatters | json-view library | 2020+ | Consistent DevTools-like UI |
| Manual error type mapping | PHP error constants | Always | Accurate error level detection |

**Deprecated/outdated:**
- `create_function()`: Deprecated in PHP 7.2, use anonymous functions
- `$errcontext` in error handler: Removed in PHP 8.0, don't rely on it
- `split()` and `ereg*()`: Use `preg_*` functions instead

## Open Questions

Things that couldn't be fully resolved:

1. **New Tab vs Modal for Results**
   - What we know: Requirements specify "new tab" (UI-05)
   - What's unclear: Should result also be shown inline for quick tests?
   - Recommendation: Primary action opens new tab; consider adding "preview" option later

2. **JSON Detection Heuristics**
   - What we know: Need to detect when output is JSON for special formatting
   - What's unclear: How to handle mixed output (text + JSON)
   - Recommendation: Detect JSON blocks with regex, wrap each in json-view container

3. **Execution Isolation Level**
   - What we know: Scripts run in same PHP process as WordPress
   - What's unclear: Whether true process isolation is needed
   - Recommendation: For v1, accept shared process; document risks; consider Action Scheduler for Phase 5

4. **History Retention Policy**
   - What we know: EXEC-14 says "store last 100 executions"
   - What's unclear: Per-script or global limit?
   - Recommendation: Implement global limit with option to keep more per-script

## Sources

### Primary (HIGH confidence)
- [PHP Manual: ob_start()](https://www.php.net/manual/en/function.ob-start.php) - Output buffering functions, nesting behavior
- [PHP Manual: set_error_handler()](https://www.php.net/manual/en/function.set-error-handler.php) - Error handling, ErrorException pattern
- [PHP Manual: memory_get_peak_usage()](https://www.php.net/manual/en/function.memory-get-peak-usage.php) - Memory tracking with real_usage
- [WordPress REST API Authentication](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/) - Cookie auth for execution endpoint
- Phase 1 Research - Database schema, security patterns

### Secondary (MEDIUM confidence)
- [json-view GitHub](https://github.com/richard-livingston/json-view) - Chrome DevTools-style JSON viewer
- [PHP Error and Exception Handling](https://blog.programster.org/php-error-and-exception-handling) - Tri-layer error capture pattern
- [Kinsta max_execution_time](https://kinsta.com/blog/wordpress-max-execution-time/) - Timeout best practices

### Tertiary (LOW confidence)
- WebSearch results on fatal error handling patterns - Community variations
- WebSearch results on responsive table CSS - Multiple approaches available

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All based on PHP official documentation
- Architecture (execution service): HIGH - Well-established patterns
- Error handling: HIGH - PHP manual verified
- Output formatting: MEDIUM - Library choice based on feature comparison
- UI patterns: MEDIUM - CSS patterns from community best practices

**Research date:** 2026-01-30
**Valid until:** 60 days (PHP error handling APIs are stable)
