# real-upstream-corpus-vfs-io-dynamic-20260531T004517Z-0

Base accepted HEAD: `93b324b07783b617d6c0938ad7bcd94b70aaa32e`.

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/mmapwarm.test`
  - `mmapwarm` 1.1, 1.2, 1.3, 1.4: `sqlite3_mmap_warm` success and no-op cases.
  - `mmapwarm` 2.0: open transaction returns `SQLITE_MISUSE`.
  - `mmapwarm` 3: OOM fault path returns either `SQLITE_OK` or `SQLITE_NOMEM` without poisoning the connection.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/mmapfault.test`
  - `mmapfault` 1-pre and 1: unique-index insert fault recovery with mmap enabled and small cache.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/mmapcorrupt.test`
  - `mmapcorrupt` 1.0, 2.1, 2.2: targeted mmap read from a corrupt tail of a WITHOUT ROWID database remains usable for the accessed schema/table path.

## Handoff delta

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsMmapWarmFaultCorruptDynamicTest.php`.
- Focused PASS growth: `+1001` TestRunner PASS lines.
- Focused assertion count: `18611` assertions.
- Expected selected throughput: `1366198 -> 1367199` pass / `0` fail.
- Mapped denominator coverage remains `1589 / 1589`; this is PASS-line growth, not mapped-denominator growth.

## Non-overlap

This slice is separate from existing accepted/current coverage for:

- `mmap1.test` read growth and vacuum truncation.
- `mmap2.test` syscall failure logging.
- `mmap3.test` active statement resize.
- VFS sync-matrix, lock-state, file-writer, rollback-journal, WAL checkpoint transaction, and VFS sync apply behavior.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsMmapWarmFaultCorruptDynamicTest.php`
  - `1 test files, 18611 assertions, 0 failures`
  - `1001` PASS lines

## Dependency closure

No new support component is needed. The slice reuses existing native PHP VFS mmap profile helpers in `SQLiteVfsIoDynamicPlan` and adds upstream-backed focused coverage only.
