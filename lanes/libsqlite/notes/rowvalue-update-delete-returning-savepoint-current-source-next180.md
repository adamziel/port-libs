# rowvalue-update-delete-returning-savepoint-current-source-next180

## Behavior

- Adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext180Plan` for nested savepoint current-source behavior where `UPDATE OR IGNORE` with row-value assignment selects a conflicting row, restores that row from the statement image, and yields no `RETURNING` row for the ignored mutation.
- The same inner savepoint then yields a normal row-value `UPDATE ... RETURNING`, performs speculative `DELETE` / `UPDATE OR REPLACE` work, rolls back to the inner image, and retries from the preserved current source before release.
- Application smoke: `lanes/libsqlite/examples/application-rowvalue-ignore-savepoint-current-source-next180.php` models a copied `wp_options` import where an ignored duplicate `(blog_id, option_name)` update should not appear in `RETURNING`, while later transient cleanup rows are discarded by inner `ROLLBACK TO` and retried.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext180Test.php`
  - `1 test files, 75 assertions, 0 failures`
  - 75 focused PASS lines.
- `php lanes/libsqlite/examples/application-rowvalue-ignore-savepoint-current-source-next180.php --self-test`
  - `application-rowvalue-ignore-savepoint-current-source-next180 self-test passed`
- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext180Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext180Test.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-ignore-savepoint-current-source-next180.php`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. The slice reuses lane-local row-value `UPDATE OR IGNORE` conflict handling, `DELETE RETURNING`, `UPDATE OR REPLACE` conflict deletion, and bounded nested savepoint current-source modeling.

## Non-Overlap

Avoids accepted next177 inner rollback yielding behavior by proving the distinct `OR IGNORE` rule that ignored row-value conflicts produce no `RETURNING` row while preserving statement current source. Also avoids accepted next174 released-inner rollback, next172 yielded single-savepoint rollback, next143 conflict retry, trigger RETURNING savepoint clusters, and WAL/pager savepoint byte/page-image rollback surfaces.
