# Real upstream pager/WAL no-shm readonly dynamic corpus

Micro-slice: `real-upstream-corpus-pager-wal-dynamic-20260531T071237Z-0`

Base accepted HEAD: `96c3c12f0e388eba581b5758d55cd85f17d538ef`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walnoshm.test`
  - `walnoshm.test` `1.1` through `1.11`: WAL reads without shared-memory sidecar and WAL removal after checkpoint.
  - `walnoshm.test` `2.1.1` through `2.2.6`: no-shm read-only mappings and immutable WAL sidecar handling.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walro.test`
  - Read-only WAL database opens preserve committed frames without writer recovery.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal6.test`
  - `wal6.test` `1.0` through `1.3` and `2.1` through `2.6`: reader-pinned checkpoints retain WAL while writes continue.

## PHP Coverage

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalNoShmReadonlyDynamic20260531T071237ZTest.php`.
- Adds 2,401 focused TestRunner PASS cases:
  - 2 checksum byte orders x 4 page sizes x 30 variants.
  - 10 distinct assertions per variant over WAL checksum validation, read-only snapshot frame boundaries, no-shm page-map visibility, passive checkpoint backfill, WAL preservation, and reader-pinned restart checkpoint retention.
  - 1 provenance assertion citing the hydrated upstream files and sections.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalNoShmReadonlyDynamic20260531T071237ZTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalNoShmReadonlyDynamic20260531T071237ZTest.php`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalNoShmReadonlyDynamic20260531T071237ZTest.php`
  - `1 test files, 2401 assertions, 0 failures`

## Non-overlap

This extends the real upstream pager/WAL corpus without repeating accepted
`wal2.test` header/recovery/fullfsync rows, `walckptnoop.test`,
`walcksum.test`, WAL byte truncation, VFS writer/sync/lock/rollback clusters,
rollback-journal commit/apply, super-journal commits, checkpoint transaction
plans, pager master-journal numbered surfaces, readonly no-SHM/setlk snapshot
accepted batch rows, or existing `SQLiteRealUpstreamPagerWalDynamicCorpusTest`
warm-body/checksum cases. The new coverage focuses on no-shm read-only WAL
opens and reader-pinned checkpoint retention across real upstream
`walnoshm.test`, `walro.test`, and `wal6.test` scenarios.

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`SQLiteWal`, `SQLiteWalHeader`, and `SQLiteWalOpenView` parsing, snapshot, and
checkpoint behavior.

## Next Task

Continue pager/WAL corpus admission with a distinct upstream file such as
`walro2.test`, `wal8.test`, or `wal9.test`, or fix a real default-memory
pager/WAL broad-sweep failure if it is the active blocker.
