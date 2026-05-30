# Row-value update/delete RETURNING savepoint current-source next183

Status: focused PHP behavior growth for nested row-value UPDATE/DELETE
RETURNING inside savepoints when the outer current source is a DELETE.

This slice adds
`SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext183Plan`. It
models a copied `wp_options` import where an outer savepoint first deletes
transient rows with `DELETE ... RETURNING`, an inner savepoint yields
row-value UPDATE and DELETE RETURNING rows, `ROLLBACK TO` discards the inner
stream, and retry statements read from the post-delete outer current source
rather than the original table image.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext183Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 72 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-rowvalue-delete-savepoint-current-source-next183.php --self-test
application-rowvalue-delete-savepoint-current-source-next183 self-test passed
```

Expected dashboard delta: `phpPass` moves from `86003` to `86075` from 72
new focused assertions. Mapped upstream coverage remains `614 / 1589`; this
is focused current-source PHP behavior over already mapped row-value,
RETURNING, and savepoint inventory.

Non-overlap: avoids accepted next180 outer UPDATE/inner rollback behavior,
next178 OR ROLLBACK transaction rollback, next173/next161 OR FAIL partial
statement rollback, next172 yielded stream rollback, next130/next138 conflict
RETURNING, trigger RETURNING, WAL/pager savepoint, B-tree, JSON, encoding, and
planner clusters. The new surface is outer DELETE current-source preservation
across an inner row-value UPDATE/DELETE RETURNING rollback and retry.

Dependency closure: no new support component is needed. The slice reuses the
native PHP `SQLiteUpdateDeleteReturningSql` row-value executor and bounded
savepoint current-source plan structure.

Next task: continue with a different SQL executor/planner gap or storage
application edge; avoid another row-value savepoint variant unless it applies
a distinct current-source transition.
