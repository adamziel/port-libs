# rowvalue-update-delete-returning-conflict-current-source-next130

Adds a current-source fix for `UPDATE OR REPLACE/IGNORE ... SET (a,b)=... RETURNING`
when row-value assignments create unique-key conflicts. The bounded executor now
checks conflicts sequentially against the statement-current rowset instead of the
all-at-once post-update image, so later selected rows can replace earlier selected
rows while earlier successful updates still produce `RETURNING` rows.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
No syntax errors detected in lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningConflictCurrentSourceNext130Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningConflictCurrentSourceNext130Test.php
php -l lanes/libsqlite/examples/application-rowvalue-update-delete-returning-conflict-current-source-next130.php
No syntax errors detected in lanes/libsqlite/examples/application-rowvalue-update-delete-returning-conflict-current-source-next130.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningConflictCurrentSourceNext130Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 50 assertions, 0 failures
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpdateDeleteReturningSqlTest.php lanes/libsqlite/tests/SQLiteUpdateDeleteRowValueReturningCurrentSourceNext117Test.php lanes/libsqlite/tests/SQLiteUpdateDeleteReturningRowValueCurrentSourceNext125Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningConflictCurrentSourceNext130Test.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 185 assertions, 0 failures
php lanes/libsqlite/examples/application-rowvalue-update-delete-returning-conflict-current-source-next130.php --self-test
application-rowvalue-update-delete-returning-conflict-current-source-next130 self-test passed
git diff --check -- lanes/libsqlite
```

Non-overlap: this avoids accepted DML trigger RETURNING conflict next106,
row-value RETURNING next117/next125/next126/next128, UPDATE FROM conflict,
recursive trigger RETURNING, WAL/pager/VFS, B-tree, JSON, PRAGMA, encoding, and
suite-runner surfaces. The new behavior is specifically parser-level
`UPDATE OR REPLACE/IGNORE` current-source conflict handling after row-value
assignment and before `RETURNING` projection.

Dependency closure: no new support component is needed. This reuses the existing
native PHP row-array `UPDATE/DELETE RETURNING` executor.
