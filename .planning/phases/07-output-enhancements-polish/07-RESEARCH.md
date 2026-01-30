# Phase 7: Output Enhancements & Polish - Research

**Researched:** 2026-01-30
**Domain:** File export formats, taxonomy systems, settings UI, security controls
**Confidence:** HIGH

## Summary

Phase 7 adds professional-grade polish to the Test Script Manager by implementing export functionality (CSV, JSON, Excel), organizing scripts with categories and tags, providing a template library for common patterns, and adding a settings page for security controls (IP whitelist, timeout configuration).

The research shows clear patterns in the WordPress ecosystem:
- **Export formats**: Use native PHP CSV functions, JSON with proper headers, and PHPSpreadsheet library for Excel
- **Taxonomy system**: Leverage WordPress's native category/tag patterns with custom tables (tsm_categories, tsm_script_tags already exist)
- **Template library**: Follow patterns from Code Snippets and WPCode plugins
- **Settings API**: Use WordPress Settings API with tabbed interface for organization
- **Security controls**: Implement IP whitelist validation in execution pipeline

**Primary recommendation:** Build on existing database schema (categories/tags tables already created in Phase 1), use PHPSpreadsheet for Excel export via Composer, implement Settings API with tabs for clean UX, and integrate security checks into ExecutionService.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHPSpreadsheet | 2.3+ | Excel export (.xlsx, .xls) | PHP Office suite, actively maintained, pure PHP, supports PHP 8.1+ through June 2026 |
| WordPress Settings API | Core | Settings page framework | Native WordPress, handles nonces/capabilities/sanitization automatically |
| WordPress WP_List_Table | Core | Bulk operations UI | Native admin list table class with built-in bulk action support |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| fputcsv() | PHP native | CSV export | Simple CSV generation, no dependencies |
| json_encode() | PHP native | JSON export | Native PHP, already used in OutputFormatter |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| PHPSpreadsheet | Manual XML generation | More control but complex, error-prone, not worth the effort |
| Settings API | Custom settings page | More flexibility but lose WordPress nonce/capability/sanitization benefits |
| Custom taxonomy | WordPress register_taxonomy() | Built-in UI but overkill for simple script tags |

**Installation:**
```bash
composer require phpoffice/phpspreadsheet
```

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── services/
│   ├── class-export-service.php       # Export logic (CSV, JSON, Excel)
│   ├── class-category-service.php     # Category CRUD operations
│   ├── class-template-service.php     # Template library management
│   └── class-settings-service.php     # Settings get/update operations
├── api/
│   ├── class-export-api.php           # Export REST endpoints
│   ├── class-categories-api.php       # Category/tag REST endpoints
│   └── class-templates-api.php        # Template REST endpoints
└── admin/
    └── class-settings-page.php        # Settings page UI (tabs)
```

### Pattern 1: Export Service with Format Strategy
**What:** Single service class with format-specific methods
**When to use:** Multiple export formats sharing common data fetching logic
**Example:**
```php
// Source: PHPSpreadsheet documentation + WordPress export plugin patterns
class ExportService {

    public static function export_execution($execution_id, $format) {
        $data = self::fetch_execution_data($execution_id);

        switch ($format) {
            case 'csv':
                return self::export_csv($data);
            case 'json':
                return self::export_json($data);
            case 'excel':
                return self::export_excel($data);
        }
    }

    private static function export_csv($data) {
        ob_start();
        $handle = fopen('php://output', 'w');

        // Headers
        fputcsv($handle, array_keys($data[0]));

        // Rows
        foreach ($data as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
        return ob_get_clean();
    }

    private static function export_excel($data) {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $sheet->fromArray(array_keys($data[0]), NULL, 'A1');

        // Data
        $sheet->fromArray($data, NULL, 'A2');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');
        return ob_get_clean();
    }
}
```

### Pattern 2: Settings API with Tabs
**What:** WordPress Settings API implementation with tabbed interface
**When to use:** Settings page with multiple logical sections
**Example:**
```php
// Source: WordPress Settings API documentation + tab pattern from community
class SettingsPage {

    public function init() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_settings_page'));
    }

