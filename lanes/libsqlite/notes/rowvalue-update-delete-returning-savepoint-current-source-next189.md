# rowvalue-update-delete-returning-savepoint-current-source-next189

Status: focused PHP behavior growth for row-value UPDATE/DELETE RETURNING
current-source savepoint handling.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext189Plan`
and focused coverage for a Application-style copied `wp_options` cleanup where:

- an outer row-value `NOT BETWEEN` UPDATE yields `RETURNING *` rows and remains
  the current source after an inner rollback;
- an inner `UPDATE OR IGNORE` yields no rows while a following DELETE uses
  row-value `NOT IN (VALUES ...)` plus a row-value `NOT BETWEEN` RETURNING
  expression;
- `ROLLBACK TO` suppresses the inner DELETE stream and retry statements read
  from the preserved post-outer current source.

Application smoke:
`application-rowvalue-not-between-savepoint-current-source-next189.php` models a
copied options-table cleanup where outer multisite URL rows are staged,
transient cleanup inside an inner savepoint is rolled back, and the retry
keeps the staged outer rows while yielding only the retry stream.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext189Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext189Test.php
php -l lanes/libsqlite/examples/application-rowvalue-not-between-savepoint-current-source-next189.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext189Test.php
php lanes/libsqlite/examples/application-rowvalue-not-between-savepoint-current-source-next189.php --self-test
git diff --check -- lanes/libsqlite
```

Focused test output:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 70 assertions, 0 failures
```

Dashboard delta: `phpPass` moves from `90084` to `90154` from 70 newly passing
focused PASS lines. Mapped upstream coverage remains `616 / 1589`.

Non-overlap: avoids accepted rowvalue next176/177/180/182/183/185 rollback and
OR FAIL/OR REPLACE surfaces, accepted row-value nullable equality, accepted
UPDATE/DELETE LIMIT/OFFSET, trigger RETURNING, and pager/WAL savepoint
current-source clusters. The new surface is the row-value `NOT BETWEEN`,
`NOT IN (VALUES ...)`, and `RETURNING *` stream composition across an inner
savepoint rollback and retry.

Dependency closure: no new support component is needed. This reuses the native
PHP UPDATE/DELETE RETURNING executor and existing row-array savepoint modeling.

Next task: continue with a non-overlapping SQL executor/planner or storage
closure gap; avoid another rowvalue savepoint variant unless it adds a distinct
current-source behavior or upstream blocker removal.
