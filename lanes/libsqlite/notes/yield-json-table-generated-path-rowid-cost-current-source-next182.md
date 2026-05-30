# JSON table generated path rowid cost current-source next182

Behavior slice: `json-table-generated-path-rowid-cost-current-source-next182`

## Implementation

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext182()`.
- Extends the existing generated-path rowid cache/yield chain with xNext admission metadata for batched cursor continuation.
- Records deliverable rowids, blocked rowids, limit fences, order fences, xNext vs xFilter restart opcodes, source fences, estimated xNext cost, and current/next admission transitions.
- Prevents a copied `wp_options` JSON table cursor from yielding stale generated-path rowids when the next source changes cache generation or xBestIndex fingerprint.

## Verification

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext182Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next182.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext182Test.php`
  - `1 test files, 56 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next182.php --self-test`
  - `application-json-table-generated-path-rowid-cost-current-source-next182 self-test passed`

## Non-Overlap

This avoids accepted JSON table SELECT source/cursor wiring, hidden/visible constraints, generated path rowid cost/admission/source-cache layers through next178, and storage/VFS/B-tree surfaces. The new behavior is the xNext admission fence above the existing generated-path rowid yield profile.

## Dependency Closure

No new support component is needed. The slice reuses native PHP JSON table row generation, generated-path rowid cost/cache/yield profiles, JSON path validation, and planner transition helpers.
