# JSON Table Generated Path Rowid Cost Current Source Next184

## Behavior

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext184()`.
- Extends the generated-path plus rowid current-source chain after next181 xColumn snapshots with a final cost/admission profile.
- Admits a pinned current-source snapshot only when rowid aliases, projection, source generation, reusable xColumn state, and rowid cost remain covering.
- Forces next-source reprepare when the copied `wp_options` JSON source changes, and classifies missing-rowid reseek cases separately from stale next-source reparses.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext184Test.php`
- Result: `1 test files, 52 assertions, 0 failures`
- Application smoke: `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next184.php --self-test`
- Result: `application-json-table-generated-path-rowid-cost-current-source-next184 self-test passed`

## Non-Overlap

- Does not repeat accepted JSON table cursor/source wiring, hidden/visible constraint extraction, next161 admission, next175 cache generation, next177 xFilter, next180 materialization, or next181 xColumn snapshot materialization.
- This slice is limited to the final generated-path rowid current-source cost/admission decision after the xColumn snapshot exists.

## Dependency Closure

No new support component is needed. The slice reuses native PHP JSON table planning, generated-path validation, rowid alias normalization, xColumn snapshot materialization, and current-source cost profiles.
