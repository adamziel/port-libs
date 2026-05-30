# JSON table generated path rowid cost current-source next185

Behavior slice: `json-table-generated-path-rowid-cost-current-source-next185`

## Implementation

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext185()`.
- Extends the accepted generated-path rowid xNext admission layer with a current-source resume checkpoint.
- Records delivered rowids, blocked rowids, last delivered rowid, next resume ordinal, projected xColumn rows, checkpoint tape, resume token, stale next-source reset state, and cost/replan transitions.
- Prevents copied `wp_options` JSON diagnostics from resuming a generated-path rowid cursor after the next source changes cache or admission fingerprints.

## Verification

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext185Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext185Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next185.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next185.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext185Test.php`
  - `1 test files, 63 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next185.php --self-test`
  - `application-json-table-generated-path-rowid-cost-current-source-next185 self-test passed`
- `git diff --check -- lanes/libsqlite`
  - no output

## Non-Overlap

This avoids accepted JSON table cursor/source wiring, hidden/visible constraints, generated-path rowid cost/cache/yield/xNext layers through next182, and storage/VFS/B-tree surfaces. The new behavior is the resume checkpoint above xNext admission: it materializes only the delivered batch, carries the last delivered rowid and projection tape, and forces next-source restart when the current-source cache is stale.

## Dependency Closure

No new support component is needed. The slice reuses native PHP JSON table row generation, generated-path rowid xNext admission, JSON path validation, current-source cache fingerprints, and projection validation.
