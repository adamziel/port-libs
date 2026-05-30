# B-tree pointer-map vacuum apply current-next66

This slice extends `SQLiteOverflowVacuumTruncatePlan` beyond the accepted
current/next transition diagnostics. It now exposes the materialized
post-vacuum database, raw bytes, page images, and an apply summary after
overflow pages are released to the freelist and incremental vacuum truncates
the tail.

Focused evidence:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePointerMapVacuumApplyCurrentNext66Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
57 PASS lines
1 test files, 279 assertions, 0 failures
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-btree-pointermap-vacuum-apply-current-next66.php
```

Scenario: copied `wp_options` overflow delete plus incremental vacuum
materializes a shorter database image, keeps surviving released overflow pages
on the freelist, preserves their free-page pointer-map entries, and omits
truncated tail pages from the next database bytes without `ext/sqlite`.

Non-overlap: this avoids accepted batch64 pointer-map vacuum transition
diagnostics, pointer-map truncate-vacuum current-next54, overflow freelist
release, bulk overflow freeblocks, page relocation/root collapse, PRAGMA
integrity pointer-map pagination, and VFS/WAL/SELECT/JSON/Unicode clusters. The
new behavior is the apply/materialized byte surface for the next database image
after those current/next vacuum decisions.

Dependency closure: no new support component is needed. The patch reuses the
existing native PHP SQLite database image, freelist, pointer-map, and overflow
vacuum primitives.
