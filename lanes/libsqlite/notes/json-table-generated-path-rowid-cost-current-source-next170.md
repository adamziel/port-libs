# JSON Table Generated Path Rowid Cost Current Source Next170

## Behavior

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext170()` on top of the accepted next166 generated-path rowid yield planner.
- The new profile records an xFilter/current-source cursor view: `idxNum`, `idxStr`, argv columns/values, omitted and residual columns, cursor mode, pinned rowid/path tape, cursor rows, estimates, cost class, and replan reasons.
- Application smoke: copied `wp_options` plugin-rule JSON diagnostics can keep a generated-path plus rowid point lookup pinned to the current `json_tree()` source until xFilter reset, while a changed generated path prepares a fresh virtual-table filter.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext170Test.php`
- Result: `1 test files, 57 assertions, 0 failures`
- Example smoke: `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next170.php`

## Non-Overlap

- Avoids accepted next161/next163/next166 behavior by reusing their generated-path, rowid seek, best-index, and yield profiles without changing their outputs.
- Does not repeat accepted JSON hidden/visible constraint pushdown, parser-level JSON table SELECT sources, JSON table cursor behavior, generated path rowid next161, or generated path rowid yield next166.

## Dependency Closure

No new support component is needed. This reuses native JSON table planning, JSONB/subtype input handling, rowid alias normalization, generated-path validation, and current-source planner profiles already present in `lanes/libsqlite/src`.
