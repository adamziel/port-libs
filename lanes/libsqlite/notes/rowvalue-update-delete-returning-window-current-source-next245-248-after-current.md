# Row-Value UPDATE/DELETE RETURNING Window Next245-248 After Current

## Behavior

- Adds focused after-current coverage that validates the prepared row-value `UPDATE`/`DELETE ... RETURNING` current-source candidates for next245, next246, next247, and next248 in order.
- The coverage composes the existing Application examples rather than introducing a new execution surface: next245 yield-ticket admission, next246 filtered release receipts, next247 peer-group exclusion, and next248 resumable publication cursors.
- Application path: copied `wp_options` imports can verify that retry rows remain behind the current-source yield/window barriers until the prepared next245-248 handoff candidates all report their expected current-source status.

## Non-Overlap

- Avoids changing the accepted next245 yield gate, next246 FILTER receipts, next247 EXCLUDE GROUP accounting, and next248 publication cursor behavior.
- Avoids row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, suite-runner, and dashboard/status surfaces.

## Dependency Closure

- No new support component needed; this reuses the existing native PHP row-value UPDATE/DELETE RETURNING window examples and focused tests for next245-248.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext245248AfterCurrentTest.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next245-248-after-current.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext245248AfterCurrentTest.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next245-248-after-current.php`
- `git diff --check`
