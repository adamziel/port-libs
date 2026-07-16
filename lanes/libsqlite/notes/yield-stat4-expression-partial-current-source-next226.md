# STAT4 Expression Partial Current Source Next226

## Behavior

Adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, a bounded
planner proof for partial expression-index range scans over copied
`wp_options` rows. The slice composes the accepted next219 peer-run cursor
proof, then verifies that the current STAT4 sample window for
`lower(option_name) BETWEEN ...` maps exactly to the current cursor rowids even
when STAT4 samples outside the range have churned.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext226Test.php`
  - `1 test files, 65 assertions, 0 failures`
  - `65` PASS lines

## Application Smoke

- `php lanes/libsqlite/examples/application-stat4-expression-partial-current-source-next226.php`
  - emits `status: stat4-expression-partial-current-source-next226-ready`
  - proves plugin-option range scans keep the current partial expression index
    while unrelated `theme_mods_*` / earlier plugin STAT4 samples churn outside
    the range window.

## Non-Overlap

This does not repeat accepted next219 peer-run boundary behavior,
expression-index range-cost ranking, SQL expression `ORDER BY`, JSON table
constraints/cursors, WAL/VFS durability, B-tree pointer-map/freeblock work, or
UTF/collation slices. The new behavior is only the STAT4 sample-window proof
inside a partial expression-index range.

## Dependency Closure

No new support component is needed. The slice reuses existing lane-local STAT4
expression partial row streams and cursor proof arrays.
