# Row-Value UPDATE/DELETE RETURNING Window Current Source Next232

## Slice

- Adds `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext232Plan` for bounded row-value `UPDATE`/`DELETE ... RETURNING` retry batches that derive window-style row numbers from the released current source.
- The focused test uses copied `wp_options` and `wp_import_targets` rows: a yield pass mutates/deletes rows, an attempted pass is suppressed by rollback-to-savepoint, and the retry pass rereads the savepoint image before release.
- Adds a Application smoke at `examples/application-rowvalue-window-current-source-next232.php`.

## Non-Overlap

This avoids accepted next229 row-value subquery retry image coverage by adding retry RETURNING row-number and partition-row-number evidence after release. It also avoids next226 DISTINCT subquery coverage, next205 release-current-source coverage, window row-value UPSERT, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree clusters.

## Dependency Closure

No new support component is needed. The slice reuses native PHP row-value UPDATE/DELETE RETURNING subquery dispatch, savepoint row images, and bounded PHP row numbering over retry RETURNING rows.

## Verification

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext232Plan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext232Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext232Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext232Test.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-window-current-source-next232.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-rowvalue-window-current-source-next232.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext232Test.php`
  - `1 test files, 70 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-rowvalue-window-current-source-next232.php`
  - JSON smoke returned `status=rowvalue-update-delete-returning-window-current-source-next232`, `retryIds=[4,5,6,9]`, and `retryRowNumbers=[1,2,3,4]`.
- `git diff --check -- lanes/libsqlite`
  - passed with no output
