# Real upstream pager/WAL snapshot boundary dynamic corpus

Micro-slice: `real-upstream-corpus-pager-wal-dynamic-20260530T205916Z-0`

Base accepted HEAD: `f32e8deaca85f9598bd0eb6230903f7d3fab9f57`

Added `SQLiteRealUpstreamPagerWalSnapshotBoundaryDynamicTest.php`, a focused
behavior corpus backed by the hydrated upstream SQLite checkout under
`/home/claude/port-libs/.upstream-cache/libsqlite/test`.

Upstream source files and scenario ranges:

- `walrestart.test` `walrestart-1.*` and `walrestart-2.*`
- `walshared.test` `walshared-1.0` through `walshared-1.4`
- `walpersist.test` `walpersist-1.0` through `walpersist-1.11`, and
  `walpersist-2.1` through `walpersist-2.3`
- `wal5.test` `wal5-1.*`
- `pager2.test` `pager2-1.*`, `pager2-2.1`, and `pager2-3.1`

Behavior covered:

- Valid WAL header/frame checksum parsing over 32 generated scenario shapes.
- Multiple committed transaction boundaries, last-commit prefixes, and
  uncommitted draft-tail recovery.
- Reader snapshot visibility through the last committed frame.
- Checkpoint database image materialization across page sizes 512, 1024, 2048,
  and 4096.
- Passive/full/restart/truncate/noop checkpoint-mode result invariants.
- Corrupt and truncated draft-tail recovery boundaries preserving the committed
  prefix.

Focused verification:

```text
php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalSnapshotBoundaryDynamicTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalSnapshotBoundaryDynamicTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalSnapshotBoundaryDynamicTest.php
1 test files, 1437 assertions, 0 failures
```

Expected dashboard movement: `phpPass +1437` from real focused PHP TestRunner
PASS cases. Mapped denominator coverage is already complete at `1589 / 1589`,
so this is PASS-line growth only.

Dependency closure: no new support component is needed. The slice reuses the
existing native PHP `SQLiteWal`, `SQLiteWalHeader`, and WAL checkpoint/recovery
helpers.

Non-overlap: this does not add metadata-only admission rows and avoids the
accepted pager/WAL persist-mode, overwrite, noop-checkpoint, crash-recovery,
restart-overwrite, WAL byte truncation, rollback-journal apply, checkpoint
transaction, savepoint rollback, and VFS writer clusters. It focuses on dynamic
snapshot/checkpoint boundary behavior across real upstream pager/WAL scenario
ranges.
