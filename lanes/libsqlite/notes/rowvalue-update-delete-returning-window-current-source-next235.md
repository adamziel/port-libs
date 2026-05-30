# Row-value UPDATE/DELETE RETURNING Window Current Source Next235

Status: focused PHP behavior growth for row-value `UPDATE`/`DELETE`
`RETURNING` streams when an attempted current-source savepoint batch is rolled
back and a retry batch yields the next visible rows.

This slice adds `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext235Plan`.
It reuses the native row-value UPDATE/DELETE RETURNING executor and annotates
discarded attempt rows plus yielded retry rows with deterministic window
metadata: stream, phase, action partition, statement ordinal, row number, and
partition row number. The Application path models copied `wp_options` migration
rows where attempted `RETURNING` rows are visible only before `ROLLBACK TO`,
then retry UPDATE/DELETE RETURNING rows become the committed current source.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext235Test.php`
  - `1 test files, 71 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next235.php --self-test`
  - `application-rowvalue-returning-window-current-source-next235 self-test passed`

Expected dashboard movement: `phpPass +71`, from `116027` to `116098`.
`benchmarkDenominator.mapped` remains `638 / 1589`; this is current-source PHP
behavior over already mapped row-value UPDATE/DELETE RETURNING, savepoint, and
window inventory rather than a newly hydrated upstream row.

Dependency closure: no new support component is needed. The slice reuses
lane-local `SQLiteUpdateDeleteReturningSql`,
`SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext212Plan`, and
bounded row-array savepoint images.

Non-overlap: avoids accepted next231 compound subquery row-value behavior,
accepted next232 row-value current-source behavior, next229 `LIMIT -1 OFFSET`
tuple sources, trigger/RETURNING recursive view work, JSON table, WAL/VFS,
B-tree, planner, PRAGMA, and encoding clusters. The new surface is RETURNING
stream window metadata across discarded attempt rows and yielded retry rows at
the current-source savepoint boundary.

Next task: continue with broader SQL executor/planner correctness or another
non-overlapping row-value executor gap; avoid adding another savepoint wrapper
unless it proves a distinct upstream behavior with focused assertion growth.
