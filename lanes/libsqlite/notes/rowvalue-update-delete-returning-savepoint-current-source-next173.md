# Row-value update/delete RETURNING savepoint current-source next173

Status: focused PHP behavior growth for row-value `UPDATE` / `DELETE`
`RETURNING` savepoint retry semantics.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext173Plan`,
covering the current-source boundary where `UPDATE OR FAIL ... RETURNING`
can produce a partial row stream before a unique row-value conflict, but
`ROLLBACK TO` must discard that stream and retry later `UPDATE` / `DELETE
RETURNING` statements from the restored savepoint image. The retry also
exercises NULL-safe row-value predicates with `IS NOT DISTINCT FROM`.

Application smoke: `application-rowvalue-update-delete-returning-savepoint-current-source-next173.php`
models a copied `wp_options` import repair where an attempted option-name
rewrite collides, rollback discards the attempted row stream, retry updates
queued import rows, and cleanup deletes a transient row with `DELETE RETURNING`.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext173Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext173Test.php
php -l lanes/libsqlite/examples/application-rowvalue-update-delete-returning-savepoint-current-source-next173.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext173Test.php
php lanes/libsqlite/examples/application-rowvalue-update-delete-returning-savepoint-current-source-next173.php --self-test
```

Focused result: `1 test files, 70 assertions, 0 failures`, adding 70 focused
PASS lines for this lane patch.

Dependency closure: no new support component is needed. The slice reuses
native PHP row-value DML parsing/execution, unique-conflict handling, RETURNING
projection, and savepoint current-source planning.

Non-overlap: avoids accepted row-value RETURNING next117/next126/next130,
row-value savepoint conflict next128/next132/next138/next140, row-value
update/delete savepoint next161-next167, trigger RETURNING, WAL/pager/VFS,
B-tree, JSON, encoding, PRAGMA, planner, and suite-runner surfaces. The new
assertion surface is the partial `OR FAIL` RETURNING stream being discarded by
`ROLLBACK TO` before retrying both row-value `UPDATE RETURNING` and
`DELETE RETURNING` from the restored current source.
