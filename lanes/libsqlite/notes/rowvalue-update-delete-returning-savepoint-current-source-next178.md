# rowvalue-update-delete-returning-savepoint-current-source-next178

Status: focused PHP behavior growth for row-value `UPDATE`/`DELETE`
`RETURNING` streams when an `UPDATE OR ROLLBACK` conflict fires inside an
active savepoint.

This slice adds
`SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext178Plan`. It
models the SQLite conflict boundary where `OR ROLLBACK` rolls back the active
transaction, not just the innermost savepoint. Prior outer-statement and
savepoint `RETURNING` rows are discarded, the retry source is restored to the
transaction image, and the next row-value `UPDATE`/`DELETE RETURNING` statements
read from that restored current source.

Focused verification:

```
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext178Test.php
```

Application smoke:

```
php lanes/libsqlite/examples/application-rowvalue-rollback-transaction-current-source-next178.php
```

Expected dashboard delta: `phpPass` increases by 61 focused PASS lines. Mapped
upstream coverage remains unchanged; this is a current-source executor behavior
slice over existing mapped row-value/update/delete/RETURNING/savepoint
inventory.

Non-overlap: avoids accepted row-value FAIL retry next161, nested savepoint
RETURNING next175, row-value delete/update savepoint next135/144/156, trigger
RETURNING, WAL/pager savepoint byte and VFS rollback application, B-tree/JSON/
PRAGMA/encoding planner clusters, and accepted current-source next175 surfaces.
The new surface is specifically `OR ROLLBACK` transaction-scope restoration and
RETURNING stream discard before retry.

Dependency closure: no new support component is needed. The slice reuses the
lane-local native PHP row-value UPDATE/DELETE RETURNING executor and bounded
transaction/savepoint current-source modeling.
