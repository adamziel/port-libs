# Row-value UPDATE/DELETE RETURNING savepoint current-source next231

Status: focused PHP behavior growth for row-value `UPDATE` / `DELETE ... RETURNING`
where the tuple source is a compound SELECT under a savepoint rollback and retry.

`SQLiteUpdateDeleteReturningSql` now accepts bounded row-value `IN (SELECT ... UNION
SELECT ...)`, `UNION ALL`, `INTERSECT`, and `EXCEPT` tuple sources. The new
`SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext231Plan` composes
those compound tuple sources through the existing current-source savepoint flow:
the first attempt yields RETURNING rows, `ROLLBACK TO` restores the savepoint
image, and the retry reads from that restored image before RELEASE.

WordPress path: `wordpress-rowvalue-compound-subquery-current-source-next231.php`
models copied multisite `wp_options` rows where migration metadata is merged
from primary and secondary batches, cleanup candidates are protected with
`EXCEPT`, and retry rows are gated with `INTERSECT` before RETURNING rows are
published.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext231Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext231Test.php
php -l lanes/libsqlite/examples/wordpress-rowvalue-compound-subquery-current-source-next231.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext231Test.php
php lanes/libsqlite/examples/wordpress-rowvalue-compound-subquery-current-source-next231.php --self-test
```

Focused result: `1 test files, 68 assertions, 0 failures`.

Expected dashboard movement: `phpPass +68` from the new focused PASS lines.
Mapped upstream coverage is unchanged; this reuses the existing row-value DML
and RETURNING/savepoint inventory rather than adding a newly hydrated upstream
manifest row.

Non-overlap: this avoids accepted next226 DISTINCT tuple-source handling,
next219 negative LIMIT/OFFSET, next213 positive ORDER/LIMIT, next217 OR
ROLLBACK, trigger/view RETURNING, WAL/VFS, JSON, planner, encoding, and B-tree
clusters. The new behavior is specifically compound SELECT tuple sources for
row-value UPDATE/DELETE RETURNING at the current/next savepoint boundary.

Dependency closure: no new support component is needed. The slice reuses the
native PHP DML RETURNING executor, savepoint current-source planner, and
row-value comparison primitives.
