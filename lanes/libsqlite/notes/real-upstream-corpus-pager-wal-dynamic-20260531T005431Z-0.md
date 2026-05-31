# real-upstream-corpus-pager-wal-dynamic-20260531T005431Z-0

Base accepted HEAD: `452a6f6fbb9dca50b40370a18b13b7d77ca03385`

Added native WAL hook/autocheckpoint behavior backed by the hydrated upstream
SQLite file:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walhook.test`

Upstream sections ported:

- `walhook-1.1`: creating a WAL database fires the hook for `main` at frame 3.
- `walhook-1.2`: a later insert fires the hook at frame 5.
- `walhook-1.3` through `walhook-1.5`: hook callbacks may checkpoint from the
  same or a second connection after commit publication.
- `walhook-2.1` through `walhook-2.3`: default and configured
  `wal_autocheckpoint` thresholds.
- `walhook-2.4` through `walhook-2.9`: transactions append until the log reaches
  the threshold, then checkpoint events are observable.

Behavior added:

- `SQLiteWalHookPlan::commitHookEvents()` derives sqlite3_wal_hook-style event
  rows from parsed native `SQLiteWal` committed transaction boundaries.
- `SQLiteWalHookPlan::autocheckpointEvents()` records threshold-driven
  checkpoint events while preserving the existing WAL checkpoint result shape.
- `SQLiteRealUpstreamPagerWalHookDynamicTest.php` adds 1,000 dynamic upstream
  corpus cases plus provenance and invalid-input guards.

Focused verification:

```text
php -l lanes/libsqlite/src/SQLiteWalHookPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteWalHookPlan.php

php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalHookDynamicTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalHookDynamicTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalHookDynamicTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 16005 assertions, 0 failures
```

Expected movement: +16,005 focused behavior assertions and +1,002 focused
TestRunner PASS lines if accepted. Mapped denominator coverage remains complete
at `1589 / 1589`.

Dependency closure: no new support component is needed. The slice reuses
existing native `SQLiteWal`, `SQLiteWalHeader`, transaction-boundary, and
checkpoint helpers.

Non-overlap: this targets `walhook.test` hook/autocheckpoint event behavior.
It avoids accepted pager/WAL checkpoint sync, persist-mode, setlk, protocol,
MVCC/recovery, checksum, WAL byte truncation, VFS writer/sync/lock,
rollback-journal, super-journal, and savepoint-byte clusters.
