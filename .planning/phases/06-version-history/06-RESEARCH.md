# Phase 6: Version History - Research

**Researched:** 2026-01-30
**Domain:** Version Control, Diff/Comparison, WordPress Revisions
**Confidence:** HIGH

## Summary

This research covers implementing version history for a WordPress test script manager plugin. The phase requires automatic version snapshots on save, version comparison with diff view, rollback capability, and automatic cleanup of old versions.

WordPress core provides excellent patterns through its post revision system, which stores revisions in the `wp_posts` table with `post_type='revision'` and uses `post_parent` to link back to the original. For our custom implementation, we'll use a dedicated `tsm_script_versions` table (already defined in Phase 1) to store script versions.

For diff functionality, **jfcherng/php-diff** is the recommended PHP library (actively maintained, multiple output formats including unified diff and HTML). For the frontend, **Monaco Editor's built-in Diff Editor** (`monaco.editor.createDiffEditor`) provides professional side-by-side comparison with syntax highlighting.

Version cleanup can follow WordPress's pattern using the `wp_revisions_to_keep` filter approach, implementing both time-based (30 days) and count-based (last 50 versions) retention policies.

**Primary recommendation:** Use jfcherng/php-diff for backend diff generation, Monaco Diff Editor for frontend visualization, implement WordPress-style revision hooks for automatic version creation, and scheduled cleanup via WP-Cron for retention management.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| jfcherng/php-diff | ^6.0 | PHP diff generation | Most actively maintained, multiple output formats (unified, HTML, JSON), based on Python difflib |
| Monaco Editor Diff | Core | Frontend diff visualization | Built-in to Monaco Editor, side-by-side view with syntax highlighting, professional UI |
| WordPress $wpdb | Core | Version storage and retrieval | Standard WordPress database API |
| WP-Cron | Core | Scheduled version cleanup | WordPress's built-in task scheduler |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| sebastianbergmann/diff | ^5.0 | Alternative PHP diff | If already using PHPUnit (includes this), strict unified diff format |
| WordPress Options API | Core | Store cleanup settings | Retention policy configuration |
| WordPress Transients | Core | Cache recent version lists | Performance optimization for version history UI |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| jfcherng/php-diff | sebastianbergmann/diff | sebastianbergmann focuses on strict unified diff (PHPUnit), jfcherng offers more renderers (HTML, side-by-side) |
| Monaco Diff Editor | CodeMirror Merge | Monaco has better TypeScript support and more modern API, built-in to existing editor |
| Custom diff algorithm | Native PHP | Reinventing the wheel; diff algorithms are complex (handle edge cases) |
| Store full copies | Store diffs only | Full copies are simpler to implement, easier to restore, minimal storage cost for scripts |

**Installation:**
```bash
# Backend diff library
composer require jfcherng/php-diff

# Frontend - Monaco Editor already included in Phase 3
# No additional installation needed
```

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── services/
│   ├── class-script-service.php       # Existing - add version hooks
│   ├── class-version-service.php      # NEW - version CRUD operations
│   └── class-cleanup-service.php      # NEW - version retention cleanup
├── api/
│   ├── class-scripts-api.php          # Existing - add version endpoints
│   └── class-versions-api.php         # NEW - version history API
└── cron/
    └── class-version-cleanup-cron.php # NEW - scheduled cleanup task

