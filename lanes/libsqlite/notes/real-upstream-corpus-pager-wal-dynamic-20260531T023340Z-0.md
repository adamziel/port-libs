# real-upstream-corpus-pager-wal-dynamic-20260531T023340Z-0

Base accepted HEAD: `0374bb37770e0bf365d4f603a02af1f3e153889e`

## Upstream source

- Hydrated file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/walro.test`
- Ported section: `walro.test` `1.4.4.1..1.4.4.2`

## Behavior ported

- Added `SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walReadonlyShmCacheSpillRows()`.
- Added `SQLiteRealUpstreamCorpusPagerWalReadonlyShmCacheSpillTest.php`.
- Ports the read-only SHM WAL reader behavior where a read-write connection opens a transaction, sets `PRAGMA cache_size = 10`, creates `t2`, doubles rows 9 times, causes cache spill/log-wrap pressure, and the read-only connection must keep its stable snapshot until commit.
- Adds 1,024 per-row TestRunner cases over the generated `t2` rows: 512 uncommitted hidden rows and 512 committed visible rows.

## Focused movement

- New focused PASS cases: `+1026`
- Focused behavior assertions: `24583`
- Expected selected movement: `1711177 -> 1712203 pass / 0 fail`
- Mapped denominator movement: none; coverage remains `1589 / 1589`.

## Verification

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalReadonlyShmCacheSpillTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalReadonlyShmCacheSpillTest.php`
  - `1 test files, 24583 assertions, 0 failures`

## Non-overlap

This slice extends the real upstream pager/WAL dynamic corpus without repeating
accepted `wal2`, `wal3`, `walrestart`, `walckptnoop`, `waloverwrite`,
`walpersist`, `walhook`, WAL byte truncation, rollback-journal apply/commit,
VFS writer/sync/lock, checkpoint transaction, JSON, SQL, B-tree, or source
neutralization clusters. It is specifically `walro.test` read-only SHM snapshot
stability during cache-spill/log-wrap write pressure.

## Dependency closure

No new support component is needed. The slice reuses existing lane-local
pager/WAL dynamic corpus modeling and the hydrated upstream SQLite checkout as
source truth.

## Root harness

Not run - isolated micro-slice.
