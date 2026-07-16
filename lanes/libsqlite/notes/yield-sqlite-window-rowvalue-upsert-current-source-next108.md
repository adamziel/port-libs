# window-rowvalue-upsert-current-source-next108

Implemented `SQLiteWindowRowValueUpsertCurrentSourcePlan`, a bounded native PHP behavior slice for UPSERT current-source semantics where `DO UPDATE WHERE` uses lexicographic row-value comparison and accepted `RETURNING` rows feed a window frame.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowRowValueUpsertCurrentSourceNext108Test.php`
- `php lanes/libsqlite/examples/application-window-rowvalue-upsert-current-source-next108.php --self-test`

Non-overlap:

- Avoids accepted grouped SELECT SQL text, row-value SELECT predicates, UPSERT RETURNING SQL parser coverage, trigger/RETURNING conflict queue, JSON table cursor/source/hidden/visible constraints, WAL/VFS checkpoint/savepoint/rollback apply, and B-tree page/freelist accepted clusters.
- This slice is statement-current UPSERT row-value predicate behavior plus bounded `RETURNING` window diagnostics.

Dependency closure:

- No new support component is needed. The slice reuses native PHP row-array state and `SQLiteWindowFunction`; it does not require ext/sqlite or an upstream binary.
