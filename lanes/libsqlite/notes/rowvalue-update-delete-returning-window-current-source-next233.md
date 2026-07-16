# rowvalue-update-delete-returning-window-current-source-next233

Status: focused PHP behavior growth for row-value `UPDATE`/`DELETE ... RETURNING`
streams where window metadata is computed over yielded, rolled-back, and retried
current-source rows.

This slice adds `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext233Plan`.
It reuses the native row-value `UPDATE`/`DELETE RETURNING` executor and models
the current-source boundary around a savepoint:

- yielded RETURNING rows keep their window receipt before `ROLLBACK TO`;
- attempted RETURNING rows after the yield phase are window-ranked but
  suppressed after rollback;
- retry statements read the original savepoint image, then RELEASE publishes
  the retry window receipt as the next current source.

Application path: `application-rowvalue-returning-window-current-source-next233.php`
models copied `wp_options` plugin/import cleanup where transient deletions and
queued option updates produce RETURNING streams that must be ranked for a
batched importer without leaking rows from a rolled-back savepoint attempt.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext233Test.php`
  - `1 test files, 82 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next233.php --self-test`
  - `application-rowvalue-returning-window-current-source-next233 self-test passed`

Expected dashboard movement: `phpPass +82`, from `113830` to `113912`. Mapped
upstream coverage remains `634 / 1589`; this is current-source PHP behavior over
already mapped row-value, UPDATE/DELETE RETURNING, savepoint, and window
inventory, not a new manifest-backed upstream row.

Dependency closure: no new support component is needed. The slice reuses native
PHP row-value DML selection, UPDATE/DELETE RETURNING row production, savepoint
row images, and lane-local window ranking over RETURNING rows.

Non-overlap: avoids accepted row-value UPDATE/DELETE RETURNING savepoint
surfaces through next229, including nullable tuple comparison, row-value
`IN (SELECT ...)` release/retry behavior, OR ROLLBACK/FAIL current-source
handling, trigger/view RETURNING, compound SELECT window clusters, JSON table,
WAL/VFS, B-tree, planner, and suite evidence handoffs. The new behavior is the
RETURNING-stream window receipt across yielded, suppressed, and retried
current-source phases.
