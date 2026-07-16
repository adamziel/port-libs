# Row-value nested savepoint RETURNING

Status: consolidated row-value `UPDATE` / `DELETE` `RETURNING` coverage across
nested savepoint release and outer `ROLLBACK TO`.

`SQLiteRowValueNestedSavepointReturningPlan` models the upstream SQLite
behavior where `RELEASE` of an inner savepoint
merges its row-value `UPDATE RETURNING` and `DELETE RETURNING` effects into the
outer savepoint, but a later `ROLLBACK TO` the outer savepoint discards both the
released inner row stream and an outer DELETE stream. Retry statements then read
from the restored outer savepoint image/current source.

Application smoke:
`application-rowvalue-nested-savepoint-returning.php`
models a copied `wp_options` plugin import with an inner plugin cleanup batch,
outer failure recovery, and retry cleanup from the restored current source.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteRowValueNestedSavepointReturningPlan.php
php -l lanes/libsqlite/tests/SQLiteRowValueNestedSavepointReturningTest.php
php -l lanes/libsqlite/examples/application-rowvalue-nested-savepoint-returning.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueNestedSavepointReturningTest.php
php lanes/libsqlite/examples/application-rowvalue-nested-savepoint-returning.php --self-test
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 76 assertions, 0 failures`, adding 76 focused
PASS lines for this lane patch.

Dependency closure: no new support component is needed. The slice reuses the
lane-local row-value DML parser/executor, RETURNING projection, unique
constraint handling, and savepoint current-source planning.

Non-overlap: consolidation-only row-value savepoint cleanup; no trigger
RETURNING, WAL/pager/VFS, B-tree, JSON,
encoding, PRAGMA, planner, and suite-runner surfaces. The new assertion surface
is nested savepoint `RELEASE` followed by outer `ROLLBACK TO`, proving released
inner row-value RETURNING effects are not durable after the outer rollback.
