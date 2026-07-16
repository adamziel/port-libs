# rowvalue-update-delete-returning-savepoint-current-source-next207

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext207Plan`, covering row-value `UPDATE OR FAIL ... RETURNING` inside a savepoint.

The behavior is the SQLite current-source boundary where `OR FAIL` preserves and yields already-mutated prefix rows before the first unique conflict, but `ROLLBACK TO` the open savepoint discards that prefix before a retry statement reads the savepoint image. The focused Application smoke models copied `wp_options` import cleanup where a transient-name conflict aborts the middle row, suppresses the yielded prefix via savepoint rollback, and retries update/delete statements against the original option rows.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext207Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext207Test.php
php -l lanes/libsqlite/examples/application-rowvalue-fail-savepoint-current-source-next207.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext207Test.php
php lanes/libsqlite/examples/application-rowvalue-fail-savepoint-current-source-next207.php --self-test
git diff --check -- lanes/libsqlite
```

Expected dashboard delta: `phpPass` moves from `100791` to `100858` from 67 newly passing focused PASS lines. Mapped upstream coverage remains `621 / 1589`; this is focused PHP behavior over already mapped row-value/update/delete/savepoint inventory rather than a fresh upstream denominator row.

Dependency closure: no new support component is needed. The slice reuses native PHP row-value UPDATE/DELETE RETURNING execution, unique-conflict handling, and savepoint current-source images.

Non-overlap: avoids accepted row-value next200 `OR ABORT`, next202 parenthesized rollback/retry, next205 RELEASE current-source, next178 `OR ROLLBACK`, prior `OR IGNORE`/`OR REPLACE` conflict slices, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters. The new surface is specifically `OR FAIL` prefix preservation followed by `ROLLBACK TO` suppression and retry over the savepoint image.
