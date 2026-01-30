# Stack Research: WordPress Test Script Manager

**Domain:** WordPress Admin Development Tools - Code Editor & Script Execution
**Researched:** 2026-01-30
**Confidence:** HIGH (verified via npm, GitHub API, and official documentation)

---

## Executive Summary

For building a WordPress admin plugin with Monaco Editor integration and background script execution, the recommended stack leverages **Monaco Editor 0.55.x** with AMD loader approach (avoiding Webpack complexity in WordPress context), **@wordpress/scripts** for build tooling, **Action Scheduler 3.9.x** for background jobs, and WordPress REST API with proper nonce/capability authentication.

This stack prioritizes:
1. **Developer experience** - Monaco Editor provides VS Code-quality editing
2. **WordPress ecosystem compatibility** - Uses standard WordPress patterns
3. **Reliability** - Action Scheduler handles background jobs robustly
4. **Security** - Layered approach appropriate for development tools

---

## Recommended Stack

### Core Technologies

| Technology | Version | Purpose | Why Recommended | Confidence |
|------------|---------|---------|-----------------|------------|
| **PHP** | 8.0+ | Backend logic | WordPress 6.4+ requires PHP 7.4+; 8.0+ for null-safe operators, named arguments, better performance | HIGH |
| **WordPress** | 6.4+ | Platform | Minimum for Action Scheduler 3.9.x; provides REST API, capability system | HIGH |
| **Monaco Editor** | 0.55.1 | Code editor | Same engine as VS Code; syntax highlighting, autocomplete, multi-cursor for 50+ languages | HIGH |
| **Action Scheduler** | 3.9.3 | Background jobs | Proven at 10,000+ jobs/hour scale; built-in admin UI for monitoring | HIGH |

### Frontend Libraries

| Library | Version | Purpose | Why Recommended | Confidence |
|---------|---------|---------|-----------------|------------|
| **monaco-editor** | 0.55.1 | Browser code editor | Latest stable; MIT license; no peer dependencies | HIGH |
| **@monaco-editor/loader** | 1.4.0 | CDN loading | Avoids Webpack complexity; loads from jsDelivr CDN | HIGH |
| **Tailwind CSS** | 3.4.x | UI styling | Utility-first; consistent with WordPress admin patterns | MEDIUM |

### Build Tools

| Tool | Version | Purpose | Notes | Confidence |
|------|---------|---------|-------|------------|
| **@wordpress/scripts** | 31.4.0 | Build pipeline | Official WordPress tooling; Webpack + Babel preconfigured | HIGH |
| **wp-scripts build** | - | Production build | Minification, chunking, asset manifest generation | HIGH |
| **wp-scripts start** | - | Development | Hot reload, source maps | HIGH |

### Database

| Technology | Version | Purpose | Notes | Confidence |
|------------|---------|---------|-------|------------|
| **WordPress Custom Tables** | via dbDelta | Script storage | `{prefix}tsm_scripts`, `{prefix}tsm_script_versions` | HIGH |
| **$wpdb prepared statements** | - | Query safety | Always use `$wpdb->prepare()` for user input | HIGH |

---

## Monaco Editor Integration Strategy

### Recommended Approach: AMD Loader via CDN

**Why NOT Webpack for Monaco in WordPress:**
1. Monaco's ESM support is incomplete (CSS module issues)
2. WordPress already has its own build pipeline (@wordpress/scripts)
3. Webpack configuration complexity adds maintenance burden
4. Monaco files are large (~10MB unpacked); CDN caching is more efficient

**Implementation Pattern:**

```javascript
// Load Monaco from CDN using @monaco-editor/loader pattern
window.MonacoEnvironment = {
    getWorkerUrl: function(moduleId, label) {
        // Workers loaded from same CDN
        const base = 'https://cdn.jsdelivr.net/npm/monaco-editor@0.55.1/min/vs';
        if (label === 'json') return `data:text/javascript;charset=utf-8,${encodeURIComponent(`self.MonacoEnvironment = { baseUrl: '${base}' }; importScripts('${base}/language/json/json.worker.js');`)}`;
        if (label === 'css' || label === 'scss' || label === 'less') return `data:text/javascript;charset=utf-8,${encodeURIComponent(`self.MonacoEnvironment = { baseUrl: '${base}' }; importScripts('${base}/language/css/css.worker.js');`)}`;
        if (label === 'html' || label === 'handlebars' || label === 'razor') return `data:text/javascript;charset=utf-8,${encodeURIComponent(`self.MonacoEnvironment = { baseUrl: '${base}' }; importScripts('${base}/language/html/html.worker.js');`)}`;
        if (label === 'typescript' || label === 'javascript') return `data:text/javascript;charset=utf-8,${encodeURIComponent(`self.MonacoEnvironment = { baseUrl: '${base}' }; importScripts('${base}/language/typescript/ts.worker.js');`)}`;
        return `data:text/javascript;charset=utf-8,${encodeURIComponent(`self.MonacoEnvironment = { baseUrl: '${base}' }; importScripts('${base}/editor/editor.worker.js');`)}`;
    }
};

// Load Monaco AMD loader
require.config({ paths: { 'vs': 'https://cdn.jsdelivr.net/npm/monaco-editor@0.55.1/min/vs' }});
require(['vs/editor/editor.main'], function() {
    monaco.editor.create(document.getElementById('editor'), {
        value: '<?php\n// Your code here',
        language: 'php',
        theme: 'vs-dark',
        automaticLayout: true
    });
});
```

