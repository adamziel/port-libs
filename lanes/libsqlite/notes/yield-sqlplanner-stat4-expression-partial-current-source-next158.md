# sqlplanner-stat4-expression-partial-current-source-next158

## Behavior

- Added `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` for a bounded current-source planner handoff where a stale prepared statement uses a partial `lower(option_name)` expression index with STAT4 range samples.
- The slice materializes the current STAT4 lower/upper range fence, blocks deleted prepared rowids, admits inserted/refreshed current rowids, and keeps table lookup elided for the covering range window.
- Application path: copied `wp_options` plugin-option scans after ANALYZE/source changes can keep using the current partial expression covering index without reading stale prepared rows.

## Evidence

- Focused test:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext158Test.php`
  - Result: `1 test files, 61 assertions, 0 failures`
- Example smoke:
  - `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next158.php`
  - Result: valid JSON with `status` `stat4-expression-partial-current-source-next158-ready`, rowids `[11,21,31,41,51]`, and stale rowid `[81]` blocked.

## Non-Overlap

This avoids accepted next133 row-generation fences, next142 partial-covering ORDER blocks, next144 point-predicate replacement, next149 skip-scan expression ranges, expression ORDER BY, JSON table, WAL, VFS, and B-tree clusters. The new behavior is specifically STAT4 partial expression range-window current-source selection with stale prepared row exclusion.

## Dependency Closure

No new support component is needed. The patch composes existing native PHP STAT4 expression-index planning, partial-predicate proof, and covering current-source row streams.
