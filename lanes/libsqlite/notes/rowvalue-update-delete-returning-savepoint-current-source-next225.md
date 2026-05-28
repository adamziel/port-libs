# Row-Value UPDATE/DELETE RETURNING Savepoint Current Source Next225

## Behavior

Adds native row-value `IN (SELECT DISTINCT ...)` tuple-source handling for bounded `UPDATE`/`DELETE ... RETURNING` execution. Duplicate staging rows in a copied `wp_optionmeta` source now collapse before `LIMIT` selection, so a savepoint attempt can yield the attempted `RETURNING` stream, roll back to the savepoint image, and retry from the restored current source without duplicate tuple side effects.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext225Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext225Test.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-distinct-savepoint-current-source-next225.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext225Test.php`
  - `1 test files, 65 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext219Test.php`
  - `1 test files, 64 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-rowvalue-distinct-savepoint-current-source-next225.php`
  - WordPress smoke passed and reported `attempt_selected` `[7, 8]`, `retry_selected` `[9, 8]`, and final option ids `[7, 8, 9]`.

## Non-Overlap

This slice avoids accepted next219 negative `LIMIT/OFFSET`, next213 positive ordered `LIMIT`, next212 plain row-value subqueries, and the accepted trigger, WAL/VFS, JSON, planner, and B-tree clusters. It specifically covers `SELECT DISTINCT` tuple-source collapse before row-value UPDATE/DELETE RETURNING savepoint rollback/retry.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP row-value UPDATE/DELETE RETURNING executor and savepoint current-source plan.
