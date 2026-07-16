# rowvalue-update-delete-returning-savepoint-current-source-next185

Status: focused PHP behavior growth for row-value `UPDATE OR FAIL ... RETURNING`
inside a savepoint followed by `ROLLBACK TO` and durable retry.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext185Plan`.
It models copied `wp_options` cleanup/import rows where pre-fail row-value
UPDATE/DELETE RETURNING streams and a partial `OR FAIL` row stream are visible
before rollback, but `ROLLBACK TO` restores the savepoint image and suppresses
those attempted RETURNING rows before retrying durable UPDATE/DELETE RETURNING
statements.

Application smoke:
`application-rowvalue-or-fail-savepoint-current-source-next185.php --self-test`
covers transient cleanup plus retrying pending option imports after a row-value
unique conflict.

Verification:

```bash
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext185Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext185Test.php
php -l lanes/libsqlite/examples/application-rowvalue-or-fail-savepoint-current-source-next185.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext185Test.php
php lanes/libsqlite/examples/application-rowvalue-or-fail-savepoint-current-source-next185.php --self-test
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +76` from the new focused test file.
Mapped upstream coverage is unchanged; this is current-source PHP behavior over
already mapped row-value DML/savepoint/RETURNING inventory.

Dependency closure: no new support component is needed. The slice reuses
lane-local row-array UPDATE/DELETE RETURNING, row-value predicate/assignment,
unique-conflict, and savepoint current-source planning.

Non-overlap: avoids accepted next172 yielded rollback retry, next173/next181
`OR FAIL` nullable tuple retry coverage, next175 nested outer rollback, next178
`OR ROLLBACK`, next180 inner rollback, and next182 released-inner rollback. The
new behavior is specifically partial `UPDATE OR FAIL` row-value RETURNING rows
being discarded by an explicit savepoint rollback before retry from the original
current source.
