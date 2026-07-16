# real-upstream-corpus-pager-wal-dynamic-20260531T120707Z-0

Base accepted HEAD: `e4074c45f1e9d3c2408ad3ef65aec8f4e6ec75cf`

This slice ports a non-overlapping pager/WAL behavior cluster from the hydrated
SQLite upstream checkout:

- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowallock.test`
- Scenario range: `rowallock.test` `1.$tn.1` through `1.$tn.5`
- Behavior: read-only WAL-mode handles can read through WAL/SHM sidecars, return
  the mmap result requested by `PRAGMA mmap_size`, reject writes with
  `attempt to write a readonly database`, do not block an independent writer
  appending to the WAL, can read an empty second table after the writer commits,
  and leave the WAL sidecar present after close.

Added `SQLiteWalReadonlyShmPlan::readOnlyWalLockPlan()` plus
`SQLiteRealUpstreamCorpusPagerWalReadonlyLockDynamic20260531Test.php`.

Focused PASS-line growth: `1004` new TestRunner PASS cases in the new focused
test file. Mapped denominator coverage is already `1589 / 1589`, so this is
PASS-line growth only.

Non-overlap: this targets `rowallock.test` read-only WAL lock behavior and
avoids accepted readonly-SHM `walro`/`walro2` refresh, `walrofault` OOM,
`walsetlk` timeout/snapshot, WAL byte truncation, rollback-journal
apply/commit, VFS writer/sync/lock, `pager4` DBMOVED, and app-WAL recovery
slices.

Dependency closure: no new support component is required. The slice reuses
generic read-only WAL/SHM state planning, the existing focused PHP TestRunner,
and the hydrated upstream `rowallock.test` source file.

Verification to run:

```text
php -l lanes/libsqlite/src/SQLiteWalReadonlyShmPlan.php
php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalReadonlyLockDynamic20260531Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalReadonlyLockDynamic20260531Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php
git diff --check -- lanes/libsqlite
```
