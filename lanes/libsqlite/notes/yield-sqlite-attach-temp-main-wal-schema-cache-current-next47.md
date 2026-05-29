# yield-sqlite-attach-temp-main-wal-schema-cache-current-next47

Adds a bounded ATTACH/TEMP/main/WAL schema-cache current/next planner for
prepared statements whose table/index resolution snapshot stays textually the
same while WAL schema cookies and attached database membership move forward.

- `SQLiteAttachWalSchemaCachePlan::snapshot()` captures table/index winners,
  schema cookies, and the reader WAL end frame.
- `currentNext()` compares the snapshot to the next catalog state and reports
  reprepare reasons from ATTACH/DETACH generation changes, schema-cookie
  changes, and WAL end-frame advancement.
- TEMP `wp_options` shadowing remains stable while explicit `main.wp_options`
  reprepares on a main schema-cookie change and newly attached `site` objects
  reprepare on resolution changes.

Focused verification:

```
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachWalSchemaCacheCurrentNext47Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 73 assertions, 0 failures
```

WordPress smoke:

```
php lanes/libsqlite/examples/wordpress-attach-wal-schema-cache-current-next47.php --self-test
wordpress-attach-wal-schema-cache-current-next47 self-test passed
```

Dashboard delta:

- `phpPass`: `17373 -> 17446` for the 73 newly verified focused PASS lines.
- `phpFail`: remains `0`.
- `benchmarkDenominator.mapped`: unchanged; this is lane-local focused PHP
  behavior for an existing ATTACH/WAL/schema-cache surface, not a newly mapped
  upstream Tcl inventory unit.

Non-overlap: this avoids accepted ATTACH temp/VFS open planning, ATTACH/DETACH
SQL lifecycle, attach schema-cache generation invalidation, attach temp/main
shadow-cache current/next35, attach main/temp collation-shadow current/next37,
WAL checkpoint/savepoint/rollback file application, VFS writer/sync/lock work,
B-tree page movement/root collapse/overflow release, JSON table source/cursor
behavior, SELECT SQL text/JOIN/GROUP/subquery/ORDER/LIMIT clusters, and
Unicode GLOB behavior. The new surface is the combined prepared statement
resolution cache plus WAL schema-cookie current/next decision.

Dependency closure: no new support component is needed. The slice reuses
lane-local attached schema catalog snapshots and bounded WAL reader metadata;
it does not require ext/sqlite, upstream caches, live services, or provider
credentials.
