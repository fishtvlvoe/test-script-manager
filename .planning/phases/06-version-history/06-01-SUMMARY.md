---
phase: 06-version-history
plan: 01
subsystem: database
tags: [version-control, wp-cron, user-attribution, retention-policy]

# Dependency graph
requires:
  - phase: 02-script-crud-storage
    provides: ScriptService CRUD operations
  - phase: 01-security-foundation
    provides: Database schema management with dbDelta
provides:
  - Automatic version snapshots before each script code update
  - Version history with user attribution (created_by column)
  - Retention policy cleanup service (50 versions OR 30 days)
  - Daily WP-Cron cleanup for old versions
affects: [06-02-version-history-ui, 06-03-version-restore]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Automatic version snapshots via ScriptService::update() hook"
    - "Retention policy: dual-threshold (count AND time)"
    - "User attribution via LEFT JOIN to wp_users table"

key-files:
  created:
    - includes/services/class-version-service.php
    - includes/services/class-cleanup-service.php
  modified:
    - includes/class-database.php
    - includes/services/class-script-service.php
    - includes/class-plugin.php

key-decisions:
  - "Retention policy keeps last 50 versions OR last 30 days (whichever keeps more)"
  - "Version snapshot only created if code actually changes"
  - "Cleanup filters (tsm_versions_to_keep, tsm_version_retention_days) allow site-level customization"
  - "Daily WP-Cron cleanup instead of cleanup on each save for better performance"
  - "Version restore creates snapshot of current code before rollback"

patterns-established:
  - "Service layer pattern: VersionService and CleanupService as static utility classes"
  - "Database migration via dbDelta and DB_VERSION constant increment"
  - "User attribution via get_current_user_id() ?: null for nullable foreign key"

# Metrics
duration: 3min
completed: 2026-01-30
---

# Phase 6 Plan 1: Version History Backend Summary

**Automatic version snapshots with user attribution and retention policy cleanup via WP-Cron**

## Performance

- **Duration:** 3 min 1 sec
- **Started:** 2026-01-30T13:58:00Z
- **Completed:** 2026-01-30T14:01:01Z
- **Tasks:** 3
- **Files modified:** 5

## Accomplishments

- Version snapshots automatically created before each script code update
- User attribution tracked via created_by column with LEFT JOIN to wp_users
- Retention policy cleanup service (keeps last 50 versions OR last 30 days)
- Daily WP-Cron cleanup registered and functioning
- All versions cleaned up when script is deleted

## Task Commits

Each task was committed atomically:

1. **Task 1: Database schema migration for created_by column** - `1ef2c2e` (feat)
2. **Task 2: Create VersionService class** - `7bc4eb1` (feat)
3. **Task 3: Create CleanupService and integrate versioning** - `8e80b5c` (feat)

## Files Created/Modified

**Created:**
- `includes/services/class-version-service.php` - Version CRUD operations: create_version, get_version, get_versions, count_versions, restore_version, delete_all_versions
- `includes/services/class-cleanup-service.php` - Retention policy cleanup with WP-Cron registration

**Modified:**
- `includes/class-database.php` - DB version bumped to 1.2.0, added created_by column to tsm_script_versions
- `includes/services/class-script-service.php` - Integrated version snapshots in update() and cleanup in delete()
- `includes/class-plugin.php` - Loaded new services and registered cleanup cron

## Decisions Made

**Retention policy strategy:**
- Keeps last 50 versions OR versions from last 30 days (whichever keeps more)
- Uses dual-threshold approach: versions must fail BOTH count AND time thresholds to be deleted
- Configurable via WordPress filters for site-level customization

**Performance optimization:**
- Version snapshot only created if code actually changes (prevents duplicate snapshots)
- Cleanup runs daily via WP-Cron instead of on each save (avoids overhead)
- Subquery pattern for "keep last N" to work with MySQL 5.x without window functions

**User attribution:**
- get_current_user_id() ?: null handles both logged-in users and background processes
- LEFT JOIN to wp_users for display_name, gracefully handles deleted users

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - dbDelta migration, service integration, and WP-Cron registration all worked as expected.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

**Ready for Phase 6 Plan 2 (Version History UI):**
- Backend API complete with full CRUD operations
- Version history can be fetched via VersionService::get_versions()
- User attribution ready for display in UI
- Restore functionality ready for UI integration

**Ready for Phase 6 Plan 3 (Version Restore & Comparison):**
- VersionService::restore_version() fully implemented
- Snapshots current code before restore
- Filesystem sync included in restore operation

**Verification completed:**
- ✓ Version snapshots created automatically on script update
- ✓ User attribution tracked correctly (created_by column)
- ✓ Versions cleaned up automatically on script delete
- ✓ WP-Cron registered and scheduled daily
- ✓ All 3 must-have truths verified

---
*Phase: 06-version-history*
*Completed: 2026-01-30*