assets/js/
└── version-history.js                 # NEW - Monaco Diff Editor integration
```

### Pattern 1: WordPress Revision Pattern (Adapted)
**What:** Store versions in separate table linked by foreign key, create version on save
**When to use:** Any content versioning system
**Example:**
```php
// Source: WordPress wp_save_post_revision() pattern
class VersionService {
    /**
     * Create version snapshot before updating script.
     *
     * @param int    $script_id Script ID
     * @param string $code      Current code before update
     * @return int|false Version ID on success, false on failure
     */
    public static function create_version( $script_id, $code ) {
        global $wpdb;

        $table_name = Database::get_table_name( Database::TABLE_SCRIPT_VERSIONS );

        $result = $wpdb->insert(
            $table_name,
            array(
                'script_id'  => $script_id,
                'code'       => $code,
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s' )
        );

        if ( false === $result ) {
            return false;
        }

        return $wpdb->insert_id;
    }
}
```

### Pattern 2: Hook-Based Version Creation
**What:** Automatically create version before script update
**When to use:** Ensure versions are never missed
**Example:**
```php
// In ScriptService::update() method
// BEFORE database update
$existing = self::get( $id );
if ( null !== $existing ) {
    // Create version snapshot of current code
    VersionService::create_version( $id, $existing['code'] );
}

// THEN proceed with update
$wpdb->update( ... );
```

### Pattern 3: Monaco Diff Editor Integration
**What:** Side-by-side code comparison with syntax highlighting
**When to use:** Version comparison UI
**Example:**
```javascript
// Source: https://microsoft.github.io/monaco-editor/typedoc/functions/editor.createDiffEditor.html
const diffEditor = monaco.editor.createDiffEditor(
    document.getElementById('diff-container'),
    {
        enableSplitViewResizing: true,
        renderSideBySide: true,
        readOnly: true,
        originalEditable: false,
        ignoreTrimWhitespace: false,
    }
);

// Set models for comparison
diffEditor.setModel({
    original: monaco.editor.createModel(originalCode, 'php'),
    modified: monaco.editor.createModel(modifiedCode, 'php'),
});
```

### Pattern 4: Version Cleanup with Retention Policies
**What:** Keep last N versions OR versions from last X days (whichever is greater)
**When to use:** Prevent unlimited version growth
**Example:**
```php
// Source: WordPress wp_revisions_to_keep filter pattern + time-based retention
class CleanupService {
    /**
     * Clean up old versions for a script.
     * Keeps last 50 versions OR versions from last 30 days (whichever is more).
     *
     * @param int $script_id Script ID
     * @return int Number of versions deleted
     */
    public static function cleanup_versions( $script_id ) {
        global $wpdb;

        $table_name = Database::get_table_name( Database::TABLE_SCRIPT_VERSIONS );

        // Keep last 50 versions
        $keep_count = apply_filters( 'tsm_versions_to_keep', 50, $script_id );

        // Keep versions from last 30 days
        $keep_days = apply_filters( 'tsm_version_retention_days', 30, $script_id );
        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$keep_days} days" ) );

        // Delete versions that are:
        // - NOT in the last N versions
        // - AND older than retention period
        $sql = "DELETE FROM {$table_name}
                WHERE script_id = %d
                AND id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM {$table_name}
                        WHERE script_id = %d
                        ORDER BY created_at DESC
                        LIMIT %d
                    ) AS recent_versions
                )
                AND created_at < %s";

        $deleted = $wpdb->query(
            $wpdb->prepare( $sql, $script_id, $script_id, $keep_count, $cutoff_date )
        );

        return (int) $deleted;
    }
}
```

### Pattern 5: PHP Diff Generation for API
**What:** Generate diff output for REST API responses
**When to use:** Version comparison endpoint
**Example:**
```php
// Source: https://github.com/jfcherng/php-diff
use Jfcherng\Diff\DiffHelper;
use Jfcherng\Diff\Factory\RendererFactory;

