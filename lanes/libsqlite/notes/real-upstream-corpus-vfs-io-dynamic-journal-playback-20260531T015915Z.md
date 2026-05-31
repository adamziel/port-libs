# real-upstream-corpus-vfs-io-dynamic-journal-playback-20260531T015915Z

Micro-slice: `real-upstream-corpus-vfs-io-dynamic-20260531T015915Z-0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr.test`
- Scenarios: `ioerr-7`, `ioerr-9`, and `ioerr-10`.

Behavior added:

- Added `SQLiteVfsIoDynamicPlan::journalPlaybackIoErrorProfile()` for early
  upstream VFS I/O error journal-playback edges:
  hot-journal read rollback deferral, master-journal name read failure, and
  statement-journal playback after a UNIQUE constraint failure.
- Added `SQLiteRealUpstreamCorpusVfsIoerrJournalPlaybackDynamicTest.php` with
  1,200 dynamic real upstream PASS cases plus hydrated-source and malformed
  input guards.

Non-overlap:

- This does not repeat accepted `io.test` traffic/default-page-size/sync
  matrix coverage, `ioerr-11`, `ioerr-13` through `ioerr-16`, `ioerr2`,
  `ioerr3`, `ioerr4`, `ioerr5`, `ioerr6`, VFS file writer/sync/lock,
  rollback-journal apply/commit, super-journal, WAL checkpoint/savepoint, or
  pager/WAL snapshot clusters.
- The owned upstream section is the earlier journal-playback family in
  `ioerr.test`: hot journal read recovery, master-journal-name read recovery,
  and statement subjournal playback after constraint failure.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerrJournalPlaybackDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerrJournalPlaybackDynamicTest.php` passed: `1 test files, 28409 assertions, 0 failures`, with 1,202 focused PASS lines.

Dependency closure:

- No new support component is needed. This reuses the existing lane-local
  VFS I/O dynamic planning surface and adds a bounded native PHP profile for
  upstream journal playback I/O error behavior.

Next task:

- Continue VFS I/O work only on a non-overlapping upstream section, such as a
  remaining `journal*.test`, `pagerfault*.test`, or VFS durability edge that
  is not already covered by accepted writer/sync/lock/rollback clusters.
