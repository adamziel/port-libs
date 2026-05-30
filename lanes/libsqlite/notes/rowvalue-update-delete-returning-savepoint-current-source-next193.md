# Row-value UPDATE/DELETE RETURNING savepoint current source next193

Status: focused PHP behavior growth for row-value `UPDATE OR FAIL ... RETURNING` streams inside a savepoint, followed by `ROLLBACK TO` and a retry `UPDATE`/`DELETE` that reads the restored current source.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext193Plan`. It reuses the existing `SQLiteUpdateDeleteReturningSql` row-value parser/executor and exercises its `preserveFailChanges` path so an `OR FAIL` statement can yield earlier `RETURNING` rows before a later unique-key conflict. The plan then models the SQLite savepoint boundary: the partial fail stream is recorded as suppressed by `ROLLBACK TO`, the savepoint remains active, and retry statements run from the restored savepoint image.

Focused evidence:

```
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext193Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext193Test.php
php -l lanes/libsqlite/examples/application-rowvalue-fail-stream-savepoint-current-source-next193.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext193Test.php
php lanes/libsqlite/examples/application-rowvalue-fail-stream-savepoint-current-source-next193.php --self-test
git diff --check -- lanes/libsqlite
```

Non-overlap: this avoids accepted next189 row-value NOT BETWEEN/NOT IN rollback-retry coverage, accepted rowvalue186 conflict rebase items, trigger/UPSERT RETURNING conflict clusters, savepoint page-image/WAL byte/VFS rollback apply clusters, and accepted SELECT/JSON/B-tree/VFS surfaces. The new behavior is specifically the `OR FAIL` partial `RETURNING` stream under row-value unique conflict and its suppression after savepoint rollback.

Dependency closure: no new support component is needed. The slice reuses lane-local row-value UPDATE/DELETE RETURNING execution and savepoint current-source modeling.

Next task: continue with broader parser/executor current-source behavior or pager/VFS transaction application; avoid another row-value savepoint slice unless it covers a distinct upstream conflict/rollback semantic.
