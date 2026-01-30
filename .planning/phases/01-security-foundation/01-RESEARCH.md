# Phase 1: Security Foundation - Research

**Researched:** 2026-01-30
**Domain:** WordPress Plugin Security, Database Schema, Admin UI
**Confidence:** HIGH

## Summary

This research covers the security foundation for a WordPress plugin that manages and executes test scripts. The phase involves implementing authentication/authorization layers, creating custom database tables, building a dangerous function detection system, and registering an admin menu.

WordPress provides robust built-in mechanisms for all these requirements: nonces for CSRF protection, capabilities for authorization, `dbDelta()` for database management, and `add_menu_page()` for admin UI. The dangerous function detection will use PHP's tokenizer or regex pattern matching to scan code before execution.

**Primary recommendation:** Use WordPress's native security APIs (nonces, capabilities, cookie authentication) combined with a custom code scanner that blocks dangerous PHP functions based on WP_DEBUG environment.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Component | Version | Purpose | Why Standard |
|-----------|---------|---------|--------------|
| WordPress REST API | Core | Authentication & API endpoints | Built-in cookie/nonce auth, automatic validation |
| WordPress dbDelta | Core | Database table creation & migration | Handles CREATE/ALTER automatically, idempotent |
| WordPress Capabilities | Core | Authorization (manage_options) | Standard permission system, role-based |
| PHP Tokenizer | PHP 5+ | Dangerous function detection | Built-in, accurate parsing (token_get_all) |

### Supporting
| Component | Version | Purpose | When to Use |
|-----------|---------|---------|-------------|
| WordPress Nonces | Core | CSRF protection | All AJAX/REST requests |
| $wpdb | Core | Database queries | All database operations |
| Dashicons | Core | Admin menu icons | Admin UI |
| WordPress Options API | Core | Version tracking | DB schema versioning |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| PHP Tokenizer | Regex pattern | Regex is simpler but can be bypassed; tokenizer is more accurate |
| Custom tables | WordPress meta tables | Custom tables offer better query performance and schema control |
| REST API | Admin AJAX | REST API is newer, better documented, more RESTful |

**Installation:**
No external dependencies required. All components are part of WordPress core or PHP.

## Architecture Patterns

### Recommended Project Structure
```
test-script-manager/
├── test-script-manager.php      # Main plugin file, activation hooks
├── includes/
│   ├── class-plugin.php         # Singleton plugin loader
│   ├── class-database.php       # Database table creation with dbDelta
│   ├── class-security.php       # Nonce validation, capability checks
│   ├── class-code-scanner.php   # Dangerous function detection
│   ├── api/
│   │   └── class-scripts-api.php  # REST API endpoints
│   └── admin/
│       └── class-admin-page.php   # Admin menu and page rendering
└── assets/
    ├── css/
    └── js/
```

### Pattern 1: Singleton Plugin Loader
**What:** Single instance plugin class that manages initialization
**When to use:** Plugin entry point, dependency loading
**Example:**
```php
// Source: WordPress Plugin Handbook pattern
class Plugin {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init() {
        $this->load_dependencies();
        $this->register_hooks();
    }
}
```

### Pattern 2: Database Schema with Version Tracking
**What:** Track DB version in options to handle upgrades
**When to use:** Any custom table creation
**Example:**
```php
// Source: https://developer.wordpress.org/plugins/creating-tables-with-plugins/
class Database {
    const DB_VERSION = '1.0.0';

    public static function create_tables() {
        global $wpdb;

        $installed_version = get_option('tsm_db_version', '0');
        if (version_compare($installed_version, self::DB_VERSION, '>=')) {
            return; // Already up to date
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        // CREATE TABLE statements...
        dbDelta($sql);

        update_option('tsm_db_version', self::DB_VERSION);
    }
}
```

### Pattern 3: REST API with Permission Callback
**What:** REST endpoints with proper authorization
**When to use:** All AJAX/REST operations
**Example:**
```php
// Source: https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
register_rest_route('tsm/v1', '/scripts', [
    'methods' => 'GET',
    'callback' => [$this, 'get_scripts'],
    'permission_callback' => [$this, 'check_permission'],
]);

public function check_permission() {
    return current_user_can('manage_options');
}
```

### Anti-Patterns to Avoid
- **Relying on nonces alone for authorization:** Nonces prevent CSRF but don't verify user capabilities. Always pair with `current_user_can()`.
- **Hardcoding paths:** Never use `__DIR__` for wp-load.php resolution in symlinked environments. Use `ABSPATH` constant.
- **Running dbDelta on every page load:** Check version first, only run when needed.
- **Using IF NOT EXISTS with dbDelta:** dbDelta handles this internally; including it prevents ALTER operations.

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| CSRF protection | Custom token system | WordPress nonces | Battle-tested, automatic expiration, integrated |
| User authorization | Session-based auth | current_user_can() | Integrated with WordPress roles/capabilities |
| Database migration | Raw SQL with version checks | dbDelta() | Handles ALTER, column changes, idempotent |
| Admin menu icons | Custom icon uploads | Dashicons | Built-in, consistent with WP admin |
| Path resolution | __DIR__ calculations | ABSPATH, plugins_url() | Symlink-safe, relocatable |

