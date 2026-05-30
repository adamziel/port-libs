# JSON Table Generated Path Rowid Cost Current Source Next194

## Behavior

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext194()`.
- Extends accepted next190 xColumn-yield coverage with a pinned-source profile for generated-path rowid scans.
- Reuses the emitted active row only when the current source generation, generated/root path, xColumn row fingerprint, selected rowids, and final-cost fingerprints remain current.
- Forces next-source reprepare when the copied `wp_options` JSON source changes after the current row has been yielded.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext194Test.php`
- Result: `1 test files, 64 assertions, 0 failures`
- Application smoke: `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next194.php --self-test`
- Result: `application-json-table-generated-path-rowid-cost-current-source-next194 self-test passed`

## Non-Overlap

- Avoids accepted JSON table source/cursor wiring, hidden/visible constraint pushdown, generated-path cost/source/best-index/yield/materialization/batch/cursor/resume/final-cost behavior, and next190 xColumn yield-row admission.
- This slice is limited to the post-xColumn source pin/reprepare decision for generated-path rowid scans.

## Dependency Closure

No new support component is needed. The slice reuses native PHP JSON table planning, generated-path validation, rowid alias normalization, xColumn yield rows, and current-source fingerprints.