    public function register_settings() {
        // General tab
        register_setting('tsm_general', 'tsm_execution_timeout');
        register_setting('tsm_general', 'tsm_output_limit');

        // Security tab
        register_setting('tsm_security', 'tsm_ip_whitelist_enabled');
        register_setting('tsm_security', 'tsm_ip_whitelist');

        add_settings_section(
            'tsm_general_section',
            'General Settings',
            null,
            'tsm_general'
        );

        add_settings_field(
            'tsm_execution_timeout',
            'Execution Timeout',
            array($this, 'render_timeout_field'),
            'tsm_general',
            'tsm_general_section'
        );
    }

    public function render_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        ?>
        <div class="wrap">
            <h1>Test Script Manager Settings</h1>
            <nav class="nav-tab-wrapper">
                <a href="?page=test-script-manager-settings&tab=general"
                   class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    General
                </a>
                <a href="?page=test-script-manager-settings&tab=security"
                   class="nav-tab <?php echo $active_tab === 'security' ? 'nav-tab-active' : ''; ?>">
                    Security
                </a>
            </nav>
            <form method="post" action="options.php">
                <?php
                settings_fields('tsm_' . $active_tab);
                do_settings_sections('tsm_' . $active_tab);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
```

### Pattern 3: Category Service with Many-to-Many
**What:** Service layer for category/tag operations using junction table
**When to use:** Many-to-many relationships (script can have multiple tags)
**Example:**
```php
// Source: WordPress patterns + existing database schema
class CategoryService {

    public static function get_script_categories($script_id) {
        global $wpdb;
        $tags_table = Database::get_table_name(Database::TABLE_SCRIPT_TAGS);
        $cat_table = Database::get_table_name(Database::TABLE_CATEGORIES);

        $sql = "SELECT c.* FROM $cat_table c
                INNER JOIN $tags_table st ON c.id = st.category_id
                WHERE st.script_id = %d";

        return $wpdb->get_results($wpdb->prepare($sql, $script_id), ARRAY_A);
    }

    public static function set_script_categories($script_id, $category_ids) {
        global $wpdb;
        $tags_table = Database::get_table_name(Database::TABLE_SCRIPT_TAGS);

        // Remove existing
        $wpdb->delete($tags_table, array('script_id' => $script_id), array('%d'));

        // Add new
        foreach ($category_ids as $cat_id) {
            $wpdb->insert(
                $tags_table,
                array('script_id' => $script_id, 'category_id' => $cat_id),
                array('%d', '%d')
            );
        }
    }
}
```

### Pattern 4: Template Library with JSON Storage
**What:** Store script templates as JSON files or database rows
**When to use:** Predefined code snippets users can quickly create from
**Example:**
```php
// Source: Code Snippets plugin pattern
class TemplateService {

    public static function get_templates() {
        // Store in plugin directory as JSON
        $template_file = TSM_PLUGIN_DIR . 'templates/script-templates.json';

        if (!file_exists($template_file)) {
            return array();
        }

        $json = file_get_contents($template_file);
        return json_decode($json, true);
    }

    public static function create_from_template($template_id, $name) {
        $templates = self::get_templates();

        if (!isset($templates[$template_id])) {
            return new \WP_Error('invalid_template', 'Template not found');
        }

        $template = $templates[$template_id];

        return ScriptService::create(array(
            'name' => $name,
            'code' => $template['code'],
            'language' => $template['language']
        ));
    }
}

// templates/script-templates.json
{
  "db-query": {
    "name": "Database Query",
    "description": "Execute a WordPress database query",
    "language": "php",
    "code": "<?php\nglobal $wpdb;\n$results = $wpdb->get_results(\"SELECT * FROM {$wpdb->posts} LIMIT 10\");\nprint_r($results);"
  },
  "user-list": {
    "name": "List Users",
    "description": "Get all WordPress users",
    "language": "php",
    "code": "<?php\n$users = get_users();\nforeach ($users as $user) {\n    echo $user->display_name . \"\\n\";\n}"
  }
}
```

### Pattern 5: IP Whitelist Validation
**What:** Validate client IP against whitelist before execution
**When to use:** Security requirement to restrict execution to specific IPs
**Example:**
```php
// Source: WordPress security plugin patterns
class SettingsService {

