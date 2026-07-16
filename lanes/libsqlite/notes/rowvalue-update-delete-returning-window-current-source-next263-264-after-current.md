# rowvalue-update-delete-returning-window-current-source-next263-264-after-current

Status: focused current-source behavior growth for the row-value `UPDATE`/`DELETE RETURNING` window stream after existing next261-next262 coverage.

Adds:

- `executeNext263()` peer-group restart checkpoints over next262 admitted peer groups.
- `executeNext264()` final receipt completeness over next263 checkpoints before handoff completion.
- Application smoke examples for next263, next264, and a combined next261-264 after-current wrapper.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next263.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next264.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next261-264-after-current.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext263264AfterCurrentTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext263264AfterCurrentTest.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next263.php --self-test`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next264.php --self-test`

Expected dashboard movement: focused PHP pass count only. `benchmarkDenominator.mapped` remains unchanged because this composes already mapped row-value, UPDATE/DELETE RETURNING, source window, and peer-group current-source behavior.

Non-overlap: avoids broad suite evidence, next262 peer admission, next260 boundary receipts, row-value savepoint-only variants, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and dashboard/status surfaces.
