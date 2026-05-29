# Row-value UPDATE/DELETE RETURNING Savepoint Compound Subquery

Status: focused PHP behavior growth for row-value `UPDATE` / `DELETE ... RETURNING`
where the tuple source is a compound SELECT under a savepoint rollback and retry.

`SQLiteUpdateDeleteReturningSql` accepts bounded row-value `IN (SELECT ... UNION
SELECT ...)`, `UNION ALL`, `INTERSECT`, and `EXCEPT` tuple sources. The
canonical `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan`
composes those compound tuple sources through the existing current-source
savepoint flow: the first attempt yields RETURNING rows, `ROLLBACK TO` restores
the savepoint image, and the retry reads from that restored image before
RELEASE.

WordPress path: `wordpress-rowvalue-compound-subquery-savepoint-current-source.php`
models copied multisite `wp_options` rows where migration metadata is merged
from primary and secondary batches, cleanup candidates are protected with
`EXCEPT`, and retry rows are gated with `INTERSECT` before RETURNING rows are
published.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCompoundSubqueryTest.php
php -l lanes/libsqlite/examples/wordpress-rowvalue-compound-subquery-savepoint-current-source.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCompoundSubqueryTest.php
php lanes/libsqlite/examples/wordpress-rowvalue-compound-subquery-savepoint-current-source.php --self-test
```

Focused result: `1 test files, 68 assertions, 0 failures`.

Expected dashboard movement: no `phpPass` or mapped-coverage change from this
consolidation-only pass. The focused assertions continue covering the same
row-value DML and RETURNING/savepoint behavior under stable unsuffixed names.

Non-overlap: this avoids accepted DISTINCT tuple-source handling, negative
LIMIT/OFFSET, positive ORDER/LIMIT, OR ROLLBACK, trigger/view RETURNING,
WAL/VFS, JSON, planner, encoding, and B-tree clusters. This cleanup only
renames the direct compound SELECT tuple-source savepoint surface.

Dependency closure: no new support component is needed. The slice reuses the
native PHP DML RETURNING executor, savepoint current-source planner, and
row-value comparison primitives.
