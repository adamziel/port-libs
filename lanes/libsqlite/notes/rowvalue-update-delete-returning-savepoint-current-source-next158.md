# Row-value UPDATE/DELETE RETURNING savepoint current-source next158

Status: focused PHP behavior growth for row-value `UPDATE`/`DELETE ... RETURNING` batches that explicitly `ROLLBACK TO` an open savepoint and then retry from the restored current source.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext158Plan`. It models the SQLite boundary where `ROLLBACK TO savepoint` discards speculative RETURNING rows, restores the savepoint image as the current source, keeps the savepoint active, and lets the next UPDATE/DELETE statements run and yield rows from that restored source before release.

Application smoke: `application-rowvalue-update-delete-returning-savepoint-current-source-next158.php` covers a copied `wp_options` import cleanup that stages option-name rewrites and transient deletes, rolls back that speculative pass, then retries the rewrite/delete batch from the original savepoint image.

Verification:

```bash
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext158Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext158Test.php
php -l lanes/libsqlite/examples/application-rowvalue-update-delete-returning-savepoint-current-source-next158.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext158Test.php
php lanes/libsqlite/examples/application-rowvalue-update-delete-returning-savepoint-current-source-next158.php --self-test
git diff --check -- lanes/libsqlite
```

Expected dashboard delta: `phpPass` increases by the focused PASS-line count from this new test file. Mapped upstream coverage remains unchanged; this is current-source behavior over existing row-value UPDATE/DELETE RETURNING and savepoint inventory.

Non-overlap: avoids accepted next146 OR ROLLBACK transaction rollback, next149 DISTINCT/real-literal row-value behavior, next148/next147 conflict DISTINCT handling, next144 delete RETURNING savepoint behavior, next141 BETWEEN current-source delete/update behavior, trigger/FK RETURNING savepoint slices, WAL/pager savepoint byte/application slices, and B-tree/JSON/encoding/PRAGMA clusters. The new surface is explicit `ROLLBACK TO` followed by retry statements reading the restored current source and yielding only retry RETURNING rows.

Dependency closure: no new support component is needed. The slice reuses native PHP row-value predicate/assignment parsing, UPDATE/DELETE RETURNING execution, unique-constraint checks, and lane-local savepoint current-source modeling.

Next task: continue with non-overlapping SQL executor/planner row-value behavior or storage-backed savepoint application; avoid another row-value savepoint wrapper unless it adds a distinct upstream state transition.
