# real-upstream-corpus-pager-wal-dynamic-20260530T185437Z-0

Status: ready for integration as focused real upstream WAL corpus growth.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walckpt.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walro.test`

Behavior ported:

- Dynamic WAL transaction visibility with uncommitted tail frames ignored by reader snapshots.
- Stale-reader checkpoint limits for passive/full/restart/truncate/noop checkpoint modes.
- Checkpoint database byte application for latest committed frames while preserving WAL bytes when a tail or stale reader prevents reset.
- Read-mark planning for stale readers, latest readers, and reusable slots.
- Read-only/stale snapshot stability across checkpoint attempts.

Focused test growth:

- Added `lanes/libsqlite/tests/SQLiteWalDynamicReaderCheckpointCorpusTest.php`.
- The file builds 40 real WAL byte streams with alternating 512/1024-byte page sizes, varying base page counts, 2-6 committed transactions, stale reader frames, and one uncommitted tail frame.
- Each stream runs 30 TestRunner cases, for 1200 distinct PASS lines.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalDynamicReaderCheckpointCorpusTest.php`
  - `1 test files, 1200 assertions, 0 failures`
- `php -l lanes/libsqlite/tests/SQLiteWalDynamicReaderCheckpointCorpusTest.php`
  - `No syntax errors detected`
- `git diff --check -- lanes/libsqlite`
  - passed

Non-overlap:

- This avoids accepted WAL persist, WAL noop checkpoint, WAL SHM read-mark restart, WAL transaction recovery, WAL reader/writer checkpoint snapshot, WAL savepoint byte truncation, VFS rollback/savepoint/commit application, pager master-journal current-source wrappers, VFS ioerr2/default-page-size coverage, and WordPress-shaped examples.
- This slice is dynamic upstream WAL reader/checkpoint behavior only; it does not add metadata-only rows, fake upstream script IDs, compatibility aliases, or new domain-specific APIs.

Dependency closure:

- No new support component is needed. The slice reuses lane-local `SQLiteWal`, `SQLiteWalHeader`, checksum, checkpoint, snapshot, and read-mark primitives.

Root harness:

- Not run - isolated micro-slice.
