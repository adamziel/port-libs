# rowvalue-update-delete-returning-savepoint-current-source-next177

## Behavior

- Adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext177Plan` for nested savepoint current-source behavior where an outer row-value `UPDATE ... RETURNING` source is preserved, an inner savepoint yields speculative `UPDATE` / `DELETE` / `UPDATE OR REPLACE` `RETURNING` rows, `ROLLBACK TO` the inner savepoint suppresses those inner rows, and the inner batch retries from the preserved inner image before release.
- This is intentionally narrower than accepted next174: next174 releases the inner savepoint into the outer source and then rolls back the outer source. This slice rolls back the inner savepoint itself and proves the outer source remains current for the retry.
- Application smoke: `lanes/libsqlite/examples/application-rowvalue-inner-rollback-current-source-next177.php` models copied `wp_options` import cleanup keeping outer option rewrites while discarding speculative inner transient cleanup and replacement rows.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext177Test.php`
  - `1 test files, 68 assertions, 0 failures`
  - 68 focused PASS lines.

## Dependency Closure

No new support component is needed. The slice reuses lane-local row-value `UPDATE`/`DELETE RETURNING` SQL execution and bounded nested savepoint current-source modeling.

## Non-Overlap

Avoids accepted next174 released-inner-to-outer rollback behavior, accepted next172 yielded single-savepoint rollback behavior, accepted row-value FAIL rollback retries, trigger RETURNING savepoint clusters, and WAL/pager savepoint byte/page-image rollback surfaces.
