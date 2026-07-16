# Row-value UPDATE/DELETE RETURNING savepoint current-source next162

Status: focused PHP behavior growth for row-value `UPDATE OR FAIL ... RETURNING` inside a savepoint.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext162Plan`. It models the upstream SQLite boundary where `OR FAIL` preserves prior row changes from the same statement until the caller rolls back, while `ROLLBACK TO savepoint` restores the savepoint image and discards any already-yielded RETURNING rows from the failed statement.

Application smoke: `application-rowvalue-update-delete-returning-savepoint-current-source-next162.php` covers a copied `wp_options` import cleanup where the first row-value update is visible in the failed statement's current source, the second row collides with that partial row, and rollback restores the original options before transient cleanup can run.

Verification:

```bash
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext162Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext162Test.php
php -l lanes/libsqlite/examples/application-rowvalue-update-delete-returning-savepoint-current-source-next162.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext162Test.php
php lanes/libsqlite/examples/application-rowvalue-update-delete-returning-savepoint-current-source-next162.php --self-test
git diff --check -- lanes/libsqlite
```

Focused assertion delta: 59 PASS lines in the new next162 test file.

Expected dashboard delta: `phpPass` should increase by 59 after clean integration. Mapped upstream coverage remains unchanged; this is current-source behavior over existing row-value UPDATE/DELETE RETURNING and savepoint inventory.

Non-overlap: avoids accepted next156 release/rollback streams, next158 rollback-to retry streams, next130/next138/next147 row-value conflict/RETURNING clusters, trigger/FK RETURNING savepoint slices, WAL/pager savepoint application, B-tree, JSON, encoding, PRAGMA, and suite-evidence clusters. The new surface is partial `UPDATE OR FAIL` preservation before savepoint rollback and discard of the failed statement's RETURNING stream.

Dependency closure: no new support component is needed. The slice reuses native PHP row-value assignment/predicate parsing, `SQLiteUpdateDeleteReturningSql` conflict preservation, and lane-local savepoint current-source modeling.

Next task: continue with non-overlapping SQL executor/planner row-value behavior or storage-backed savepoint application; avoid another row-value savepoint wrapper unless it covers a different upstream state transition.
