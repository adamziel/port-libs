# rowvalue-update-delete-returning-savepoint-current-source-next172

Status: focused PHP behavior growth for row-value UPDATE/DELETE RETURNING
streams yielded before `ROLLBACK TO` inside a savepoint.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext172Plan`.
It models a copied `wp_options` import batch where an `UPDATE ... RETURNING`
stream is observable to the caller before rollback, later DELETE/UPDATE
RETURNING streams are also attempted, and `ROLLBACK TO` restores the savepoint
image. The retry DELETE/UPDATE statements then read the restored current
source, while all pre-rollback RETURNING streams are tracked as non-durable.

Application smoke: `application-rowvalue-yield-savepoint-current-source-next172.php --self-test`
models plugin-option promotion plus transient cleanup after retry.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext172Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext172Test.php
php -l lanes/libsqlite/examples/application-rowvalue-yield-savepoint-current-source-next172.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext172Test.php
php lanes/libsqlite/examples/application-rowvalue-yield-savepoint-current-source-next172.php --self-test
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 77 assertions, 0 failures`, with 77 PASS lines.

Expected dashboard movement: `phpPass` moves from `76936` to `77013`. Mapped
upstream coverage remains `611 / 1589`; this is focused PHP behavior over
already mapped row-value UPDATE/DELETE RETURNING/savepoint behavior.

Non-overlap: avoids accepted next163 BETWEEN retry, next164 OR ROLLBACK retry,
next165 OR IGNORE continuation, next166 nested savepoint rollback, DELETE-only
next144, row-value DISTINCT/IS/conflict/upsert clusters, and pager/WAL/VFS
savepoint application clusters. The new surface is the caller-observable
RETURNING stream yielded before `ROLLBACK TO` and then suppressed as durable
state while retry reads the restored savepoint image.

Dependency closure: no new support component needed; the slice reuses
lane-local row-value UPDATE/DELETE RETURNING parsing/execution and savepoint
current-source modeling.

Next task: wire the same yielded-stream rollback boundary into parser-level
VDBE execution once the row-array DML plans are retired.
