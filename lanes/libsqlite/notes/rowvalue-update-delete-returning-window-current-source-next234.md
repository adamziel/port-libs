# rowvalue-update-delete-returning-window-current-source-next234

Status: focused PHP behavior growth for row-value UPDATE/DELETE RETURNING
current-source execution.

This slice adds `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext234Plan`.
It runs an attempted row-value UPDATE/DELETE RETURNING batch, rolls back to the
savepoint image, retries a row-value UPDATE/DELETE RETURNING batch from that
current source, and materializes a bounded window tape over the yielded
RETURNING stream. The window tape partitions by `blog_id`, orders by
`option_id`, and records `row_number`, `dense_rank`, partition size, lag/lead
option names, and one-row-preceding/current/one-row-following frame rowids.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext234Test.php`
- Result: `1 test files, 73 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next234.php --self-test`
- Result: `application-rowvalue-returning-window-current-source-next234 self-test passed`
- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext234Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext234Test.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next234.php`
- Result: all three reported no syntax errors.

Expected dashboard movement: `phpPass +73` from the new focused test file.
`benchmarkDenominator.mapped` remains `637 / 1589`; this is additional PHP
current-source behavior over already mapped row-value UPDATE/DELETE RETURNING
and window inventory, not a new upstream manifest row.

Non-overlap: avoids accepted next230-next231 row-value savepoint
rollback/release, next219 negative LIMIT tuple sources, next206 released-inner
retry, compound/window recursive LIMIT, trigger RETURNING, JSON table, planner,
WAL/VFS, B-tree, PRAGMA, and encoding clusters. The new surface is specifically
window materialization over the retry-yielded row-value RETURNING stream after
savepoint rollback.

Dependency closure: no new support component is needed; the patch reuses the
native row-array UPDATE/DELETE RETURNING executor and adds bounded
current-source RETURNING window materialization.

Next task: continue with a non-overlapping SQL executor/planner gap or row-value
RETURNING behavior that adds focused assertions without repeating next234
window materialization.
