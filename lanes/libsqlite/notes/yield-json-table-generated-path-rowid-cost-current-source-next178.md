# JSON table generated path rowid cost current-source next178

Behavior slice: `json-table-generated-path-rowid-cost-current-source-next178`

## Implementation

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext178()`.
- Extends the accepted generated-path rowid current-source cache layer (`next175`) with xNext/yield resumption metadata for a pinned `json_each()` / `json_tree()` cursor.
- Records last-yielded rowid, resume ordinal, yielded/remaining/skipped rowid tapes, EOF state, xFilter reuse, source-fence restart state, cursor generation hash, yield cost, and current/next yield replan reasons.
- Prevents stale generated-path rowid cursor reuse when the next copied `wp_options` JSON source changes cache generation or xBestIndex fingerprint.

## Verification

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext178Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next178.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext178Test.php`
  - `1 test files, 62 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next178.php --self-test`
  - `application-json-table-generated-path-rowid-cost-current-source-next178 self-test passed`

## Non-overlap

This does not repeat next161 admission, next163 best-index metadata, next167 xFilter binding, next173 xBestIndex fingerprints, or next175 cache generation reuse/invalidation. It adds the cursor-yield/xNext resumption fence above the accepted generated-path rowid cache and avoids JSON visible/hidden constraints, JSON table SELECT/FROM cursor wiring, host joins, and storage/VFS/B-tree surfaces.

## Dependency Closure

No new support component is needed. The slice reuses native PHP JSON table row generation, generated-path rowid cost profiles, current-source cache metadata, and planner transition helpers.
