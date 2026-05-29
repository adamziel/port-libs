# trigger-recursive-view-returning-current-generation-seal

This consolidation slice keeps the current-source generation seal on
`SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan` and removes the
direct numbered generation-seal test/example/source contract names for this
cluster.

The behavior keeps current-source `RETURNING` rows visible while attempted next-source rows remain held until the current source generation, view generation, trigger generation, and per-row generation seals are acknowledged. Missing, unexpected, stale source-generation, stale view-generation, stale trigger-generation, and ordered-seal mismatch cases fence next-source rows without hiding already-yielded current rows.

The behavior still keeps current-source `RETURNING` rows visible while
attempted next-source rows remain held until the current source generation,
view generation, trigger generation, and per-row generation seals are
acknowledged. Missing, unexpected, stale source-generation, stale
view-generation, stale trigger-generation, and ordered-seal mismatch cases
fence next-source rows without hiding already-yielded current rows.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentReturningGenerationSealTest.php`
- `php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-generation-seal.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentReturningGenerationSealTest.php`
- Result: `1 test files, 104 assertions, 0 failures`.
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-generation-seal.php`
- Result: `wordpress-trigger-recursive-view-returning-current-generation-seal self-test passed`.

Expected dashboard movement: no `phpPass` or mapped-coverage movement; this
renames an existing direct focused test/example and production result contract
inside the consolidated trigger-returning family.

Dependency closure: no new support component is needed; this reuses the existing native recursive view/trigger `RETURNING` current-source, epoch, and next224 source-seal plans.

Non-overlap: this is after next224 source seals and avoids accepted next208 cursor close, next212 yield receipts, next218 epoch receipt admission, next224 source seals, DML RETURNING conflicts, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters.