**Key insight:** WordPress has mature, battle-tested solutions for security. Custom implementations introduce vulnerabilities and maintenance burden.

## Common Pitfalls

### Pitfall 1: Nonce Verification Timing
**What goes wrong:** Verifying nonce in REST callback instead of permission_callback
**Why it happens:** Misunderstanding of REST API flow
**How to avoid:** Use permission_callback for all authorization; nonces are auto-verified for cookie auth
**Warning signs:** `wp_verify_nonce()` calls inside main callback

### Pitfall 2: dbDelta SQL Formatting
**What goes wrong:** Tables not created or columns not updated
**Why it happens:** dbDelta has strict formatting requirements
**How to avoid:** Follow exact formatting rules:
- Two spaces between PRIMARY KEY and definition
- Each field on its own line
- Use KEY not INDEX
- No backticks around field names
- Specify field lengths (e.g., `bigint(20)`)
**Warning signs:** dbDelta returns empty array, tables don't exist

### Pitfall 3: Symlink Path Resolution
**What goes wrong:** wp-load.php not found, wrong URLs for assets
**Why it happens:** `__FILE__` and `__DIR__` resolve symlinks
**How to avoid:** Use `ABSPATH` for WordPress core paths, `plugins_url()` for assets
**Warning signs:** "Failed to open stream" errors, 404 on assets

### Pitfall 4: WP_DEBUG Environment Detection
**What goes wrong:** Dangerous functions blocked in development, allowed in production
**Why it happens:** Inverted logic or checking wrong constant
**How to avoid:** Explicitly check: `if (defined('WP_DEBUG') && WP_DEBUG) { /* allow */ }`
**Warning signs:** Different behavior between environments

### Pitfall 5: Capability Check in Callback Only
**What goes wrong:** Unauthorized users can trigger callback (even if it fails later)
**Why it happens:** Only checking permissions inside main callback
**How to avoid:** Always use permission_callback; it blocks request before callback runs
**Warning signs:** REST requests reaching callback for unauthorized users

## Code Examples

Verified patterns from official sources:

### Nonce Creation and Verification for REST API
```php
// Source: https://developer.wordpress.org/apis/security/nonces/

// In PHP (for JavaScript consumption):
wp_localize_script('tsm-admin', 'tsmData', [
    'nonce' => wp_create_nonce('wp_rest'),
    'restUrl' => rest_url('tsm/v1/'),
]);

// In JavaScript:
fetch(tsmData.restUrl + 'scripts', {
    headers: {
        'X-WP-Nonce': tsmData.nonce
    }
});

// Note: Nonce verification is automatic for cookie authentication
// via rest_cookie_check_errors() - no manual verification needed
```

### Database Table Creation with dbDelta
```php
// Source: https://developer.wordpress.org/plugins/creating-tables-with-plugins/

public static function create_scripts_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'tsm_scripts';
    $charset_collate = $wpdb->get_charset_collate();

    // Note: Two spaces before (id), each field on own line
    $sql = "CREATE TABLE $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        slug varchar(255) NOT NULL,
        code longtext NOT NULL,
        language varchar(20) NOT NULL DEFAULT 'php',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_executed_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY slug (slug),
        KEY created_at (created_at),
        KEY last_executed_at (last_executed_at)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
```

### Admin Menu Registration
```php
// Source: https://developer.wordpress.org/reference/functions/add_menu_page/

public function register_admin_menu() {
    add_action('admin_menu', function() {
        $hook = add_menu_page(
            __('Test Scripts', 'test-script-manager'),  // Page title
            __('Test Scripts', 'test-script-manager'),  // Menu title (shorter name)
            'manage_options',                           // Capability
            'test-script-manager',                      // Menu slug
            [$this, 'render_admin_page'],               // Callback
            'dashicons-editor-code',                    // Icon
            80                                          // Position
        );

        // Enqueue scripts only on this page
        add_action("load-{$hook}", [$this, 'enqueue_scripts']);
    });
}

public function render_admin_page() {
    // Double-check capability in callback
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have permission to access this page.'));
    }

    // Render page content
}
```

### Dangerous Function Detection
```php
// Source: Security best practices, PHP tokenizer documentation

class CodeScanner {
    private const DANGEROUS_FUNCTIONS = [
        'eval',
        'exec',
        'system',
        'shell_exec',
        'passthru',
        'popen',
        'proc_open',
        'pcntl_exec',
        'create_function',  // Deprecated in PHP 7.2
    ];

    /**
     * Scan code for dangerous functions
     *
     * @param string $code PHP code to scan
     * @return array List of found dangerous functions
     */
    public static function scan(string $code): array {
        $found = [];

        // Use tokenizer for accurate parsing
        $tokens = token_get_all('<?php ' . $code);

        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_STRING) {
                $function_name = strtolower($token[1]);
                if (in_array($function_name, self::DANGEROUS_FUNCTIONS, true)) {
                    $found[] = $token[1];
                }
            }
        }

        return array_unique($found);
    }

    /**
     * Check if dangerous functions should be blocked
     *
     * @return bool True if dangerous functions should be blocked
     */
    public static function should_block_dangerous(): bool {
        // Only allow dangerous functions in development
        return !(defined('WP_DEBUG') && WP_DEBUG);
    }
}
```

