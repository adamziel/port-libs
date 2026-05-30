# rowvalue-update-delete-returning-savepoint-current-source-next218

Status: focused PHP behavior growth for row-value UPDATE/DELETE RETURNING
savepoint `ROLLBACK TO` current-source handling.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext218Plan`
and focused coverage for a copied Application `wp_options` import where:

- row-value UPDATE/DELETE RETURNING statements run inside an active savepoint;
- later attempted UPDATE/DELETE RETURNING rows are recorded as suppressed when
  `ROLLBACK TO` restores the savepoint image;
- retry UPDATE/DELETE RETURNING statements read the restored savepoint image,
  not the attempted current source;
- deleted transient rows and updated option values from the attempted stream do
  not leak into the retry source.

Application smoke:
`application-rowvalue-rollback-to-current-source-next218.php` models copied
options-table cleanup where speculative option rewrites are rolled back and the
retry stream starts from the original savepoint image.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext218Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext218Test.php
php -l lanes/libsqlite/examples/application-rowvalue-rollback-to-current-source-next218.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext218Test.php
php lanes/libsqlite/examples/application-rowvalue-rollback-to-current-source-next218.php
git diff --check -- lanes/libsqlite
```

Focused test output:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 64 assertions, 0 failures
```

Dashboard delta: `phpPass` moves from accepted batch193 `104546` to `104610`
from 64 newly passing focused PASS lines. Mapped upstream coverage remains
`623 / 1589`.

Non-overlap: avoids accepted next211 row-value OR IGNORE/savepoint behavior,
next200 statement ABORT preservation, next205 RELEASE current-source
admission, trigger RETURNING, WAL/VFS, JSON table, planner, and B-tree
clusters. The new surface is explicit `ROLLBACK TO` savepoint image
restoration after successful row-value UPDATE/DELETE RETURNING attempts.

Dependency closure: no new support component is needed. This reuses native PHP
row-value UPDATE/DELETE RETURNING execution and row-array savepoint images.

Next task: continue with a non-overlapping SQL executor/planner, WAL/pager,
B-tree, JSON planner, encoding/collation, or suite blocker gap.
