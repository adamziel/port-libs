# real-upstream-corpus-pager-wal-dynamic-20260531T043151Z-0

Implemented a focused upstream pager/WAL corpus slice from hydrated SQLite
`/home/claude/port-libs/.upstream-cache/libsqlite/test/wal.test`.

Upstream sections:

- `wal.test` `wal-18.2.1` through `wal-18.2.12`: WAL header page-size
  recovery matrix for invalid page sizes (`128`, `256`, `131072`, non-power
  `1016`) and valid page sizes (`512` through `65536`).

Local coverage:

- Added `SQLiteRealUpstreamPagerWalInvalidPageSizeDynamicTest.php`.
- Adds 1002 focused TestRunner PASS cases: 1 hydrated-source citation, 1000
  dynamic behavior cases, and 1 non-overlap/dependency note.
- Valid WAL page sizes are parsed, checksummed, recovered through
  `SQLiteWal::transactionRecoveryBoundary()`, checkpointed, and read through
  `readerSnapshotPageImage()`.
- Invalid WAL page sizes are rejected before frame application, matching the
  upstream recovery boundary that ignores invalid WAL page-size headers instead
  of applying corrupt frame content.

Non-overlap:

- Avoids accepted WAL checkpoint, persistent close, checksum tail, WAL byte
  truncation, rollback-journal apply/commit, VFS writer/sync/lock, and pager1
  recovery batches.
- This slice covers WAL header page-size admissibility before frame
  application.

Dependency closure:

- No new support component needed.
- Reuses existing `SQLiteWalHeader` validation, `SQLiteWal` checksum/recovery,
  and the hydrated upstream SQLite `.test` checkout as source truth.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalInvalidPageSizeDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalInvalidPageSizeDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Root harness: not run - isolated micro-slice.