    public static function is_ip_whitelisted($ip = null) {
        if ($ip === null) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        $whitelist_enabled = get_option('tsm_ip_whitelist_enabled', false);

        if (!$whitelist_enabled) {
            return true; // Whitelist disabled, allow all
        }

        $whitelist = get_option('tsm_ip_whitelist', '');
        $allowed_ips = array_filter(array_map('trim', explode("\n", $whitelist)));

        if (empty($allowed_ips)) {
            return true; // No IPs configured, allow all
        }

        // Check exact match
        if (in_array($ip, $allowed_ips)) {
            return true;
        }

        // Check CIDR ranges
        foreach ($allowed_ips as $allowed) {
            if (strpos($allowed, '/') !== false) {
                if (self::ip_in_range($ip, $allowed)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function ip_in_range($ip, $range) {
        list($subnet, $bits) = explode('/', $range);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask;
        return ($ip & $mask) == $subnet;
    }
}

// In ExecutionService::execute()
if (!SettingsService::is_ip_whitelisted()) {
    return new \WP_Error(
        'tsm_ip_not_whitelisted',
        __('Your IP address is not whitelisted for script execution.', 'test-script-manager')
    );
}
```

### Anti-Patterns to Avoid
- **Don't use WordPress register_taxonomy()** for script categories - it's designed for post types and adds unnecessary UI complexity. Use custom tables with simpler UI.
- **Don't generate Excel XML manually** - use PHPSpreadsheet library instead. Manual XML is error-prone and hard to maintain.
- **Don't store settings in separate custom tables** - use WordPress options table with Settings API for consistency and automatic handling.
- **Don't validate IP whitelist on every page load** - only check when user clicks Execute button to avoid performance impact.

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Excel file generation | Custom XML writer for .xlsx format | PHPSpreadsheet library | Excel format is complex (ZIP with XML files), has edge cases (formulas, formatting, cell types), PHPSpreadsheet handles all of this |
| Settings page with validation | Custom POST handler with nonces | WordPress Settings API | Settings API automatically handles nonces, capabilities, sanitization, and form rendering |
| CSV escaping | Manual quote/comma handling | fputcsv() | Handles edge cases (quotes in quotes, newlines in cells, null bytes) |
| IP range validation | String parsing for CIDR | ip2long() with bitwise operations | CIDR notation is tricky (subnet masks, network addresses), bitwise math is the correct approach |
| Bulk operations UI | Custom checkbox system | WP_List_Table bulk actions | WP_List_Table provides standard WordPress UX with checkboxes, select-all, and bulk action dropdown |

**Key insight:** Export functionality has many edge cases (memory limits for large datasets, special characters in CSV, Excel formula injection risks). Using proven libraries and WordPress core APIs prevents these issues.

## Common Pitfalls

### Pitfall 1: Memory Exhaustion with Large Exports
**What goes wrong:** Loading entire result dataset into memory before export causes PHP memory limit errors
**Why it happens:** Execution logs can have large output fields (up to 10MB per execution), multiplied by many rows
**How to avoid:** Use streaming approach - fetch and write in chunks
**Warning signs:** PHP fatal error "Allowed memory size exhausted" when exporting more than 50-100 execution logs

**Prevention strategy:**
```php
// BAD: Load all data then export
$all_data = $wpdb->get_results("SELECT * FROM tsm_execution_logs");
foreach ($all_data as $row) {
    fputcsv($handle, $row);
}

// GOOD: Stream in chunks
$offset = 0;
$chunk_size = 50;
while (true) {
    $chunk = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM tsm_execution_logs LIMIT %d OFFSET %d", $chunk_size, $offset)
    );
    if (empty($chunk)) break;

    foreach ($chunk as $row) {
        fputcsv($handle, $row);
    }
    $offset += $chunk_size;
}
```

### Pitfall 2: CSV Injection Vulnerability
**What goes wrong:** Malicious code in script output can execute in Excel when exported to CSV
**Why it happens:** Excel treats cells starting with `=`, `+`, `@`, `-` as formulas
**How to avoid:** Prefix dangerous cells with single quote or tab character
**Warning signs:** User reports Excel showing formula errors or unexpected behavior when opening exported CSV

**Prevention strategy:**
```php
private static function sanitize_csv_cell($value) {
    if (is_string($value) && strlen($value) > 0) {
        $first_char = $value[0];
        if (in_array($first_char, array('=', '+', '-', '@'))) {
            // Prefix with tab to prevent formula injection
            $value = "\t" . $value;
        }
    }
    return $value;
}
```

### Pitfall 3: Output Already Started Error
**What goes wrong:** Headers cannot be sent because PHP output already started (even whitespace)
**Why it happens:** Template file has whitespace before `<?php` tag, or echo/error happens before headers
**How to avoid:** Use output buffering, verify no output before headers, check file encodings
**Warning signs:** "Cannot modify header information - headers already sent" error when clicking export

**Prevention strategy:**
```php
// Start output buffering at the very beginning of export endpoint
public function export_csv(WP_REST_Request $request) {
    ob_start();
    ob_clean(); // Clear any accidental output

    // Set headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="execution-' . $execution_id . '.csv"');

    // Generate CSV
    $csv_content = ExportService::export_csv($execution_id);

    echo $csv_content;
    exit;
}
```

### Pitfall 4: Settings Not Saving (Options API)
**What goes wrong:** Settings form submits but values don't persist
**Why it happens:** Missing register_setting() call or incorrect option group name
**How to avoid:** Ensure register_setting() matches settings_fields() parameter exactly
**Warning signs:** Form submits successfully but refreshing page shows old values

**Prevention strategy:**
```php
// In register_settings()
register_setting('tsm_general', 'tsm_execution_timeout'); // First param is option group

// In render_page()
settings_fields('tsm_general'); // Must match register_setting() group name
```

### Pitfall 5: Category Orphans on Script Delete
**What goes wrong:** Deleting a script leaves orphaned records in tsm_script_tags table
**Why it happens:** No foreign key cascade delete, manual cleanup not implemented
**How to avoid:** Add cleanup in ScriptService::delete()
**Warning signs:** tsm_script_tags table growing larger than tsm_scripts table

**Prevention strategy:**
```php
// In ScriptService::delete()
public static function delete($script_id) {
    global $wpdb;

    // Delete script-tag relationships FIRST
    $tags_table = Database::get_table_name(Database::TABLE_SCRIPT_TAGS);
    $wpdb->delete($tags_table, array('script_id' => $script_id), array('%d'));

    // Then delete script
    $scripts_table = Database::get_table_name(Database::TABLE_SCRIPTS);
    $wpdb->delete($scripts_table, array('id' => $script_id), array('%d'));
}
```

## Code Examples

Verified patterns from official sources:

### Export REST Endpoint with File Download
```php
// Source: WordPress REST API handbook + PHPSpreadsheet documentation
class Export_API {

    public function register_routes() {
        register_rest_route(
            'test-script-manager/v1',
            '/executions/(?P<id>\d+)/export',
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'export_execution'),
                'permission_callback' => array('TSM\Security', 'check_admin_permission'),
                'args'                => array(
                    'id'     => array(
                        'required'          => true,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                    'format' => array(
                        'default'           => 'csv',
                        'type'              => 'string',
                        'enum'              => array('csv', 'json', 'excel'),
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );
    }

    public function export_execution(WP_REST_Request $request) {
        $execution_id = $request->get_param('id');
        $format = $request->get_param('format');

        // Get execution data
        $execution = $this->get_execution_data($execution_id);
        if (is_wp_error($execution)) {
            return $execution;
        }

        // Start output buffering
        ob_start();
        ob_clean();

        // Set headers based on format
        switch ($format) {
            case 'csv':
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="execution-' . $execution_id . '.csv"');
                echo ExportService::export_csv($execution);
                break;

            case 'json':
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename="execution-' . $execution_id . '.json"');
                echo json_encode($execution, JSON_PRETTY_PRINT);
                break;

            case 'excel':
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="execution-' . $execution_id . '.xlsx"');
                echo ExportService::export_excel($execution);
                break;
        }

        exit;
    }
}
```

### Bulk Delete Scripts with WP_List_Table
```php
// Source: WordPress WP_List_Table documentation
class Scripts_List_Table extends WP_List_Table {

    protected function get_bulk_actions() {
        return array(
            'delete'   => 'Delete',
            'category' => 'Change Category',
        );
    }

    protected function process_bulk_action() {
        $action = $this->current_action();

        if (!$action) {
            return;
        }

        // Verify nonce
        check_admin_referer('bulk-scripts');

        $script_ids = isset($_REQUEST['script']) ? array_map('absint', $_REQUEST['script']) : array();

        if (empty($script_ids)) {
            return;
        }

        switch ($action) {
            case 'delete':
                foreach ($script_ids as $script_id) {
                    ScriptService::delete($script_id);
                }
                wp_redirect(add_query_arg('deleted', count($script_ids), wp_get_referer()));
                exit;

            case 'category':
                $category_id = isset($_REQUEST['category_id']) ? absint($_REQUEST['category_id']) : 0;
                foreach ($script_ids as $script_id) {
                    CategoryService::set_script_categories($script_id, array($category_id));
                }
                wp_redirect(add_query_arg('updated', count($script_ids), wp_get_referer()));
                exit;
        }
    }
}
```

### Settings Sanitization Callbacks
```php
// Source: WordPress Settings API documentation
public function register_settings() {
    // Timeout: integer between 1 and 300
    register_setting(
        'tsm_general',
        'tsm_execution_timeout',
        array(
            'type'              => 'integer',
            'default'           => 30,
            'sanitize_callback' => function($value) {
                return max(1, min(300, absint($value)));
            },
        )
    );

    // IP whitelist: multiline text with IP validation
    register_setting(
        'tsm_security',
        'tsm_ip_whitelist',
        array(
            'type'              => 'string',
            'default'           => '',
            'sanitize_callback' => function($value) {
                $lines = explode("\n", $value);
                $valid_ips = array();

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;

                    // Validate IP or CIDR
                    if (filter_var($line, FILTER_VALIDATE_IP) || $this->is_valid_cidr($line)) {
                        $valid_ips[] = $line;
                    }
                }

                return implode("\n", $valid_ips);
            },
        )
    );
}

private function is_valid_cidr($cidr) {
    if (!str_contains($cidr, '/')) {
        return false;
    }

    list($ip, $bits) = explode('/', $cidr);

    return filter_var($ip, FILTER_VALIDATE_IP) && is_numeric($bits) && $bits >= 0 && $bits <= 32;
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| PHPExcel | PHPSpreadsheet | 2017 | PHPExcel deprecated, PHPSpreadsheet is namespaced, PSR-compliant, PHP 7+ |
| register_taxonomy() for everything | Custom tables for app-specific taxonomies | Ongoing pattern | Better performance, simpler UI, no post type coupling |
| Custom settings tables | WordPress Options API | Since WP 2.7 (2008) | Automatic handling, better compatibility, standard UX |
| Manual header() calls | wp_send_json() for JSON | WP 3.5+ (2012) | Handles headers automatically, proper exit, CORS support |
| Action Scheduler for exports | Direct export in request | Still valid | For large exports, background job prevents timeout |

**Deprecated/outdated:**
- **PHPExcel**: Use PHPSpreadsheet instead (PHPExcel abandoned in 2015)
- **fgetcsv() with fopen('data.csv')**: Works but fputcsv() with php://output is cleaner for exports
- **Direct $_POST handling**: Use WordPress Settings API sanitize callbacks instead

## Open Questions

Things that couldn't be fully resolved:

1. **Should result comparison support side-by-side diff UI?**
   - What we know: EXEC-13 requires "result comparison between executions", Phase 6 has Monaco Diff Editor for version comparison
   - What's unclear: Whether to reuse Monaco Diff Editor for execution result comparison, or just show two results side by side with simple highlight
   - Recommendation: Start with simple side-by-side display (two columns with same-row highlighting). If users request diff view, add Monaco Diff Editor in future iteration

2. **How many templates should ship with plugin?**
   - What we know: STORE-13 requires "template library for common script patterns"
   - What's unclear: Optimal number (too few = not useful, too many = overwhelming)
   - Recommendation: Start with 5-8 essential templates (DB query, user list, post query, option get/set, cache clear, image resize test, WooCommerce order query), add more based on user feedback

3. **Should settings page include export/import plugin settings?**
   - What we know: Settings page needed for timeout/IP whitelist, common pattern in WordPress plugins
   - What's unclear: Whether to include plugin configuration export/import feature
   - Recommendation: Defer to v2 (ADV-03 already mentions script import/export, can combine with settings export)

4. **Excel export memory limits for very large results?**
   - What we know: PHPSpreadsheet loads entire spreadsheet in memory, execution logs can have 10MB output field
   - What's unclear: Whether to use streaming writer for very large exports (100+ rows with large output)
   - Recommendation: Start with normal PHPSpreadsheet writer (handles most cases). If users hit memory limits, add streaming writer option or limit Excel export to executions with smaller output

## Sources

### Primary (HIGH confidence)
- [PHPSpreadsheet GitHub](https://github.com/PHPOffice/PhpSpreadsheet) - PHP Office suite, actively maintained
- [PHPSpreadsheet Documentation](https://phpspreadsheet.readthedocs.io/) - Official documentation with PHP 8.1+ support through June 2026
- [WordPress Settings API Documentation](https://developer.wordpress.org/plugins/settings/settings-api/) - Official WordPress plugin handbook
- [WordPress WP_List_Table Documentation](https://developer.wordpress.org/reference/classes/wp_list_table/) - Official WordPress developer reference
- [PHP fputcsv() Manual](https://www.php.net/manual/en/function.fputcsv.php) - PHP native CSV function documentation

### Secondary (MEDIUM confidence)
- [WordPress Settings API Explained - Press Coders](https://presscoders.com/wordpress-settings-api-explained/) - Community tutorial with best practices
- [How to Create Tabs on WordPress Settings Pages - Smashing Magazine](https://www.smashingmagazine.com/2011/10/create-tabs-wordpress-settings-pages/) - Tab implementation pattern
- [Code Snippets WordPress Plugin](https://wordpress.org/plugins/code-snippets/) - Template library pattern reference
- [WPCode Plugin](https://wpcode.com/) - 3000+ snippet library, cloud storage pattern
- [How to Restrict Login Access by Whitelisting IP Addresses - Shield Security](https://getshieldsecurity.com/blog/how-to-restrict-login-access-by-whitelisting-ip-addresses-in-wordpress/) - IP whitelist implementation pattern

### Tertiary (LOW confidence)
- WebSearch results about CSV export best practices - General PHP practices, needs verification with WordPress context
- WebSearch results about Excel export - Multiple sources agree on PHPSpreadsheet, but version compatibility should be tested

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - PHPSpreadsheet is industry standard, Settings API is core WordPress, CSV/JSON are native PHP
- Architecture: HIGH - Patterns verified in existing WordPress plugins (Code Snippets, WP All Export) and official documentation
- Pitfalls: MEDIUM - Common issues documented in community forums and GitHub issues, but project-specific edge cases may emerge

**Research date:** 2026-01-30
**Valid until:** 60 days (PHP library landscape stable, WordPress Settings API unchanged since WP 2.7)

**Technologies confirmed compatible:**
- PHP 8.0+ (project requirement)
- WordPress 6.4+ (project requirement)
- PHPSpreadsheet 2.3+ (requires PHP 8.1 minimum, but project uses PHP 8.0 - check compatibility or upgrade)
- Composer available (Action Scheduler already uses Composer autoloader)

**Note:** PHPSpreadsheet 2.x requires PHP 8.1+, but this project requires PHP 8.0+. Need to check if PHPSpreadsheet 1.x is still maintained, or consider requiring PHP 8.1+ for Phase 7. Recommendation: Update plugin requirement to PHP 8.1+ (current stable version as of 2026-01-30).
