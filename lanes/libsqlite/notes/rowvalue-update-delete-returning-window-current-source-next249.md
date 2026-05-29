# Row-Value UPDATE/DELETE RETURNING Window Resume Next249

## Behavior

- Adds `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext249Plan` for row-value `UPDATE`/`DELETE ... RETURNING` streams that yield current-source window rows in acknowledgement chunks before retry rows are exposed.
- The slice records window sequence tokens, lag/lead RETURNING tickets, chunk resume tokens, and a held/resumed next-source gate for missing or unexpected current-source acknowledgements.
- WordPress path: copied `wp_options` imports can checkpoint row-value RETURNING windows in bounded chunks and resume retried option rows only after the current-source yield rows are complete.

## Non-Overlap

- Avoids accepted next245 yield-ticket admission and next236 current-row window-frame receipts.
- Avoids accepted next242 row-value/window behavior, row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, and upstream-runner surfaces.

## Dependency Closure

- No new support component needed; reuses native PHP row-value UPDATE/DELETE RETURNING execution, next245 yield-ticket gates, and current-row window receipts.

## Verification

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext249Plan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext249Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueChunkedYieldResumeWindowTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRowValueChunkedYieldResumeWindowTest.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-chunked-yield-resume-window.php`
  - `No syntax errors detected in lanes/libsqlite/examples/wordpress-rowvalue-chunked-yield-resume-window.php`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueChunkedYieldResumeWindowTest.php`
  - `1 test files, 60 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-rowvalue-chunked-yield-resume-window.php`
  - exited `0`
- `git diff --check -- lanes/libsqlite`
  - exited `0`

## Expected Dashboard Movement

- `phpPass`: `126252 -> 126312` from 60 focused PASS lines in the new lane-scoped test.
- `mapped` coverage: unchanged; this is current-source focused PHP behavior over already mapped row-value/window surfaces.
