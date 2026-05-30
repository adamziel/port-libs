# JSON Table Generated Path Rowid Cursor Next186

## Scope

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext186()`.
- Extends the accepted generated-path/rowid current-source chain after next183 batch materialization with a bounded `xNext` cursor profile.
- Covers pinned current-source cursor advancement, batch refill after rowid `5`, final/eof behavior after rowid `6`, stale next-source restart fencing, blocked residual cursor state, transition reasons, and cursor cost classes.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext186Test.php`
- Result: `1 test files, 51 assertions, 0 failures`
- PASS-line delta expected for libsqlite dashboard: `+51`
- Application smoke: `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next186.php --self-test`

## Non-Overlap

This does not repeat accepted JSON table visible/hidden constraints, parser-level JSON table SELECT sources, cursor open/rowid behavior, next180 materialization, or next183 batch-yield behavior. It starts from next183 output and adds only the current-source `xNext` cursor-state/restart profile for generated-path rowid scans.

## Dependency Closure

No new support component is needed. The slice reuses native JSON table path planning, rowid aliases, current-source materialization, and existing PHP JSON helpers.
