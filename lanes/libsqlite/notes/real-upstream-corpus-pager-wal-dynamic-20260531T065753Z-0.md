## real-upstream-corpus-pager-wal-dynamic-20260531T065753Z-0

Base accepted HEAD: `b596d6a43afd4ccaf50904f879de33fed9b5b7f3`.

Added a real upstream pager/WAL dynamic batch for:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walhook.test`
  - `walhook-1.1` through `walhook-1.5`: WAL hook callback frame reporting and checkpoint calls from the hook.
  - `walhook-2.1` through `walhook-2.9`: `PRAGMA wal_autocheckpoint`, threshold-triggered checkpointing, and WAL-log reuse after crossing the frame threshold.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walnoshm.test`
  - `walnoshm-1.2` through `walnoshm-1.11`: version-1 VFS WAL admission requiring exclusive locking and rollback-mode return.
  - `walnoshm-2.1.3` through `walnoshm-2.2.6`: copied WAL database access blocked until exclusive lock is acquired.
  - `walnoshm-3.1` through `walnoshm-3.2`: normal-locking return allowed only when exclusive mode is set after the WAL file opens.

Focused movement:

- New focused TestRunner cases: `1124` PASS lines.
- Focused assertions: `15416`.
- Countable as PASS-line growth only; no mapped-denominator growth.

Non-overlap:

Avoids accepted WAL byte truncation, checkpoint transactions, persistent close, `wal2` validation, readonly-SHM cache spill, rollback-journal apply/commit, VFS sync/file writer/lock, pager1 boundary, page-size mapping, super-journal commit, and rollback commit/apply batches. This batch covers WAL hook/autocheckpoint frame-threshold behavior and no-SHM exclusive WAL admission from separate upstream scripts.

Dependency closure:

No new support component is needed. The patch reuses `SQLitePagerWalDynamicPlan` and hydrated upstream SQLite `walhook.test` / `walnoshm.test` source truth.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerWalDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamic20260531T065753ZTest.php`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamic20260531T065753ZTest.php`
  - Result: `1 test files, 15416 assertions, 0 failures`
