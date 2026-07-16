# rowvalue-update-delete-returning-savepoint-current-source-next146

Status: focused PHP behavior growth for row-value UPDATE/DELETE RETURNING current-source handling when `UPDATE OR ROLLBACK` conflicts inside a savepoint.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext146Plan`. It models a Application `wp_options` import batch where earlier row-value UPDATE and DELETE statements produce attempted `RETURNING` rows, then a later row-value `UPDATE OR ROLLBACK` hits the composite `(blog_id, option_name)` uniqueness constraint. SQLite's rollback conflict action rolls back the transaction, so the current and next source return to the transaction image, the savepoint is not preserved, and attempted prior `RETURNING` rows are discarded rather than committed or retried.

Application smoke: `application-rowvalue-rollback-returning-current-source-next146.php` covers copied `wp_options` option staging and transient cleanup where a later duplicate `siteurl` move aborts the whole import transaction.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext146Test.php
1 test files, 59 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-rowvalue-rollback-returning-current-source-next146.php --self-test
application-rowvalue-rollback-returning-current-source-next146 self-test passed
```

Dashboard delta: update `phpPass` by the focused PASS-line/assertion delta verified for this new test file (`+59`, from `64992` to `65051`). `benchmarkDenominator.mapped` is unchanged; this is additional current-source PHP behavior over already mapped row-value UPDATE/DELETE RETURNING and savepoint/conflict surfaces, not a newly hydrated upstream Tcl inventory row.

Dependency closure: no new support component is needed. The slice reuses lane-local row-value UPDATE/DELETE RETURNING parsing/execution and savepoint current-source primitives.

Non-overlap: this avoids accepted next132 `OR FAIL` partial statement preservation, next140 `OR ABORT` savepoint-preserved behavior, next143 rollback-to-savepoint retry behavior, next142 row-value DISTINCT RETURNING predicates, trigger RETURNING savepoint work, and WAL/pager/VFS savepoint application clusters. The new behavior is specifically `UPDATE OR ROLLBACK` transaction rollback discarding attempted UPDATE/DELETE RETURNING streams and restoring the transaction current source.

Next task: wire this conflict-action boundary into parser-level DML execution once native transaction state owns savepoint release/rollback directly.
