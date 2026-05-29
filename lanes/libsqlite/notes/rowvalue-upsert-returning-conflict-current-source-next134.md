# rowvalue-upsert-returning-conflict-current-source-next134

Implemented a bounded current-source row-value UPSERT/RETURNING conflict
extension on `SQLiteRowValueSavepointUpsertCurrentSourceNextPlan`.

- Adds parser/executor support for `DO NOTHING RETURNING`, partial
  `ON CONFLICT (...) WHERE ...` targets, and `DO UPDATE ... WHERE ...` conflict
  filters.
- Skipped `DO NOTHING` and false `DO UPDATE WHERE` conflicts now preserve
  current-source rows, emit no `RETURNING` rows, record skip reasons, and do not
  increment changed-row counts.
- Changed conflict updates still evaluate row-value assignment expressions
  against the current row plus `excluded`, and statement-order yield metadata
  includes both changed and skipped conflict attempts.
- Added a copied `wp_options` example for import batches where stale revisions
  skip, `DO NOTHING` suppresses archived option conflicts, and accepted updates
  produce current-source `RETURNING` rows.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteRowValueSavepointUpsertCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpsertReturningConflictCurrentSourceNext134Test.php
php -l lanes/libsqlite/examples/wordpress-rowvalue-upsert-returning-conflict-current-source-next134.php
No syntax errors detected in changed PHP files

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpsertReturningConflictCurrentSourceNext134Test.php
1 test files, 53 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueSavepointUpsertCurrentSourceNext131Test.php lanes/libsqlite/tests/SQLiteRowValueUpsertReturningConflictCurrentSourceNext134Test.php
2 test files, 109 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-rowvalue-upsert-returning-conflict-current-source-next134.php
wordpress-rowvalue-upsert-returning-conflict-current-source-next134 self-test passed
```

Non-overlap: avoids accepted next131 row-value savepoint rollback, next128
row-value UPDATE RETURNING savepoint conflict, next126 recursive UPSERT
RETURNING, next106 DML trigger RETURNING conflict, and the accepted WAL/VFS,
B-tree, JSON table, planner, and encoding clusters. This slice is specifically
row-value UPSERT conflict-policy current-source behavior for `RETURNING`
streams.

Dependency closure: no new support component is needed. The patch reuses the
native PHP row-value UPSERT/savepoint helper and extends its conflict policy
parser/evaluator.