### Language Support Configuration

| Language | Worker | Use Case |
|----------|--------|----------|
| PHP | editor.worker (default) | WordPress hooks, plugin code |
| JavaScript | ts.worker | Frontend scripts, AJAX |
| CSS/SCSS | css.worker | Theme styling |
| SQL | editor.worker (default) | Database queries |
| JSON | json.worker | Configuration, REST responses |
| HTML | html.worker | Template output |

---

## Background Execution: Action Scheduler

### Why Action Scheduler (Not WP-Cron)

| Feature | WP-Cron | Action Scheduler |
|---------|---------|------------------|
| Trigger mechanism | Page visits only | Background queue processor |
| Failure handling | Silent fail | Retry with logging |
| Scalability | ~100 jobs/hour max | 10,000+ jobs/hour |
| Admin UI | None | Built-in dashboard |
| Concurrency | Single threaded | Batch processing |
| Dependency | None | Bundled via Composer |

### Implementation Pattern

```php
// Schedule a script execution
as_schedule_single_action(
    time(), // Run immediately
    'tsm_execute_script',
    [
        'script_id' => $script_id,
        'user_id' => get_current_user_id(),
    ],
    'test-script-manager'
);

// Hook handler
add_action('tsm_execute_script', function($script_id, $user_id) {
    // Execute with proper WordPress context
    wp_set_current_user($user_id);

    // ... execution logic
}, 10, 2);
```

### Bundling Action Scheduler

```json
{
    "require": {
        "woocommerce/action-scheduler": "^3.9"
    }
}
```

Action Scheduler uses a "first loaded wins" pattern - if WooCommerce or another plugin already loads it, that version is used.

---

## Database Schema

### Custom Tables

**Table: `{prefix}tsm_scripts`**

```sql
CREATE TABLE {prefix}tsm_scripts (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    name varchar(200) NOT NULL,
    slug varchar(200) NOT NULL,
    language varchar(20) NOT NULL DEFAULT 'php',
    content longtext NOT NULL,
    description text,
    author_id bigint(20) unsigned NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'active',
    execution_count int(11) DEFAULT 0,
    last_executed datetime DEFAULT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug),
    KEY author_id (author_id),
    KEY status (status),
    KEY language (language)
) {charset_collate};
```

**Table: `{prefix}tsm_script_versions`**

```sql
CREATE TABLE {prefix}tsm_script_versions (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    script_id bigint(20) unsigned NOT NULL,
    version_number int(11) NOT NULL,
    content longtext NOT NULL,
    commit_message varchar(255) DEFAULT NULL,
    author_id bigint(20) unsigned NOT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY script_id (script_id),
    KEY version_number (version_number),
    FOREIGN KEY (script_id) REFERENCES {prefix}tsm_scripts(id) ON DELETE CASCADE
) {charset_collate};
```

**Table: `{prefix}tsm_execution_logs`**

```sql
CREATE TABLE {prefix}tsm_execution_logs (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    script_id bigint(20) unsigned NOT NULL,
    user_id bigint(20) unsigned NOT NULL,
    execution_type varchar(20) NOT NULL DEFAULT 'immediate',
    status varchar(20) NOT NULL,
    output longtext,
    error_output longtext,
    execution_time float DEFAULT NULL,
    memory_peak bigint(20) DEFAULT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY script_id (script_id),
    KEY user_id (user_id),
    KEY status (status),
    KEY created_at (created_at)
) {charset_collate};
```

### Version History Pattern

Use incremental version numbers (1, 2, 3...) rather than Git-like SHA hashes for simplicity. Store full content each version (not diffs) - storage is cheap, diff reconstruction is complex.

---

## REST API Design

### Endpoint Structure

