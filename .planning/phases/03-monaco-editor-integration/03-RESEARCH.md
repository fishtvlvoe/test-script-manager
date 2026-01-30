# Phase 3: Monaco Editor Integration - Research

**Researched:** 2026-01-30
**Domain:** Monaco Editor (Microsoft VS Code browser editor) integration in WordPress
**Confidence:** MEDIUM

## Summary

Monaco Editor is Microsoft's browser-based code editor (the same editor used in VS Code) that provides professional-grade code editing with syntax highlighting, IntelliSense, and keyboard shortcuts. Version 0.55.1 (released November 2025) is the latest stable version.

**Key findings:**
- **AMD loader is deprecated** - Modern integration should use ESM (ES modules) or CDN with global variable pattern
- Monaco has built-in PHP syntax highlighting but **NO built-in WordPress function autocomplete** - must be custom-implemented
- SQL support exists but is basic - enhanced support requires third-party libraries (monaco-sql-languages)
- WordPress plugins successfully integrate Monaco using `admin_enqueue_scripts` hook and CDN loading
- Auto-save pattern: listen to `onDidChangeModelContent` event with debounce (typically 2-4 seconds)
- Theme switching is straightforward using `monaco.editor.setTheme()` API

**Primary recommendation:** Load Monaco Editor via CDN (jsDelivr) using global variable pattern (not AMD), register custom WordPress autocomplete provider for PHP language, implement debounced auto-save on content change events.

## Standard Stack

The established libraries/tools for Monaco Editor integration:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| monaco-editor | 0.55.1 | Code editor engine | Official Microsoft editor, powers VS Code |
| jsDelivr CDN | latest | Asset delivery | Fast, reliable CDN with Monaco support |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| monaco-sql-languages | latest | Enhanced SQL support | If SQL autocomplete needed (DTStack) |
| @monaco-editor/loader | 3.x | Monaco initialization helper | If using npm/webpack (not CDN) |
| Shiki | latest | Advanced syntax highlighting | If custom language grammars needed |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| CDN global | ESM/webpack | ESM requires build process, better for SPAs |
| Custom autocomplete | WordPress Hooks Intellisense | Intellisense is VS Code extension, not browser-ready |
| monaco-sql-languages | Built-in SQL | Built-in is basic, DTStack adds MySQL/PostgreSQL dialects |

**Installation:**
```bash
# If using npm (not recommended for WordPress plugin)
npm install monaco-editor

# Recommended: Use CDN in WordPress
# No installation needed - load from jsDelivr
```

## Architecture Patterns

### Recommended Project Structure
```
assets/
├── js/
│   ├── monaco-loader.js      # Monaco initialization and configuration
│   ├── monaco-config.js      # Language providers, autocomplete definitions
│   └── admin-editor.js       # WordPress editor integration
└── css/
    └── monaco-overrides.css  # Custom Monaco styling if needed

includes/
├── admin/
│   └── class-monaco-assets.php  # Enqueue Monaco scripts/styles
```

### Pattern 1: CDN Loading with Global Variable
**What:** Load Monaco via CDN using loader.js, configure paths, then initialize editor
**When to use:** WordPress plugins (recommended pattern, avoids AMD conflicts)
**Example:**
```javascript
// Source: https://www.javaspring.net/blog/how-to-initialize-microsoft-monaco-editor-in-a-browser-using-simple-javascript-or-jquery/
// Load Monaco loader from CDN
<script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.55.1/min/vs/loader.js"></script>

// Configure Monaco paths
require.config({
  paths: {
    'vs': 'https://cdn.jsdelivr.net/npm/monaco-editor@0.55.1/min/vs'
  }
});

// Initialize editor
require(['vs/editor/editor.main'], function() {
  var editor = monaco.editor.create(document.getElementById('container'), {
    value: '<?php\n// Your code here',
    language: 'php',
    theme: 'vs-dark',
    automaticLayout: true
  });
});
```

