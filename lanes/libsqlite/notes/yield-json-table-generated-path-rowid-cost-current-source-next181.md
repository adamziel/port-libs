# JSON table generated path rowid cost current-source next181

Behavior slice: `json-table-generated-path-rowid-cost-current-source-next181`

## Implementation

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext181()`.
- Extends the accepted generated-path rowid current-source yield layer (`next178`) with xColumn materialization from a pinned current-source `json_each()` / `json_tree()` row snapshot.
- Records projected xColumn names, remaining rowids, materialized rows, missing rowids, xColumn tape, snapshot fingerprint, reuse state, cost class, and current/next replan reasons.
- Prevents a changed next copied `wp_options` JSON source from reusing current-source xColumn values without reprepare.

## Verification

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext181Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next181.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext181Test.php`
  - `1 test files, 56 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next181.php --self-test`
  - `application-json-table-generated-path-rowid-cost-current-source-next181 self-test passed`

## Non-overlap

This avoids accepted next161 admission, next175 cache generation, next177 xFilter program, and next178 yield-resumption behavior. It adds the xColumn materialized-row snapshot above those layers and does not repeat JSON visible/hidden constraint pushdown, JSON SELECT/FROM source wiring, JSON cursor rewind/eof behavior, host joins, storage/VFS, B-tree, or compound SELECT surfaces.

## Dependency Closure

No new support component is needed. The slice reuses native PHP JSON table row generation, generated-path rowid current-source cache/yield profiles, and planner transition helpers.