| Endpoint | Method | Purpose | Capability |
|----------|--------|---------|------------|
| `/tsm/v1/scripts` | GET | List scripts | `manage_options` |
| `/tsm/v1/scripts` | POST | Create script | `manage_options` |
| `/tsm/v1/scripts/{id}` | GET | Get script | `manage_options` |
| `/tsm/v1/scripts/{id}` | PUT | Update script | `manage_options` |
| `/tsm/v1/scripts/{id}` | DELETE | Delete script | `manage_options` |
| `/tsm/v1/scripts/{id}/execute` | POST | Execute script | `manage_options` |
| `/tsm/v1/scripts/{id}/versions` | GET | Get versions | `manage_options` |
| `/tsm/v1/scripts/{id}/revert/{version}` | POST | Revert to version | `manage_options` |
| `/tsm/v1/executions` | GET | List executions | `manage_options` |
| `/tsm/v1/executions/{id}` | GET | Get execution | `manage_options` |

### Authentication Pattern

```php
register_rest_route('tsm/v1', '/scripts', [
    'methods' => 'GET',
    'callback' => [$this, 'get_scripts'],
    'permission_callback' => function() {
        return current_user_can('manage_options');
    }
]);

// Frontend JS
fetch(wpApiSettings.root + 'tsm/v1/scripts', {
    headers: {
        'X-WP-Nonce': wpApiSettings.nonce
    }
});
```

**Critical:** Always use `permission_callback` with capability checks. Nonce alone is not sufficient - any logged-in user has access to `wp_rest` nonce.

---

## Security Layer for Code Execution

### Philosophy: Developer Tool, Not Production Code Runner

This is explicitly a **development environment tool**. Users with `manage_options` capability (administrators) already have full database access via phpMyAdmin, server access, and can install arbitrary plugins. The security model acknowledges this.

### Execution Architecture

```php
class ScriptExecutor {
    public function execute(string $code, string $language): ExecutionResult {
        // Pre-execution checks
        if (!current_user_can('manage_options')) {
            throw new UnauthorizedException();
        }

        // Set up execution environment
        $start_time = microtime(true);
        $start_memory = memory_get_usage();

        ob_start();

        try {
            switch ($language) {
                case 'php':
                    // WordPress is already loaded
                    eval($code);
                    break;
                case 'sql':
                    $result = $this->execute_sql($code);
                    print_r($result);
                    break;
                // ... other languages
            }
        } catch (Throwable $e) {
            return new ExecutionResult(
                success: false,
                output: ob_get_clean(),
                error: $e->getMessage(),
                trace: $e->getTraceAsString()
            );
        }

        return new ExecutionResult(
            success: true,
            output: ob_get_clean(),
            execution_time: microtime(true) - $start_time,
            memory_peak: memory_get_peak_usage() - $start_memory
        );
    }
}
```

### Dangerous Functions: Allow but Log

Since this is a development tool, allow dangerous functions but log their usage:

```php
// Functions to log (not block)
$monitored_functions = [
    'eval', 'exec', 'shell_exec', 'system', 'passthru',
    'file_put_contents', 'unlink', 'rmdir',
    'wp_delete_user', 'wp_delete_post',
    'drop table', 'truncate', 'delete from'
];
```

---

## Alternatives Considered

| Category | Recommended | Alternative | When to Use Alternative |
|----------|-------------|-------------|-------------------------|
| Code Editor | Monaco Editor | CodeMirror 6 | Lighter weight (but less VS Code-like) |
| Editor Loading | CDN AMD | Webpack Bundle | If already using complex Webpack config |
| Background Jobs | Action Scheduler | WP-Cron | Simple, low-volume tasks only |
| Database | Custom Tables | Post Meta | Don't - structured data needs proper tables |
| Build Tool | @wordpress/scripts | Vite | If not using any WordPress JS dependencies |
| Styling | Tailwind CSS | WordPress Admin CSS | Tighter WordPress integration, less flexibility |

---

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| **monaco-editor-webpack-plugin** | Adds complexity; WordPress doesn't need custom Webpack | CDN loader pattern |
| **WP-Cron for background jobs** | Unreliable timing, no retry, no UI | Action Scheduler |
| **eval() without output buffering** | Can corrupt HTTP response | Always wrap in ob_start/ob_get_clean |
| **Post meta for scripts** | Poor query performance, no versioning | Custom tables |
| **`__return_true` permission_callback** | Security vulnerability | Always check capabilities |
| **create_function()** | Deprecated in PHP 7.2 | Anonymous functions or eval |
| **preg_replace /e modifier** | Removed in PHP 7.0 | preg_replace_callback |
| **Storing scripts in database without versioning** | Users will lose work | Version every save |
| **wp_options for script content** | Autoload bloat, no indexing | Custom tables |

