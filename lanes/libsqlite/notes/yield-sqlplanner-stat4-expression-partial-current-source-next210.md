# SQL Planner STAT4 Expression Partial Current Source Next210

## Behavior

- Adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`.
- Extends the current-source STAT4 expression partial-index planner chain after
  next209 grouped partial OR admission.
- Adds a duplicate expression-key peer fence: when a current STAT4 partial
  expression index yields multiple rows for the same expression key, the
  admitted stream records and verifies SQLite rowid tie-break ordering for
  those peers.

## WordPress smoke

- `lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next210.php`
- Scenario: copied `wp_options` diagnostics using a stale prepared
  `lower(option_name)` partial expression index after ANALYZE/schema refresh,
  preserving deterministic rowid order for duplicate plugin option names
  without `ext/sqlite`.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext210Test.php`
  - `1 test files, 58 assertions, 0 failures`
  - `58` PASS lines
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next210.php`
  - self-test passed and emitted ready JSON.

## Non-Overlap

This avoids accepted next209 grouped partial OR admission, next208 planner OR
behavior, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree,
trigger, and UTF clusters. The slice only proves rowid tie-break ordering for
duplicate expression keys inside an already admitted current-source STAT4
partial index stream.

## Dependency Closure

No new support component is needed. The slice reuses lane-local current-source
STAT4 expression partial planner fixtures and existing row payload diagnostics.
