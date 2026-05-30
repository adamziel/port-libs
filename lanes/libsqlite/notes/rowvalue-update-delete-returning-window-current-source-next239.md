# Row-Value UPDATE/DELETE RETURNING Statement Windows Next239

## Behavior

- Adds `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext239Plan`.
- The slice keeps accepted next233/next236 retry behavior but adds statement-partitioned RETURNING windows after rollback/release.
- Retry windows now record `ntile(2)`, percent-rank, cume-dist, first/last values, and `ROWS BETWEEN 1 PRECEDING AND 1 FOLLOWING EXCLUDE CURRENT ROW` neighbor ids.
- The release seal proves suppressed attempt rows stay outside the released current-source retry windows.

## Application Path

Copied `wp_options` import batches can rollback a row-value `UPDATE`/`DELETE ... RETURNING` attempt, retry from the savepoint image, and emit statement-local migration progress windows without mixing rolled-back rows into the released stream.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext239Test.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `1 test files, 71 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next239.php`
  - exited `0`
- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext239Plan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext239Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext239Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext239Test.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next239.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next239.php`
- `git diff --check -- lanes/libsqlite`
  - exited `0`

## Non-Overlap

Avoids accepted next233 row-number/dense-rank/count/sum windows, next236 current-row frames, row-value UPSERT, trigger RETURNING, JSON table, planner, WAL/VFS, B-tree, PRAGMA, and encoding clusters. The new surface is statement-partitioned retry release windows and EXCLUDE CURRENT ROW neighbor receipts after rollback.

## Dependency Closure

No new support component is needed; this reuses native PHP row-value UPDATE/DELETE RETURNING execution, savepoint current-source images, and lane-local window row metadata.
