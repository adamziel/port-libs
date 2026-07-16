# real-upstream-corpus-pager-wal-dynamic-20260531T004433Z-0

Session: `port-dev-sqlite-yield-dyn-real-pager-20260531T004433Z`
Base accepted HEAD: `ad16a572f80ccf85246d93f3ad58ce0402786c09`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walhook.test`
- Ported scenario family: `walhook-1.1` through `walhook-1.5` and
  `walhook-2.1` through `walhook-2.9`.

## Behavior Ported

Added `SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walHookAutocheckpointRows()`
and 1000 dynamic TestRunner cases in
`SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`.

The new rows exercise the real upstream WAL hook/autocheckpoint behavior:

- WAL hook callbacks report the `main` database and the current frame count.
- `PRAGMA wal_autocheckpoint = 10` begins checkpointing after the log reaches
  the 10-frame threshold.
- Database byte size follows the 1024-byte upstream page-size setup.
- WAL byte size follows SQLite WAL header plus frame-record sizing.
- Post-threshold transactions recycle the WAL start while preserving the
  checkpointed database size boundary.

## Focused Evidence

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
  - `1 test files, 39622 assertions, 0 failures`

Focused PASS growth: `+1002` TestRunner cases, all from real upstream
`walhook.test` behavior. Expected selected movement: `1344000` to `1345002`
PASS, `0` fail. Mapped denominator remains `1589 / 1589`.

## Non-Overlap

This slice does not repeat accepted WAL setlk snapshot, WAL persist mode,
WAL checkpoint transaction, VFS rollback journal apply, VFS writer, savepoint
byte truncation, WAL checksum, walckptnoop, waloverwrite, walpersist, or pager
master-journal numbered surfaces. It specifically owns upstream `walhook.test`
hook frame-count and autocheckpoint recycling behavior.

## Dependency Closure

No new support component is needed. The slice reuses lane-local pager/WAL
dynamic corpus helpers and existing PHP TestRunner infrastructure.

## Next Task

Continue pager/WAL real-upstream work only on a non-overlapping upstream file
or behavior section, such as a remaining `walprotocol*`, `pagerfault*`, or
`journal*` failure cluster that can add large focused behavior coverage or
remove a named runner blocker.
