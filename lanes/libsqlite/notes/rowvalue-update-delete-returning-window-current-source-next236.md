# Row-Value UPDATE/DELETE RETURNING Current-Row Windows Next236

## Behavior

- Adds `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext236Plan` for row-value `UPDATE`/`DELETE ... RETURNING` batches that rollback a yielded stream, retry from the savepoint image, and compute current-row window-frame receipts over each returned row.
- The slice records current-row values, one-row frame counts, running bytes, following bytes, lag/lead neighbors, and frame tokens for yielded, suppressed, and retried RETURNING streams.
- Application path: copied `wp_options` import/migration batches can summarize retry progress after rollback/release without requiring ext/sqlite.

## Non-Overlap

- Avoids accepted next232 simple retry row numbering and accepted next233 row_number/dense_rank/count/sum windows.
- Avoids row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, and upstream-runner surfaces.

## Dependency Closure

- No new support component needed; the slice reuses native PHP row-value UPDATE/DELETE RETURNING execution, savepoint row images, and window metadata from next233 while adding current-row frame receipts.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext236Test.php`
  - `1 test files, 68 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext236Plan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext236Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext236Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext236Test.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next236.php`
  - exited `0`
- `git diff --check -- lanes/libsqlite`
  - exited `0`