### Pattern 2: WordPress Script Enqueuing
**What:** Use WordPress hooks to conditionally load Monaco on admin pages
**When to use:** All WordPress plugin Monaco integrations
**Example:**
```php
// Source: WordPress Codex - admin_enqueue_scripts
public function enqueue_monaco_assets($hook) {
    // Only load on our plugin page
    if ($hook !== 'toplevel_page_test-script-manager') {
        return;
    }

    // Monaco loader
    wp_enqueue_script(
        'monaco-loader',
        'https://cdn.jsdelivr.net/npm/monaco-editor@0.55.1/min/vs/loader.js',
        array(),
        '0.55.1',
        true
    );

    // Our Monaco configuration
    wp_enqueue_script(
        'tsm-monaco-config',
        TSM_PLUGIN_URL . 'assets/js/monaco-config.js',
        array('monaco-loader'),
        TSM_VERSION,
        true
    );

    // Pass WordPress data to JavaScript
    wp_localize_script('tsm-monaco-config', 'tsmMonaco', array(
        'wpFunctions' => $this->get_wordpress_functions(),
        'wpHooks' => $this->get_wordpress_hooks(),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('tsm_autosave')
    ));
}

add_action('admin_enqueue_scripts', array($this, 'enqueue_monaco_assets'));
```

### Pattern 3: Custom Autocomplete Provider
**What:** Register completion provider for WordPress functions in PHP
**When to use:** To provide WordPress-specific IntelliSense
**Example:**
```javascript
// Source: https://app.studyraid.com/en/read/15534/540336/setting-up-monaco-code-completion-providers
monaco.languages.registerCompletionItemProvider('php', {
  provideCompletionItems: function(model, position) {
    var word = model.getWordUntilPosition(position);
    var range = {
      startLineNumber: position.lineNumber,
      endLineNumber: position.lineNumber,
      startColumn: word.startColumn,
      endColumn: word.endColumn
    };

    // WordPress functions from wp_localize_script
    var suggestions = tsmMonaco.wpFunctions.map(function(func) {
      return {
        label: func.name,
        kind: monaco.languages.CompletionItemKind.Function,
        documentation: func.description,
        insertText: func.name + '(${1})',
        insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
        range: range
      };
    });

    return { suggestions: suggestions };
  }
});
```

### Pattern 4: Debounced Auto-Save
**What:** Listen to content changes, debounce, then save via AJAX
**When to use:** All editor implementations with auto-save
**Example:**
```javascript
// Source: https://blog.expo.dev/building-a-code-editor-with-monaco-f84b3a06deaf
var autoSaveTimeout = null;
var AUTOSAVE_DELAY = 3000; // 3 seconds

editor.onDidChangeModelContent(function(e) {
  // Clear pending save
  if (autoSaveTimeout) {
    clearTimeout(autoSaveTimeout);
  }

  // Schedule new save
  autoSaveTimeout = setTimeout(function() {
    var content = editor.getValue();

    jQuery.ajax({
      url: tsmMonaco.ajaxUrl,
      method: 'POST',
      data: {
        action: 'tsm_autosave_script',
        nonce: tsmMonaco.nonce,
        script_id: tsmMonaco.currentScriptId,
        content: content
      },
      success: function(response) {
        console.log('Auto-saved at ' + new Date().toLocaleTimeString());
      }
    });
  }, AUTOSAVE_DELAY);
});
```

### Pattern 5: Keyboard Shortcuts
**What:** Add custom commands for save and execute
**When to use:** Improve UX with familiar keyboard shortcuts
**Example:**
```javascript
// Source: https://ahmadrosid.com/blog/monaco-editor-action-command
// Ctrl+S or Cmd+S to save
editor.addAction({
  id: 'tsm-save-script',
  label: 'Save Script',
  keybindings: [monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS],
  contextMenuGroupId: 'navigation',
  contextMenuOrder: 1.5,
  run: function(ed) {
    // Trigger save immediately (bypass debounce)
    saveScript(ed.getValue());
  }
});

// Ctrl+Enter to execute
editor.addAction({
  id: 'tsm-execute-script',
  label: 'Execute Script',
  keybindings: [monaco.KeyMod.CtrlCmd | monaco.KeyCode.Enter],
  contextMenuGroupId: 'navigation',
  contextMenuOrder: 1.6,
  run: function(ed) {
    executeScript(ed.getValue());
  }
});
```

