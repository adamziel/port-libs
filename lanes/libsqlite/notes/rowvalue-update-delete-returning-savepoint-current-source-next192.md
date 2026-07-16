# rowvalue-update-delete-returning-savepoint-current-source-next192

Status: focused PHP behavior growth for row-value UPDATE/DELETE RETURNING
current-source savepoint handling.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext192Plan`
and focused coverage for a Application-style copied `wp_options` cleanup where:

- outer row-value UPDATE work is preserved as the current source for an inner
  savepoint;
- prior inner DELETE/UPDATE RETURNING changes remain visible after a later
  `UPDATE OR ABORT` statement hits a unique `(blog_id, option_name)` conflict;
- the aborting statement suppresses its attempted RETURNING stream and rolls
  back only to the statement start, not to the inner savepoint image;
- retry UPDATE/DELETE RETURNING statements read from the preserved inner
  current source and then release both savepoints.

Application smoke:
`application-rowvalue-abort-savepoint-current-source-next192.php` models copied
options-table cleanup where transient deletion and orphaned-cache staging are
kept after an aborting duplicate option-name rewrite, while retry cleanup
yields the final current-source stream.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext192Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext192Test.php
php -l lanes/libsqlite/examples/application-rowvalue-abort-savepoint-current-source-next192.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext192Test.php
php lanes/libsqlite/examples/application-rowvalue-abort-savepoint-current-source-next192.php
git diff --check -- lanes/libsqlite
```

Focused test output:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 72 assertions, 0 failures
```

Dashboard delta: `phpPass` moves from `92140` to `92212` from 72 newly passing
focused PASS lines. Mapped upstream coverage remains `617 / 1589`.

Non-overlap: avoids accepted rowvalue next156/158/161/172/178/180/189 rollback,
OR FAIL, OR ROLLBACK, OR IGNORE, OR REPLACE, NOT BETWEEN/VALUES, and yielded
inner rollback surfaces. The new surface is row-value `UPDATE OR ABORT`
statement-level rollback under an active inner savepoint, with prior inner
current-source changes preserved for retry DELETE/UPDATE RETURNING.

Dependency closure: no new support component is needed. This reuses the native
PHP UPDATE/DELETE RETURNING executor and existing row-array savepoint modeling.

Next task: continue with a non-overlapping SQL executor/planner, WAL/pager, or
B-tree closure gap; avoid another rowvalue savepoint variant unless it removes
a named upstream runner blocker or adds materially distinct assertion growth.
