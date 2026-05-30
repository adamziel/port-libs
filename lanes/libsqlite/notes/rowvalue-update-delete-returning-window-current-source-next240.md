# Row-Value UPDATE/DELETE RETURNING Peer Windows Next240

## Behavior

- Adds `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext240Plan` for row-value `UPDATE`/`DELETE ... RETURNING` batches that rollback a yielded stream, retry from the savepoint image, and compute peer-group window receipts over each returned row.
- The slice records GROUPS-style peer keys, ranks, dense ranks, `percent_rank`, `cume_dist`, `ntile(2)`, `EXCLUDE CURRENT ROW`, `EXCLUDE TIES`, and excluded-group sums for yielded, suppressed, and retried RETURNING streams.
- Application path: copied `wp_options` import/migration batches can summarize retry progress after rollback/release with peer/exclusion windows without requiring ext/sqlite.

## Non-Overlap

- Avoids accepted next236 current-row frame receipts, next235 stream row numbering, and next233 aggregate window receipts.
- Avoids row-value savepoint retry variants, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, PRAGMA, and encoding clusters.

## Dependency Closure

- No new support component needed; this reuses native PHP row-value UPDATE/DELETE RETURNING execution, savepoint row images, and next236 current-row window metadata.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext240Test.php`
  - `1 test files, 72 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next240.php`
  - exited `0`
