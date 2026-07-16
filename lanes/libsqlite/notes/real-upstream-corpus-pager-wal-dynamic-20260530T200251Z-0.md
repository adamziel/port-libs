# real-upstream-corpus-pager-wal-dynamic-20260530T200251Z-0

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/waloverwrite.test`
- Ported sections:
  - `waloverwrite-1.1.2` through `waloverwrite-1.1.10`
  - `waloverwrite-1.2.2` through `waloverwrite-1.2.10`

## Behavior covered

- Builds checksum-valid WAL byte streams for overwrite transactions with 20
  logical pages and repeated page rewrites.
- Verifies copied database images without the WAL keep base page content, while
  copied WAL sidecars recover committed overwritten page images.
- Verifies savepoint rollback recovery truncates valid uncommitted draft frames
  and keeps the pre-savepoint committed images.
- Exercises `SQLiteWal::parse()`, `transactionRecoveryBoundary()`,
  `checkpointDatabaseImage()` via recovery output, and
  `readerSnapshotPageImage()` with per-page assertions.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalOverwriteDynamicCorpusTest.php`
  - `1 test files, 1549 assertions, 0 failures`
  - `1549` focused TestRunner PASS lines

## Non-overlap

This does not repeat accepted WAL hot-journal checkpoint, restart-overwrite,
persist-WAL close, VFS file-writer, rollback-journal apply, checkpoint
transaction, or savepoint byte-truncation batches. It owns the upstream
`waloverwrite.test` overwrite/savepoint corpus shape for this slice.

## Dependency closure

No new support component is needed. The slice reuses existing native PHP WAL
checksum, parser, recovery, checkpoint-image, and reader-snapshot primitives.