class VersionsAPI {
    /**
     * Compare two versions.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function compare_versions( $request ) {
        $version1 = VersionService::get( $request['version1_id'] );
        $version2 = VersionService::get( $request['version2_id'] );

        // Generate unified diff
        $rendererName = 'Unified';
        $differOptions = [
            'context' => 3,
            'ignoreWhitespace' => false,
            'ignoreCase' => false,
        ];
        $rendererOptions = [
            'detailLevel' => 'word',
            'lineNumbers' => true,
        ];

        $diff = DiffHelper::calculate(
            $version1['code'],
            $version2['code'],
            $rendererName,
            $differOptions,
            $rendererOptions
        );

        return rest_ensure_response([
            'version1' => $version1,
            'version2' => $version2,
            'diff' => $diff,
        ]);
    }
}
```

### Anti-Patterns to Avoid

- **Storing diffs instead of full code**: Complex to restore, error-prone. Store full code snapshots.
- **No version limit**: Database bloat. Always implement cleanup.
- **Synchronous cleanup on save**: Slow user experience. Use WP-Cron scheduled task.
- **Missing user_id tracking**: Can't audit who made changes. Add `created_by` field.
- **No transaction safety**: Version creation should be atomic with script update. Use $wpdb transactions if needed.

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Diff algorithm | Custom line-by-line comparison | jfcherng/php-diff | Handles edge cases (similar lines, whitespace, empty files), optimized LCS algorithm |
| Diff visualization | Custom HTML with color coding | Monaco Diff Editor | Professional UI, handles long files, syntax highlighting, collapsible sections |
| Version cleanup | Manual DELETE queries | WordPress-style retention filter | Flexible (allow per-script policies), tested pattern, hookable |
| Date calculations | Manual strtotime logic | WordPress current_time() | Handles timezone correctly, respects WP settings |
| Scheduled tasks | Custom cron job | WP-Cron | WordPress-native, survives plugin updates, manageable via WP |

**Key insight:** Diff algorithms are deceptively complex. Edge cases include: empty files, whitespace-only changes, similar but not identical lines, very long lines, binary content. Established libraries handle these correctly.

## Common Pitfalls

### Pitfall 1: Creating Version After Update
**What goes wrong:** Version created after update saves the NEW code, not the OLD code
**Why it happens:** Logical mistake in hook placement
**How to avoid:**
```php
// ❌ WRONG - This saves the new code
$wpdb->update( $table_name, ['code' => $new_code], ['id' => $id] );
VersionService::create_version( $id, $new_code ); // Too late!

// ✅ CORRECT - Save old code before update
$existing = self::get( $id );
VersionService::create_version( $id, $existing['code'] ); // Save old
$wpdb->update( $table_name, ['code' => $new_code], ['id' => $id] );
```
**Warning signs:** All versions show the same code, "restore" does nothing

### Pitfall 2: No Cleanup = Database Bloat
**What goes wrong:** Database grows unbounded, slow queries, storage issues
**Why it happens:** Forgetting to implement or schedule cleanup
**How to avoid:**
- Implement cleanup service in Phase 6
- Schedule WP-Cron daily task
- Add filter hooks for customizable retention
- Test cleanup with large version counts (100+ versions)
**Warning signs:** Database size growing rapidly, slow version list queries

### Pitfall 3: Missing created_by Tracking
**What goes wrong:** Can't audit who created each version
**Why it happens:** Database schema omits user_id field
**How to avoid:**
- Add `created_by` column to `tsm_script_versions` table in Phase 1
- Use `get_current_user_id()` when creating versions
- Display username in version history UI
**Warning signs:** Version list shows timestamps but no user attribution

### Pitfall 4: Inline Diff Mode Unusable for Large Changes
**What goes wrong:** Inline diff (Monaco) is hard to read when many lines changed
**Why it happens:** Inline mode shows changes in single column
**How to avoid:**
- Default to side-by-side mode (`renderSideBySide: true`)
- Provide toggle button for user preference
- Save preference in localStorage
**Warning signs:** User complaints about readability, requests for "old vs new" view

### Pitfall 5: Comparing Wrong Versions
**What goes wrong:** User wants to compare version A with B, but API compares with current
**Why it happens:** API endpoint design assumes comparison with current version
**How to avoid:**
- Support two version IDs: `/scripts/{id}/compare/{v1}/{v2}`
- Also support comparison with current: `/scripts/{id}/compare/{v1}/current`
- Clear UI labels: "Version 1" vs "Version 2"
**Warning signs:** Confusion in issue reports, "wrong version shown"

## Code Examples

Verified patterns from official sources:

### Version Creation with User Tracking
```php
// Source: WordPress wp_save_post_revision() + custom user tracking
class VersionService {
    public static function create_version( $script_id, $code ) {
        global $wpdb;

        $table_name = Database::get_table_name( Database::TABLE_SCRIPT_VERSIONS );

        $result = $wpdb->insert(
            $table_name,
            array(
                'script_id'  => $script_id,
                'code'       => $code,
                'created_at' => current_time( 'mysql' ),
                'created_by' => get_current_user_id(),
            ),
            array( '%d', '%s', '%s', '%d' )
        );

        return false !== $result ? $wpdb->insert_id : false;
    }

    public static function get_versions( $script_id, $limit = 50 ) {
        global $wpdb;

        $table_name = Database::get_table_name( Database::TABLE_SCRIPT_VERSIONS );

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT v.*, u.display_name as author_name
                 FROM {$table_name} v
                 LEFT JOIN {$wpdb->users} u ON v.created_by = u.ID
                 WHERE v.script_id = %d
                 ORDER BY v.created_at DESC
                 LIMIT %d",
                $script_id,
                $limit
            ),
            ARRAY_A
        );

        return $results ?: array();
    }
}
```

### Version Restoration
```php
// Source: Custom implementation following WordPress pattern
class VersionService {
    /**
     * Restore script to a specific version.
     * Creates a version snapshot of current code before restoring.
     *
     * @param int $script_id  Script ID
     * @param int $version_id Version to restore
     * @return true|WP_Error
     */
    public static function restore_version( $script_id, $version_id ) {
        global $wpdb;

        // Get version to restore
        $version = self::get( $version_id );
        if ( null === $version || (int) $version['script_id'] !== (int) $script_id ) {
            return new \WP_Error(
                'tsm_version_not_found',
                __( 'Version not found or does not belong to this script.', 'test-script-manager' )
            );
        }

        // Get current script (to create snapshot before restore)
        $script = ScriptService::get( $script_id );
        if ( null === $script ) {
            return new \WP_Error(
                'tsm_script_not_found',
                __( 'Script not found.', 'test-script-manager' )
            );
        }

        // Create version of current code (before restore)
        self::create_version( $script_id, $script['code'] );

        // Restore to version code
        $result = ScriptService::update(
            $script_id,
            array( 'code' => $version['code'] )
        );

        return $result;
    }
}
```

### WP-Cron Cleanup Task
```php
// Source: WordPress cron pattern
class VersionCleanupCron {
    /**
     * Register cron event.
     */
    public static function register() {
        if ( ! wp_next_scheduled( 'tsm_cleanup_versions' ) ) {
            wp_schedule_event( time(), 'daily', 'tsm_cleanup_versions' );
        }

        add_action( 'tsm_cleanup_versions', array( __CLASS__, 'run_cleanup' ) );
    }