### Pattern 6: Theme Switching
**What:** Switch between light and dark themes dynamically
**When to use:** User preference or match WordPress admin color scheme
**Example:**
```javascript
// Source: https://app.studyraid.com/en/read/15534/540318/dynamically-switching-themes-during-runtime
function setEditorTheme(theme) {
  // Built-in themes: 'vs' (light), 'vs-dark' (dark), 'hc-black' (high contrast)
  monaco.editor.setTheme(theme);
  localStorage.setItem('tsm-editor-theme', theme);
}

// Initialize with saved preference
var savedTheme = localStorage.getItem('tsm-editor-theme') || 'vs-dark';

var editor = monaco.editor.create(document.getElementById('container'), {
  value: code,
  language: 'php',
  theme: savedTheme,
  automaticLayout: true
});

// Theme toggle button
document.getElementById('theme-toggle').addEventListener('click', function() {
  var currentTheme = localStorage.getItem('tsm-editor-theme') || 'vs-dark';
  var newTheme = currentTheme === 'vs-dark' ? 'vs' : 'vs-dark';
  setEditorTheme(newTheme);
});
```

### Anti-Patterns to Avoid

- **Using AMD with WordPress:** AMD is deprecated and conflicts with RequireJS used by other plugins. Use CDN with global `monaco` variable instead.
- **Loading Monaco everywhere:** Only enqueue on pages that need it. Check `$hook` parameter in `admin_enqueue_scripts`.
- **Saving on every keystroke:** Use debounce (2-4 seconds) to avoid overwhelming server with AJAX requests.
- **Hard-coding CDN version:** Use WordPress version constant to enable cache busting during updates.
- **Forgetting automaticLayout:** Set `automaticLayout: true` or manually call `editor.layout()` when container resizes.

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| SQL syntax highlighting | Custom SQL parser | monaco-sql-languages (DTStack) | Supports MySQL, PostgreSQL, Spark, Hive dialects with autocomplete |
| WordPress function list | Manual scraping | WordPress Hooks Intellisense data | Automattic maintains official function definitions (needs conversion to JSON) |
| Module loading | Custom AMD setup | CDN + global variable | AMD is deprecated, global pattern avoids RequireJS conflicts |
| Debounce function | Custom setTimeout logic | Lodash debounce (if available) or simple setTimeout pattern | Edge cases like rapid saves, cancellation, flush on unload |
| Theme persistence | Custom cookie system | localStorage | Built-in browser API, no server round-trip needed |

