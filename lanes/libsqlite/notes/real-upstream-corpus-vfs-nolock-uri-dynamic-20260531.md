# Real Upstream Corpus VFS nolock URI Dynamic

Slice: `real-upstream-corpus-vfs-io-dynamic-20260531T014000Z-0`
Base accepted HEAD: `d0e37b664c0ef9500748faeafd4d7f1484470255`

Added a focused real-upstream VFS I/O dynamic test file for URI `nolock`
locking suppression and immutable/read-only/write-transaction lock-call
boundaries. The cases cite hydrated upstream SQLite sources:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/nolock.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/lock5.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/win32nolock.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/filectrl.test`

Focused movement:

- New focused TestRunner PASS cases: `1002`
- New focused assertions: `9002`
- Countable as PASS-line growth against the selected VFS I/O dynamic corpus.
- Non-overlap: does not repeat accepted file-writer, lock-byte range,
  process file lock, lock-state, sync plan/apply, rollback-journal apply,
  journal2 safe-delete, atomic journal admission, cksumvfs, WAL SHM, or
  `ioerr*` pointer-map/fault recovery clusters.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsNoLockUriDynamic20260531Test.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsNoLockUriDynamic20260531Test.php`
  - `1 test files, 9002 assertions, 0 failures`

Dependency closure:

- No new support component is needed. The test reuses the existing bounded
  `SQLiteVfsIoDynamicPlan::nolockProbe()` behavior and existing URI/VFS
  capability parsing.