    /**
     * Run cleanup for all scripts.
     */
    public static function run_cleanup() {
        $scripts = ScriptService::list_all( 999999 );
        $total_deleted = 0;

        foreach ( $scripts as $script ) {
            $deleted = CleanupService::cleanup_versions( $script['id'] );
            $total_deleted += $deleted;
        }

        // Log cleanup
        error_log( sprintf(
            'TSM Version Cleanup: Deleted %d old versions',
            $total_deleted
        ) );
    }

    /**
     * Unregister cron event.
     */
    public static function unregister() {
        $timestamp = wp_next_scheduled( 'tsm_cleanup_versions' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'tsm_cleanup_versions' );
        }
    }
}
```

### Monaco Diff Editor with Toggle
```javascript
// Source: https://microsoft.github.io/monaco-editor/ + custom toggle
class VersionHistoryUI {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.diffEditor = null;
        this.sideBySideMode = true;
    }

    initDiffEditor() {
        this.diffEditor = monaco.editor.createDiffEditor(this.container, {
            enableSplitViewResizing: true,
            renderSideBySide: this.sideBySideMode,
            readOnly: true,
            originalEditable: false,
            ignoreTrimWhitespace: false,
            automaticLayout: true,
        });
    }

    compareVersions(version1Code, version2Code, language = 'php') {
        if (!this.diffEditor) {
            this.initDiffEditor();
        }

        this.diffEditor.setModel({
            original: monaco.editor.createModel(version1Code, language),
            modified: monaco.editor.createModel(version2Code, language),
        });
    }

    toggleViewMode() {
        this.sideBySideMode = !this.sideBySideMode;

        if (this.diffEditor) {
            this.diffEditor.updateOptions({
                renderSideBySide: this.sideBySideMode
            });
        }

        // Save preference
        localStorage.setItem('tsm_diff_mode', this.sideBySideMode ? 'side-by-side' : 'inline');
    }

    loadViewPreference() {
        const saved = localStorage.getItem('tsm_diff_mode');
        if (saved) {
            this.sideBySideMode = saved === 'side-by-side';
        }
    }
}
```

### REST API Version Endpoints
```php
// Source: WordPress REST API patterns
class VersionsAPI extends \WP_REST_Controller {
    public function register_routes() {
        // List versions for a script
        register_rest_route( 'test-script-manager/v1', '/scripts/(?P<id>\d+)/versions', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_versions' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args'                => array(
                'id' => array(
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param );
                    },
                ),
            ),
        ) );

        // Compare two versions
        register_rest_route( 'test-script-manager/v1', '/scripts/(?P<id>\d+)/versions/compare', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array( $this, 'compare_versions' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args'                => array(
                'version1' => array(
                    'required' => true,
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param ) || $param === 'current';
                    },
                ),
                'version2' => array(
                    'required' => true,
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param ) || $param === 'current';
                    },
                ),
            ),
        ) );

        // Restore to version
        register_rest_route( 'test-script-manager/v1', '/scripts/(?P<id>\d+)/versions/(?P<version_id>\d+)/restore', array(
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'restore_version' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ) );
    }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Store diffs between versions | Store full code snapshots | ~2015 (GitHub-style) | Simpler restore, easier to implement, storage is cheap |