**Key insight:** Monaco Editor has a steep learning curve for custom language features. The built-in PHP support is sufficient for syntax highlighting, but WordPress autocomplete requires custom implementation. Reuse existing WordPress function lists (from Automattic's VS Code extension data) rather than building from scratch.

## Common Pitfalls

### Pitfall 1: AMD Loader Conflicts with WordPress Plugins
**What goes wrong:** Monaco's `loader.js` uses AMD (RequireJS), which conflicts if other WordPress plugins also use RequireJS, causing "Can only have one anonymous define call per script file" errors.
**Why it happens:** WordPress admin area may have multiple AMD loaders from different plugins, Monaco expects to control `require` global.
**How to avoid:**
- Use CDN loading with Monaco's own loader isolated: `require.config({ paths: { 'vs': 'CDN_PATH' } })`
- Avoid enqueueing RequireJS separately for Monaco
- Test with popular plugins (WooCommerce, Yoast SEO) that use AMD
**Warning signs:** JavaScript console errors about "define" or "require" being undefined, Monaco editor container stays blank.

### Pitfall 2: PHP Code Without `<?php` Tag Loses Highlighting
**What goes wrong:** Monaco's PHP language mode expects opening `<?php` tag. Code without it shows as plain text.
**Why it happens:** Monaco uses official PHP grammar which requires proper PHP file structure.
**How to avoid:**
- Always include `<?php` in editor default value
- When creating new scripts, insert `<?php\n// Code here\n` as template
- Don't allow users to delete the opening tag
**Warning signs:** PHP code shows without color syntax, no autocomplete works.

### Pitfall 3: Editor Height Issues in WordPress Admin
**What goes wrong:** Monaco editor renders with 0px height or takes full viewport (100vh), breaking layout.
**Why it happens:** WordPress 6.7+ changed default editor iframe height behavior from `100vh` to `fit-content`, Monaco requires explicit container dimensions.
**How to avoid:**
- Set explicit height on container: `<div id="editor" style="height: 600px;"></div>`
- Use CSS flexbox with `flex: 1` to fill parent container
- Set `automaticLayout: true` to handle responsive resizing
**Warning signs:** Editor container shows but is invisible, or editor pushes page footer off-screen.

### Pitfall 4: Auto-Save Fires on Programmatic Changes
**What goes wrong:** When loading a script, `editor.setValue()` triggers `onDidChangeModelContent`, causing unwanted AJAX save request immediately.
**Why it happens:** Monaco's change event fires for all content modifications, including programmatic ones.
**How to avoid:**
- Use `isFlush` property: `if (e.isFlush) return;` to skip `setValue()` changes
- Set a flag before `setValue()`: `isLoading = true`, check in change handler
- Don't start auto-save timer until user makes first edit
**Warning signs:** Network tab shows save AJAX request immediately when opening script, "last modified" timestamp updates without user action.

### Pitfall 5: WordPress Function Autocomplete Data Bloat
**What goes wrong:** Passing all 2000+ WordPress functions via `wp_localize_script` makes HTML page size huge (500KB+), slow page load.
**Why it happens:** `wp_localize_script` inlines JavaScript data as JSON in HTML, core functions list is large.
**How to avoid:**
- Load autocomplete data via separate AJAX request: `jQuery.getJSON(ajaxUrl + '?action=tsm_get_wp_functions')`
- Cache in localStorage with version key: `localStorage.setItem('tsm-wp-funcs-6.7', JSON.stringify(data))`
- Lazy load only when user starts typing WordPress function names
**Warning signs:** View Source shows massive inline `<script>` with thousands of function definitions, PageSpeed Insights flags large DOM size.

### Pitfall 6: Theme Doesn't Apply on First Load
**What goes wrong:** Editor loads with default light theme despite setting `theme: 'vs-dark'` in config.
**Why it happens:** Theme is set before Monaco fully initializes, timing issue with CDN loading.
**How to avoid:**
- Set theme in editor creation options: `monaco.editor.create(el, { theme: 'vs-dark' })`
- OR use `editor.updateOptions({ theme: 'vs-dark' })` after creation
- Don't rely on `monaco.editor.setTheme()` before editor exists
**Warning signs:** Flash of light theme, then switches to dark after 1-2 seconds.

## Code Examples

Verified patterns from official sources:

### Basic Monaco Editor Setup in WordPress
```php
// Source: WordPress Codex + Monaco Editor official docs
// File: includes/admin/class-monaco-assets.php

class TSM_Monaco_Assets {
    private $version = '0.55.1';

    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue'));
    }

    public function enqueue($hook) {
        if ($hook !== 'toplevel_page_test-script-manager') {
            return;
        }

        // Monaco loader
        wp_enqueue_script(
            'monaco-loader',
            "https://cdn.jsdelivr.net/npm/monaco-editor@{$this->version}/min/vs/loader.js",
            array(),
            $this->version,
            true
        );

        // Monaco initialization
        wp_enqueue_script(
            'tsm-monaco-init',
            TSM_PLUGIN_URL . 'assets/js/monaco-init.js',
            array('monaco-loader', 'jquery'),
            TSM_VERSION,
            true
        );

        // Pass data to JavaScript
        wp_localize_script('tsm-monaco-init', 'tsmMonaco', array(
            'cdnPath' => "https://cdn.jsdelivr.net/npm/monaco-editor@{$this->version}/min/vs",
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tsm_editor'),
            'theme' => get_user_meta(get_current_user_id(), 'tsm_editor_theme', true) ?: 'vs-dark'
        ));
    }
}
```

### Monaco Initialization with WordPress Data
```javascript
// Source: Monaco Editor docs + WordPress AJAX patterns
// File: assets/js/monaco-init.js

(function($) {
    'use strict';

    var editor = null;
    var autoSaveTimeout = null;
    var isLoading = false;

    // Configure Monaco CDN path
    require.config({
        paths: {
            'vs': tsmMonaco.cdnPath
        }
    });

    // Load Monaco and initialize
    require(['vs/editor/editor.main'], function() {
        initializeEditor();
        loadWordPressAutocomplete();
    });

    function initializeEditor() {
        var container = document.getElementById('monaco-editor');
        if (!container) return;

        editor = monaco.editor.create(container, {
            value: '<?php\n\n// Your test script here\n',
            language: 'php',
            theme: tsmMonaco.theme,
            automaticLayout: true,
            minimap: { enabled: false },
            fontSize: 14,
            lineNumbers: 'on',
            roundedSelection: false,
            scrollBeyondLastLine: false,
            readOnly: false,
            cursorStyle: 'line',
            wordWrap: 'on'
        });

        // Auto-save on content change
        editor.onDidChangeModelContent(function(e) {
            if (isLoading || e.isFlush) return;
            scheduleAutoSave();
        });

        // Keyboard shortcuts
        addKeyboardShortcuts();
    }

    function scheduleAutoSave() {
        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(function() {
            saveScript();
        }, 3000); // 3 second debounce
    }

    function saveScript() {
        var content = editor.getValue();

        $.ajax({
            url: tsmMonaco.ajaxUrl,
            method: 'POST',
            data: {
                action: 'tsm_autosave_script',
                nonce: tsmMonaco.nonce,
                script_id: $('#script-id').val(),
                content: content
            },
            success: function(response) {
                if (response.success) {
                    $('#save-indicator').text('Saved at ' + new Date().toLocaleTimeString());
                }
            }
        });
    }

    function addKeyboardShortcuts() {
        // Ctrl+S / Cmd+S to save immediately
        editor.addAction({
            id: 'tsm-save',
            label: 'Save Script',
            keybindings: [monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS],
            run: function() {
                clearTimeout(autoSaveTimeout);
                saveScript();
            }
        });

        // Ctrl+Enter to execute
        editor.addAction({
            id: 'tsm-execute',
            label: 'Execute Script',
            keybindings: [monaco.KeyMod.CtrlCmd | monaco.KeyCode.Enter],
            run: function() {
                $('#execute-script-btn').click();
            }
        });
    }

    function loadWordPressAutocomplete() {
        // Check localStorage cache first
        var cachedFuncs = localStorage.getItem('tsm-wp-functions-6.7');
        if (cachedFuncs) {
            registerAutocomplete(JSON.parse(cachedFuncs));
            return;
        }

        // Load from server
        $.getJSON(tsmMonaco.ajaxUrl + '?action=tsm_get_wp_functions', function(data) {
            localStorage.setItem('tsm-wp-functions-6.7', JSON.stringify(data));
            registerAutocomplete(data);
        });
    }

    function registerAutocomplete(wpFunctions) {
        monaco.languages.registerCompletionItemProvider('php', {
            provideCompletionItems: function(model, position) {
                var word = model.getWordUntilPosition(position);
                var range = {
                    startLineNumber: position.lineNumber,
                    endLineNumber: position.lineNumber,
                    startColumn: word.startColumn,
                    endColumn: word.endColumn
                };

                var suggestions = wpFunctions.map(function(func) {
                    return {
                        label: func.name,
                        kind: monaco.languages.CompletionItemKind.Function,
                        documentation: func.description || '',
                        detail: func.signature || '',
                        insertText: func.snippet || func.name,
                        insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                        range: range
                    };
                });

                return { suggestions: suggestions };
            }
        });
    }

    // Expose editor globally for WordPress admin scripts
    window.tsmEditor = editor;

})(jQuery);
```

### Theme Toggle Implementation
```javascript
// Source: Monaco Editor theme switching docs
// File: assets/js/monaco-theme-toggle.js

(function($) {
    'use strict';

    $('#editor-theme-toggle').on('click', function() {
        var currentTheme = localStorage.getItem('tsm-editor-theme') || 'vs-dark';
        var newTheme = currentTheme === 'vs-dark' ? 'vs' : 'vs-dark';

        monaco.editor.setTheme(newTheme);
        localStorage.setItem('tsm-editor-theme', newTheme);

        // Update button text
        $(this).text(newTheme === 'vs-dark' ? 'Light Theme' : 'Dark Theme');

        // Save to user meta via AJAX
        $.post(tsmMonaco.ajaxUrl, {
            action: 'tsm_save_editor_theme',
            nonce: tsmMonaco.nonce,
            theme: newTheme
        });
    });

})(jQuery);
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| AMD module loading | ESM or CDN global | v0.50.0 (2025) | AMD deprecated, will be removed in future versions |
| Manual RequireJS config | @monaco-editor/loader | 2023+ | Cleaner API, handles AMD complexity |
| Basic SQL support | monaco-sql-languages | Ongoing | Third-party adds MySQL, PostgreSQL, Hive support |
| Manual function lists | WordPress Hooks Intellisense | 2022+ | Automattic maintains official WordPress function data |
| Static editor height | automaticLayout: true | Always available | Responsive editor without manual resize handling |

**Deprecated/outdated:**
- **AMD loading:** Monaco README explicitly states "AMD support is deprecated and will be removed in future versions" - use ESM or CDN global pattern
- **monaco-languages package:** Replaced by built-in language support in monaco-editor core
- **Custom editor.layout() calls:** Modern pattern is `automaticLayout: true` in config

## Open Questions

Things that couldn't be fully resolved:

1. **WordPress Hooks Intellisense Browser Compatibility**
   - What we know: Automattic's VS Code extension has comprehensive WordPress function data
   - What's unclear: How to extract and convert extension data to browser-compatible JSON format
   - Recommendation: Parse WordPress source using PHP reflection to generate function list with PHPDoc descriptions

2. **Vue.js Syntax Highlighting Quality**
   - What we know: Monaco has Vue language support via third-party grammar (Shiki integration)
   - What's unclear: Whether it handles single-file components with `<template>`, `<script>`, `<style>` sections properly
   - Recommendation: Test with sample Vue SFC, fall back to treating as HTML if highlighting poor

3. **Performance with Large Scripts**
   - What we know: Monaco Editor can handle large files (10,000+ lines)
   - What's unclear: At what file size does browser tab become sluggish, should we limit script size
   - Recommendation: Set reasonable file size limit (e.g., 5000 lines), show warning if exceeded

4. **SQL Dialect Support Necessity**
   - What we know: Built-in SQL is basic, monaco-sql-languages adds MySQL/PostgreSQL support
   - What's unclear: Whether test scripts will use advanced SQL features requiring dialect support
   - Recommendation: Start with built-in SQL, add monaco-sql-languages only if users request MySQL-specific features

## Sources

### Primary (HIGH confidence)
- [Monaco Editor Official Repository](https://github.com/microsoft/monaco-editor) - Current version (v0.55.1), ESM integration, AMD deprecation notice
- [Monaco Editor API Documentation](https://microsoft.github.io/monaco-editor/typedoc/) - KeyCode, KeyMod, addAction, onDidChangeModelContent APIs
- [jsDelivr CDN Package Page](https://www.jsdelivr.com/package/npm/monaco-editor) - CDN URLs, version information, package contents
- [WordPress Developer Reference - admin_enqueue_scripts](https://developer.wordpress.org/reference/hooks/admin_enqueue_scripts/) - Script enqueueing patterns

### Secondary (MEDIUM confidence)
- [Scripts Organizer Plugin Documentation](https://updates.dplugins.com/scripts-organizer-visual-studio-code-editor-inside-wordpress/) - WordPress autocomplete implementation using Automattic's Hooks Intellisense
- [Building a code editor with Monaco (Expo Dev Blog)](https://blog.expo.dev/building-a-code-editor-with-monaco-f84b3a06deaf) - Debounce pattern and auto-save implementation
- [Monaco Theme Switching Guide (StudyRaid)](https://app.studyraid.com/en/read/15534/540318/dynamically-switching-themes-during-runtime) - Theme API usage patterns
- [DBlocks CodePro WordPress Plugin](https://dplugins.com/dblocks/code-pro/) - Recent WordPress integration (Monaco 0.52.0, WordPress 6.7.1 compatible)

### Tertiary (LOW confidence)
- [monaco-sql-languages GitHub](https://github.com/DTStack/monaco-sql-languages) - SQL dialect support (MySQL, PostgreSQL), marked for validation
- [Monaco AMD Loader Conflicts GitHub Issue #797](https://github.com/microsoft/monaco-editor/issues/797) - Community discussion on RequireJS conflicts, needs verification with WordPress context
- [monaco-editor-vue3 npm package](https://www.npmjs.com/package/monaco-editor-vue3) - Vue 3 wrapper (1.0.5), needs testing for WordPress compatibility

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Monaco v0.55.1 is official, jsDelivr CDN is verified by Monaco docs
- Architecture: MEDIUM - Patterns verified from multiple WordPress plugins but not officially documented by Monaco
- Pitfalls: MEDIUM - Based on GitHub issues and WordPress 6.7 changes, needs testing in plugin context

**Research date:** 2026-01-30
**Valid until:** 2026-02-27 (30 days - Monaco is stable but WordPress compatibility changes with major WP versions)