### REST API Endpoint Registration
```php
// Source: https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/

class Scripts_API {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        $namespace = 'tsm/v1';

        register_rest_route($namespace, '/scripts', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_scripts'],
                'permission_callback' => [$this, 'check_permission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_script'],
                'permission_callback' => [$this, 'check_permission'],
            ],
        ]);
    }

    public function check_permission(): bool {
        return current_user_can('manage_options');
    }
}
```

### WordPress Path Resolution (Symlink Safe)
```php
// Source: https://developer.wordpress.org/plugins/plugin-basics/determining-plugin-and-content-directories/

// For plugin file paths (use in main plugin file):
define('TSM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TSM_PLUGIN_URL', plugin_dir_url(__FILE__));

// For including wp-load.php (if needed outside WordPress):
// ALWAYS use ABSPATH, never __DIR__ calculations
$wp_load = ABSPATH . 'wp-load.php';
if (file_exists($wp_load)) {
    require_once $wp_load;
}

// For temporary script files (as per CONTEXT.md decision):
$temp_script_dir = ABSPATH . 'test-scripts/';
if (!file_exists($temp_script_dir)) {
    wp_mkdir_p($temp_script_dir);
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Admin AJAX | REST API | WordPress 4.7 (2016) | Better structure, automatic nonce verification |
| `mysql_*` functions | `$wpdb` methods | WordPress 3.9 (2014) | Prepared statements, security |
| Hardcoded paths | WordPress path functions | Always recommended | Symlink compatibility |
| `/e` modifier in preg_replace | preg_replace_callback | PHP 7.0 (2015) | Security, /e deprecated |

**Deprecated/outdated:**
- `create_function()`: Deprecated in PHP 7.2, use anonymous functions
- User levels: Deprecated, use capabilities
- `mysql_*` functions: Use `$wpdb` methods

## Open Questions

Things that couldn't be fully resolved:

1. **Execution Timeout Implementation**
   - What we know: PHP has `set_time_limit()`, WordPress has `wp_max_execution_time` filter
   - What's unclear: Best approach for script-specific timeouts without affecting WordPress
   - Recommendation: Use `set_time_limit()` before script execution, restore after

2. **Script File vs Database Storage**
   - What we know: CONTEXT.md specifies temporary files in `{ABSPATH}/test-scripts/`
   - What's unclear: Whether to also keep code in database or only in files
   - Recommendation: Keep code in database (tsm_scripts), generate temp files only for execution

3. **category_id Column Discrepancy**
   - What we know: Requirements mention `category_id` in tsm_scripts, but tsm_script_tags is a many-to-many table
   - What's unclear: Should scripts have a primary category and additional tags?
   - Recommendation: Clarify with user; current design uses only many-to-many

## Sources

### Primary (HIGH confidence)
- [WordPress Nonces Documentation](https://developer.wordpress.org/apis/security/nonces/) - Nonce creation, verification, lifetime
- [WordPress REST API Authentication](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/) - Cookie auth, nonce passing
- [WordPress Creating Tables with Plugins](https://developer.wordpress.org/plugins/creating-tables-with-plugins/) - dbDelta patterns
- [WordPress add_menu_page](https://developer.wordpress.org/reference/functions/add_menu_page/) - Admin menu registration
- [WordPress Plugin Paths](https://developer.wordpress.org/plugins/plugin-basics/determining-plugin-and-content-directories/) - Path functions

### Secondary (MEDIUM confidence)
- [WordPress dbDelta Function](https://developer.wordpress.org/reference/functions/dbdelta/) - Function details
- [WordPress Trac #13550](https://core.trac.wordpress.org/ticket/13550) - Symlink handling
- [Kinsta Symlinks Guide](https://kinsta.com/blog/managing-wordpress-development-with-symlinks/) - Symlink best practices

### Tertiary (LOW confidence)
- WebSearch results on PHP dangerous function detection - Pattern variations
- WebSearch results on preg_match bypass techniques - Security considerations

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All based on WordPress official documentation
- Architecture: HIGH - Following established WordPress plugin patterns
- Database: HIGH - Using documented dbDelta best practices
- Security (nonces/capabilities): HIGH - Official WordPress security APIs
- Dangerous function detection: MEDIUM - Combines official PHP docs with community patterns
- Symlink handling: MEDIUM - Based on WordPress Trac discussions and community guides

**Research date:** 2026-01-30
**Valid until:** 60 days (WordPress security APIs are stable)