| Manual version creation | Auto-save before update | WordPress 2.6+ | Never miss a version, better UX |
| Keep all versions forever | Retention policies (count + time) | WordPress 3.6+ | Prevents DB bloat, configurable |
| Text-only diff | Syntax-highlighted diff | Monaco Editor 2018+ | Better readability, professional UI |
| Inline diff only | Side-by-side + inline toggle | Modern diff tools | Better for large changes |

**Deprecated/outdated:**
- **chrisboulton/php-diff**: Original library, no longer maintained. Use **jfcherng/php-diff** (active fork)
- **Storing only last N versions**: Should combine with time-based retention (keep 50 OR 30 days)
- **No user attribution**: Modern systems track who created each version

## Open Questions

Things that couldn't be fully resolved:

1. **Should created_by field be added to tsm_script_versions schema?**
   - What we know: Database schema defined in Phase 1, currently has: id, script_id, code, created_at
   - What's unclear: Was `created_by` field included in original schema?
   - Recommendation: Check Database::create_script_versions_table() - if missing, add migration in Phase 6

2. **What's the optimal version retention policy?**
   - What we know: WordPress defaults to unlimited, allows filters for customization
   - What's unclear: For test scripts, what's reasonable (50 versions? 30 days?)
   - Recommendation: Start with 50 versions AND 30 days (whichever keeps more), make filterable

3. **Should cleanup run immediately on save or via scheduled task?**
   - What we know: Immediate = guaranteed cleanup, scheduled = better performance
   - What's unclear: How many versions are expected (high churn or low?)
   - Recommendation: Use scheduled daily cleanup (WordPress pattern), more scalable

4. **Should version comparison be available for non-adjacent versions?**
   - What we know: UI could allow "compare any two versions"
   - What's unclear: Is this needed for test scripts? (vs just "compare with previous")
   - Recommendation: Support both - quick "compare with previous" button, advanced "select two" mode

## Sources

### Primary (HIGH confidence)
- [WordPress Revisions Documentation](https://wordpress.org/documentation/article/revisions/) - How WP implements revisions
- [WordPress wp_posts Revisions Schema](https://publishpress.com/blog/revisions/revisions-in-the-wordpress-database/) - Database structure
- [jfcherng/php-diff GitHub](https://github.com/jfcherng/php-diff) - PHP diff library documentation
- [Monaco Diff Editor API](https://microsoft.github.io/monaco-editor/typedoc/functions/editor.createDiffEditor.html) - Official API docs
- [WordPress wp_revisions_to_keep Filter](https://developer.wordpress.org/reference/hooks/wp_revisions_to_keep/) - Retention policy pattern

### Secondary (MEDIUM confidence)
- [WP_POST_REVISIONS Constant Usage](https://jetpack.com/resources/wordpress-revisions/) - Limiting revisions
- [PHP Diff Library Comparison](https://packagist.org/packages/jfcherng/php-diff) - Library selection
- [Monaco Editor Examples](https://codepen.io/akshitsarin/pen/VwexpQL) - Diff editor implementation

### Tertiary (LOW confidence)
- [WordPress Version Control Plugins](https://duplicator.com/wordpress-version-control-plugin/) - General patterns (not specific implementation)
- [SQL Temporal Tables Cleanup](https://learn.microsoft.com/en-us/sql/relational-databases/tables/manage-retention-of-historical-data-in-system-versioned-temporal-tables) - Generic cleanup strategies (adapted for WP)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - jfcherng/php-diff verified via GitHub/Packagist, Monaco Diff Editor official API
- Architecture: HIGH - WordPress revision pattern documented, tested in core for 15+ years
- Pitfalls: MEDIUM - Based on common issues in version control systems, not TSM-specific yet
- Cleanup strategy: MEDIUM - Combining WordPress pattern with SQL best practices

**Research date:** 2026-01-30
**Valid until:** ~60 days (stable technologies, WordPress patterns change slowly)

**Key technical decisions:**
1. **Full snapshots over diffs**: Simpler, more reliable
2. **jfcherng/php-diff over alternatives**: Active maintenance, feature-rich
3. **Monaco Diff Editor**: Already integrated, professional UI
4. **Dual retention policy**: Count-based AND time-based (whichever keeps more)
5. **Scheduled cleanup**: WP-Cron daily task, better performance

**Implementation priority:**
1. VersionService (CRUD for versions) - CRITICAL
2. Hook into ScriptService::update() - CRITICAL
3. REST API version endpoints - HIGH
4. Monaco Diff UI integration - HIGH
5. Cleanup service + WP-Cron - MEDIUM (can defer to Phase 7)
