# real-upstream-corpus-pager-wal-dynamic-20260531T034619Z-0

Added `SQLiteRealUpstreamPagerWalReadonlyShmTruncateDynamicTest.php`, a focused
real upstream pager/WAL dynamic batch derived from the hydrated upstream SQLite
checkout at `/home/claude/port-libs/.upstream-cache/libsqlite/test/walro2.test`.

Upstream sections cited:

- `walro2.test` 3.1.* readonly_shm reader opens from WAL and SHM.
- `walro2.test` 3.2.* writer checkpoint truncate while readonly_shm reader is
  open.
- `walro2.test` 3.3.* readonly_shm reader reruns recovery after truncate.
- `walro2.test` 4.1.* external truncate and subsequent readonly_shm reopen.
- `walro2.test` 5.* readonly_shm readers survive large WAL/truncate cycles.

Coverage:

- 1,000 dynamic WAL rows over page sizes 512/1024/2048/4096, mixed WAL byte
  order, varying database page counts, target pages, and committed post-reader
  frames.
- 1 source-citation PASS case.
- 18,001 focused behavior assertions.
- No mapped denominator movement claimed; mapped coverage remains complete.

Non-overlap:

- This does not repeat accepted WAL byte truncation, rollback-journal apply or
  commit, VFS writer/lock/sync, WAL checkpoint transactions, readonly-SHM cache
  spill, file-permission, WAL restart/noop/checksum/overwrite/crash batches, or
  metadata-only runner admission.
- The distinct behavior is `walro2.test` readonly-SHM truncate/recovery reader
  pinning: a reader pinned before the later committed frame preserves the WAL
  during truncate, while releasing the reader allows the truncate result to
  checkpoint and drop the WAL bytes.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalReadonlyShmTruncateDynamicTest.php`
  passed with no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalReadonlyShmTruncateDynamicTest.php`
  passed: `1 test files, 18001 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure:

- No new support component is needed. The slice reuses existing lane-local
  `SQLiteWal` parsing, checksum, reader snapshot, and checkpoint mode behavior
  against hydrated upstream `walro2.test` scenarios.
