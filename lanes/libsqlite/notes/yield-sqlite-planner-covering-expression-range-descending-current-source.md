# SQLite Planner Covering Expression Range Current-Source Descending

## Behavior

- Added `SQLitePlannerCoveringExpressionRangeCurrentSourceNextPlan` for a stale prepared `lower(option_name) DESC` covering expression range cursor.
- The plan reparses against the current schema/stat4/index source, rejects stale prepared lower-bound rowids, streams current rows in descending expression-key order, and keeps payload projection on the covering index cursor without deferred table seeks.
- WordPress smoke: copied `wp_options` plugin admin scans can stream `lower(option_name) DESC` after ANALYZE/schema changes while preserving current/next cursor pairs.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerCoveringExpressionRangeDescendingCurrentSourceTest.php`
  - `1 test files, 67 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-covering-expression-range-descending-current-source.php --self-test`
  - `wordpress-covering-expression-range-current-source-descending self-test passed`

## Non-Overlap

- Avoids accepted next128 forward range recheck, next132 expression skip-scan, expression ORDER BY, range-cost ranking, and batch132 expression covering skip-scan planning.
- No new support component is needed; this reuses native expression-index STAT4 range planning and covering cursor diagnostics.
