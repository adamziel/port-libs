# Row-value UPDATE/DELETE RETURNING Savepoint Current Source Next224

Slice: `rowvalue-update-delete-returning-savepoint-current-source-next224`

This adds a bounded nested-savepoint row-value `UPDATE`/`DELETE ... RETURNING`
model. The inner savepoint is released into the outer savepoint, a later outer
rollback discards both the released inner changes and the outer attempted
changes, and retry statements read from the original outer savepoint image.

WordPress path:
`examples/wordpress-rowvalue-nested-savepoint-materialization.php`
models a copied `wp_options` import that batches transient cleanup and option
rewrites in an inner savepoint, then rolls back the outer import scope and
retries without yielding stale inner `RETURNING` rows.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueNestedSavepointMaterializationTest.php`
- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext224Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueNestedSavepointMaterializationTest.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-nested-savepoint-materialization.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-nested-savepoint-materialization.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dashboard delta: update `phpPass` by the focused PASS-line delta from the new
test file. Mapped upstream coverage is unchanged because this is additive PHP
behavior coverage over an already mapped row-value/RETURNING/savepoint surface.

Non-overlap: avoids accepted next218 explicit `ROLLBACK TO` image restoration,
next217 `OR ROLLBACK` transaction abort, next211 `OR IGNORE`, DML trigger
RETURNING, WAL/VFS savepoint application, JSON table, planner, and B-tree
clusters. The new surface is specifically a released inner savepoint whose
row-value `RETURNING` rows become suppressed by a later outer `ROLLBACK TO`.

Dependency closure: no new support component is needed. This reuses the native
PHP row-value `UPDATE`/`DELETE RETURNING` executor and row-array savepoint image
model.
