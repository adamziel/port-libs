# Row-value UPDATE/DELETE RETURNING savepoint current-source next182

This slice adds a focused current-source model for row-value `UPDATE` /
`DELETE ... RETURNING` streams when an inner savepoint is released into an
outer savepoint and a later `ROLLBACK TO` the outer savepoint suppresses the
already released inner `RETURNING` rows.

Behavior covered:

- outer row-value `UPDATE ... RETURNING` rows are produced before the inner
  savepoint starts;
- inner `DELETE ... RETURNING` and `UPDATE OR REPLACE ... RETURNING` rows are
  released into the outer savepoint image;
- `ROLLBACK TO` the outer savepoint restores the original current source,
  including rows deleted by the released inner work and rows removed by
  `OR REPLACE`;
- retry statements start from the outer savepoint image and yield only the
  retry `RETURNING` rows;
- suppressed outer and released-inner rows remain available as invalidated
  diagnostics, but are not part of the retry stream.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext182Test.php
1 test files, 66 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-rowvalue-release-inner-rollback-current-source-next182.php --self-test
application-rowvalue-release-inner-rollback-current-source-next182 self-test passed
```

Non-overlap: this avoids accepted next180 row-value `OR IGNORE` inner
rollback-to retry behavior by covering the distinct case where an inner
savepoint has already been released and a later outer rollback invalidates
both outer and released-inner `RETURNING` streams. It also avoids accepted
trigger RETURNING, pager/WAL savepoint, B-tree, JSON, encoding, VFS, and
planner clusters.

Dependency closure: no new support component is needed; the behavior composes
existing native PHP row-value DML execution, unique-conflict handling, and
savepoint current-source modeling.
