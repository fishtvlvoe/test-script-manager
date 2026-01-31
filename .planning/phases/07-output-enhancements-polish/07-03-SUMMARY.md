---
phase: 07-output-enhancements-polish
plan: 03
subsystem: backend-services
tags: [categories, templates, rest-api, crud]

dependency-graph:
  requires:
    - 01-01 (database tables tsm_categories, tsm_script_tags)
    - 02-01 (ScriptService for template creation)
  provides:
    - CategoryService for script organization
    - TemplateService for quick-start patterns
    - REST APIs for categories and templates
  affects:
    - 07-04 (UI integration will consume these APIs)

tech-stack:
  added: []
  patterns:
    - Static service class pattern (consistent with existing services)
    - REST API with permission callbacks
    - JSON file-based template storage

file-tracking:
  created:
    - includes/services/class-category-service.php
    - includes/services/class-template-service.php
    - includes/api/class-categories-api.php
    - includes/api/class-templates-api.php
    - templates/script-templates.json
  modified:
    - includes/class-plugin.php

decisions:
  - key: Static service methods
    choice: All CategoryService and TemplateService methods are static
    rationale: Consistent with existing service classes (ScriptService, VersionService)
  - key: JSON file for templates
    choice: Store templates in templates/script-templates.json
    rationale: Easy to edit, version control friendly, no database overhead
  - key: Template ID as key
    choice: Template ID is the JSON object key (e.g., "db-query")
    rationale: Simple lookup, human-readable, slug-safe

metrics:
  duration: 4 min
  completed: 2026-01-31
---

# Phase 07 Plan 03: Categories and Templates Backend Summary

**One-liner:** CategoryService and TemplateService with full CRUD, script associations, and REST APIs for organizing scripts and quick-start templates.

## What Was Built

### CategoryService (`includes/services/class-category-service.php`)

Complete CRUD operations for script categories:

| Method | Purpose |
|--------|---------|
| `create($name)` | Create category with auto-generated slug |
| `get($id)` | Fetch single category |
| `get_all()` | List all categories (ordered by name) |
| `update($id, $name)` | Update category name and slug |
| `delete($id)` | Delete category and all associations |
| `get_script_categories($script_id)` | Get categories for a script |
| `set_script_categories($script_id, $category_ids)` | Set script's categories |
| `delete_script_categories($script_id)` | Remove all categories from script |
| `get_scripts_by_category($category_id)` | Get script IDs in a category |

### TemplateService (`includes/services/class-template-service.php`)

Template library management:

| Method | Purpose |
|--------|---------|
| `get_templates()` | List all built-in templates |
| `get_template($template_id)` | Get specific template by ID |
| `create_from_template($template_id, $name)` | Create new script from template |

### Built-in Templates (`templates/script-templates.json`)

6 essential script patterns:

| Template ID | Name | Description |
|-------------|------|-------------|
| `db-query` | Database Query | Execute $wpdb query with table output |
| `user-list` | List Users | Get WordPress users with formatted display |
| `option-get` | Get Option | Retrieve and display option value |
| `transient-check` | Check Transient | Check transient value and expiration |
| `post-meta` | Get Post Meta | Retrieve all meta for a post |
| `api-request` | External API Request | Make HTTP request with wp_remote_get |

### REST APIs

**Categories_API** (`includes/api/class-categories-api.php`):

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/categories` | List all categories |
| POST | `/categories` | Create new category |
| GET | `/categories/{id}` | Get single category |
| PUT | `/categories/{id}` | Update category |
| DELETE | `/categories/{id}` | Delete category |
| GET | `/scripts/{id}/categories` | Get script's categories |
| PUT | `/scripts/{id}/categories` | Set script's categories |

**Templates_API** (`includes/api/class-templates-api.php`):

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/templates` | List all templates |
| GET | `/templates/{id}` | Get single template |
| POST | `/templates/{id}/create` | Create script from template |

## Verification Results

### Artifact Verification

| File | Min Lines | Actual Lines | Status |
|------|-----------|--------------|--------|
| class-category-service.php | 80 | 384 | PASS |
| class-template-service.php | 50 | 115 | PASS |
| class-categories-api.php | 60 | 343 | PASS |
| class-templates-api.php | 40 | 176 | PASS |
| script-templates.json | 30 | 38 | PASS |

### Key Link Verification

- Categories_API calls CategoryService methods
- Templates_API calls TemplateService methods
- TemplateService::create_from_template calls ScriptService::create

## Commits

| Hash | Description |
|------|-------------|
| 536b1e8 | feat(07-03): create CategoryService with CRUD and association methods |
| da8c505 | feat(07-03): create TemplateService and built-in templates |
| 254415e | feat(07-03): create Categories and Templates REST APIs |

## Deviations from Plan

None - plan executed exactly as written.

## Next Phase Readiness

**Ready for 07-04:** UI integration can now consume these APIs to:
- Display category sidebar for filtering scripts
- Show template picker dialog for new script creation
- Allow script-category assignment in editor

**API consumers will need:**
- `tsmData.nonce` for authentication
- `tsmData.restUrl` for API base URL
- All endpoints require `manage_options` capability
