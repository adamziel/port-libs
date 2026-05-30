# sqlplanner-stat4-expression-partial-current-source-next180

## Behavior

Adds a bounded current-source planner handoff for descending STAT4-backed
partial expression indexes over copied `wp_options` rows. The slice reuses the
accepted inclusive `BETWEEN` admission for `lower(option_name)` partial indexes,
then materializes the reverse covering cursor shape: `SeekLE`, `IdxGE`, and
`Prev` over current-source STAT4 boundaries.

Application path: copied plugin option scans can keep a descending
`lower(option_name)` partial expression index after ANALYZE/source churn without
falling back to a stale ascending prepared cursor.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext180Test.php`
  - `1 test files, 59 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next180.php`
  - emits JSON with `status` =
    `stat4-expression-partial-current-source-next180-ready` and descending
    `matchedRowids` `[60, 30, 20, 10]`

## Non-Overlap

Avoids accepted next177 inclusive BETWEEN admission, expression ORDER BY,
range-cost ranking, JSON table, WAL, VFS, B-tree, trigger, and upstream-runner
surfaces. This slice only adds descending scan direction and reverse cursor
materialization for current-source STAT4 partial expression indexes.

## Dependency Closure

No new support component is needed. The patch reuses lane-local current-source
STAT4 expression partial planning and adds bounded native PHP reverse-scan
diagnostics.