---

## Installation Commands

```bash
# Initialize project
npm init -y
composer init

# Core dependencies
npm install --save-dev @wordpress/scripts@31.4.0
composer require woocommerce/action-scheduler:^3.9

# Add package.json scripts
npm pkg set scripts.build="wp-scripts build"
npm pkg set scripts.start="wp-scripts start"

# Optional: Tailwind
npm install --save-dev tailwindcss postcss autoprefixer
npx tailwindcss init
```

### Recommended package.json

```json
{
  "name": "test-script-manager",
  "version": "1.0.0",
  "scripts": {
    "build": "wp-scripts build",
    "start": "wp-scripts start",
    "lint:js": "wp-scripts lint-js",
    "lint:css": "wp-scripts lint-style"
  },
  "devDependencies": {
    "@wordpress/scripts": "^31.4.0"
  }
}
```

### Recommended composer.json

```json
{
  "name": "your-vendor/test-script-manager",
  "description": "WordPress Test Script Manager",
  "require": {
    "php": ">=8.0",
    "woocommerce/action-scheduler": "^3.9"
  },
  "autoload": {
    "psr-4": {
      "TSM\\": "includes/"
    }
  }
}
```

---

## Version Compatibility Matrix

| Package | Compatible With | Notes |
|---------|-----------------|-------|
| Monaco Editor 0.55.x | All modern browsers | Chrome 80+, Firefox 75+, Edge 80+ |
| Action Scheduler 3.9.x | WordPress 6.4+, PHP 7.4+ | Tested up to WP 6.8 |
| @wordpress/scripts 31.x | WordPress 6.8+, Node 20+ | Use wp-6.4 tag for older WP |
| PHP 8.0+ | WordPress 6.3+ | Required for modern syntax |

---

## File Structure Recommendation

```
test-script-manager/
├── test-script-manager.php          # Main plugin file
├── package.json
├── composer.json
├── webpack.config.js                 # Optional: extend wp-scripts
├── includes/
│   ├── class-plugin.php             # Main plugin class
│   ├── class-database.php           # Table creation/migration
│   ├── Admin/
│   │   ├── class-admin-page.php     # Admin menu registration
│   │   └── class-settings.php       # Plugin settings
│   ├── API/
│   │   ├── class-scripts-api.php    # REST endpoints
│   │   └── class-executions-api.php
│   ├── Services/
│   │   ├── class-script-service.php # CRUD operations
│   │   ├── class-executor.php       # Code execution
│   │   ├── class-version-service.php # Version management
│   │   └── class-output-formatter.php # Result formatting
│   └── Models/
│       ├── class-script.php
│       └── class-execution-log.php
├── assets/
│   ├── src/
│   │   ├── admin.js                 # Entry point
│   │   ├── editor/
│   │   │   ├── index.js             # Monaco integration
│   │   │   └── languages/           # Language configs
│   │   └── components/
│   │       ├── ScriptList.js
│   │       ├── Editor.js
│   │       └── ExecutionOutput.js
│   └── build/                       # wp-scripts output
├── views/
│   └── admin-page.php               # Admin page template
└── vendor/                          # Composer dependencies
```

---

## Sources

### HIGH Confidence (Official/Verified)

- [npm: monaco-editor 0.55.1](https://www.npmjs.com/package/monaco-editor) - Version verified via npm CLI
- [npm: @wordpress/scripts 31.4.0](https://www.npmjs.com/package/@wordpress/scripts) - Version verified via npm CLI
- [GitHub: Action Scheduler 3.9.3](https://github.com/woocommerce/action-scheduler/releases) - Release date 2025-07-15
- [WordPress Developer: REST API Authentication](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/)
- [WordPress Developer: dbDelta()](https://developer.wordpress.org/reference/functions/dbdelta/)
- [WordPress Developer: @wordpress/scripts](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/)

### MEDIUM Confidence (Verified Community Sources)

- [Action Scheduler Official Site](https://actionscheduler.org/) - Performance benchmarks
- [Kinsta: wp-scripts development](https://kinsta.com/blog/wp-scripts-development/) - Build patterns
- [WPCodeBox](https://wpcodebox.com/) - Reference implementation for Monaco in WordPress

### LOW Confidence (Community Discussion)

- [GitHub: monaco-editor ESM discussions](https://github.com/microsoft/monaco-editor/discussions/3771) - CDN loading patterns
- [HackerNews: Ace Monaco Editor](https://news.ycombinator.com/item?id=46327218) - Community feedback

---

*Stack research for: WordPress Test Script Manager*
*Researched: 2026-01-30*
