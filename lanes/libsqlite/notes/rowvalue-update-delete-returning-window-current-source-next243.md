# Row-value UPDATE/DELETE RETURNING window current-source next243

Status: focused PHP behavior growth for row-value `UPDATE` / `DELETE`
`RETURNING` retry windows after savepoint rollback and current-source release.

This slice adds `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext243Plan`.
It layers row-value tuple window receipts over the accepted next239
statement-partitioned retry windows:

- retry `UPDATE` and `DELETE` returning partitions expose tuple keys ordered by
  `(bytes DESC, rowid)`;
- tuple frames preserve lag/current/lead membership across rollback/retry;
- peer groups remain visible when two retry rows share the same tuple-order
  byte value;
- the release boundary records the final current-source tuple window IDs and
  digest.

WordPress smoke:
`wordpress-rowvalue-returning-window-current-source-next243.php` models copied
`wp_options` import cleanup where a failed attempt deletes a transient row,
`ROLLBACK TO` restores the current source, and retry `UPDATE`/`DELETE
RETURNING` rows are released with tuple-window frame receipts.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext243Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 60 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next243.php --self-test
wordpress-rowvalue-returning-window-current-source-next243 self-test passed
```

Expected dashboard movement: `phpPass +60` from the new focused test file.
Mapped upstream coverage remains unchanged; this is current-source PHP behavior
over already mapped row-value, UPDATE/DELETE RETURNING, savepoint, and window
inventory.

Non-overlap: avoids accepted next239 statement-partitioned retry windows,
next238 source fences, next236 current-row frames, next219 negative
`LIMIT -1 OFFSET` tuple sources, row-value UPSERT, trigger RETURNING, JSON
table, planner, WAL/VFS, B-tree, PRAGMA, and encoding clusters. The added
surface is tuple-key peer/frame accounting at the retry current-source release
boundary.

Dependency closure: no new support component is needed; this reuses native
row-value UPDATE/DELETE RETURNING execution, savepoint current-source images,
and lane-local window frame rows.
