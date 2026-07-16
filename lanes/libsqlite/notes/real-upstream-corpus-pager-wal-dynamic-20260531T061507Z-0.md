# real-upstream-corpus-pager-wal-dynamic-20260531T061507Z-0

Base accepted HEAD: `2139c8ce030e83a04c23079c17d6da80f20ffd83`.

Added `SQLiteRealUpstreamPagerWalAttachedCheckpointDynamicTest.php`, a
real-upstream pager/WAL batch backed by
`/home/claude/port-libs/.upstream-cache/libsqlite/test/wal.test`.

Covered upstream scenarios:

- `wal.test` `wal-16.1`: omitted database name checkpoints all attached WAL databases.
- `wal.test` `wal-16.2`: empty database name checkpoints all attached WAL databases.
- `wal.test` `wal-16.3`: unqualified `PRAGMA wal_checkpoint` checkpoints attached WAL databases.
- `wal.test` `wal-16.4`: C API checkpoint targets only `main`.
- `wal.test` `wal-16.5`: C API checkpoint targets only `aux`.
- `wal.test` `wal-16.6`: `temp` checkpoint succeeds without WAL-backed database work.
- `wal.test` `wal-16.7`: `PRAGMA main.wal_checkpoint` targets only `main`.
- `wal.test` `wal-16.8`: `PRAGMA aux.wal_checkpoint` targets only `aux`.
- `wal.test` `wal-16.9`: `PRAGMA temp.wal_checkpoint` reports no WAL frames.

Focused growth:

- `1008` distinct TestRunner PASS cases.
- `26208` focused behavior assertions.
- Reuses native `SQLiteWal` header parsing, checksum validation, frame parsing,
  passive checkpoint result planning, database page-count accounting, and WAL
  byte-order handling over main/aux attached-database WAL images.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalAttachedCheckpointDynamicTest.php`
  passed.
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalAttachedCheckpointDynamicTest.php`
  passed: `1 test files, 26208 assertions, 0 failures`.

Dependency closure: no new support component is needed; this reuses existing
lane-local native WAL parsing, checksum, frame, transaction, and checkpoint
helpers.

Non-overlap: this does not edit production source and does not repeat recent
pager/WAL rollback-commit, super-journal, sync-plan/apply, savepoint rollback,
checkpoint-transaction, WAL byte truncation, WAL overwrite/restart, readonly
SHM, wal2 header-recovery, wal5 blocking-checkpoint, walckptnoop, walpersist,
or lock-recovery batches. The slice focuses on `wal.test` attached database
checkpoint target selection in `wal-16.*`.
