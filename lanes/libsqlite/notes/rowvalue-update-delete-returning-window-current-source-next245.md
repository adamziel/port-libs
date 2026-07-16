# Row-Value UPDATE/DELETE RETURNING Yield Windows Next245

## Behavior

- Adds `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext245Plan` for row-value `UPDATE`/`DELETE ... RETURNING` streams that compute window receipts and require yielded current-source rows to be acknowledged before retry rows are exposed as next source.
- The slice records yield, suppressed-attempt, and retry-release tickets, detects missing or unexpected acknowledgements, and holds next-source exposure until current-source RETURNING window rows are complete.
- Application path: copied `wp_options` import batches can report current-source migration progress and keep retried rows hidden until all yielded row-value RETURNING window rows are acknowledged.

## Non-Overlap

- Avoids accepted next236 current-row window-frame receipts and accepted next242 row-value/window behavior.
- Avoids row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, and upstream-runner surfaces.

## Dependency Closure

- No new support component needed; reuses native PHP row-value UPDATE/DELETE RETURNING execution, savepoint image retry, and next236 current-row window receipts.

## Verification

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext245Plan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext245Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext245Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext245Test.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next245.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next245.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext245Test.php`
  - `1 test files, 62 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next245.php`
  - exited `0`

## Expected Dashboard Movement

- `phpPass`: `124032 -> 124094` from 62 focused PASS lines in the new lane-scoped test.
- `mapped` coverage: unchanged; this is current-source focused PHP behavior over already mapped row-value/window surfaces.
