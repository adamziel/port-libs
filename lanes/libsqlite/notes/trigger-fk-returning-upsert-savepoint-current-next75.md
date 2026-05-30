# trigger-fk-returning-upsert-savepoint-current-next75

This slice adds a bounded current/next planner for the uncovered intersection of
recursive trigger-generated UPSERT rows, `RETURNING` output, deferred foreign-key
release failure, and retry inside the same preserved savepoint.

The new behavior is intentionally narrower than accepted trigger, FK,
RETURNING, UPSERT, and savepoint clusters: the current UPSERT can yield
`RETURNING` rows from attempted trigger/recursive changes, then `RELEASE`
detects deferred FK violations and rolls back to the current savepoint image.
The next retry starts from that preserved image and returns only committed retry
rows.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerFkReturningUpsertSavepointCurrentNext75Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerFkReturningUpsertSavepointCurrentNext75Test.php`
- `php -l lanes/libsqlite/examples/application-trigger-fk-returning-upsert-savepoint-current-next75.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerFkReturningUpsertSavepointCurrentNext75Test.php`
- `php lanes/libsqlite/examples/application-trigger-fk-returning-upsert-savepoint-current-next75.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local recursive trigger UPSERT savepoint planner and adds only
RETURNING projection plus deferred-FK release current/next composition.

Non-overlap: avoids accepted recursive trigger savepoint UPSERT, trigger
RETURNING FK savepoint, nested trigger RETURNING savepoint, trigger UPSERT
savepoint, UPSERT RETURNING expression, savepoint rollback/page-image/VFS
apply, rollback-journal/WAL checkpoint, JSON table, B-tree, VFS lock/write/sync,
and SQL SELECT text clusters. The new surface is release-time deferred FK
failure after attempted UPSERT `RETURNING` rows, followed by the next retry from
the preserved current savepoint.
