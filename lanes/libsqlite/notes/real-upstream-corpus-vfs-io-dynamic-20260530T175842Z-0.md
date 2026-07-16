Slice: `real-upstream-corpus-vfs-io-dynamic-20260530T175842Z-0`

Added a real upstream VFS/io corpus extension to the existing
`SQLiteRealUpstreamCorpusVfsIoDynamicTest.php` batch.

Upstream source files and scenarios:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/cksumvfs.test`
  - `cksumvfs.test` 1.3 through 1.9: checksum VFS reserve-byte handling,
    WAL delete/checkpoint, row-count persistence after save/restore reopen,
    and plain reopen.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walvfs.test`
  - `walvfs.test` 2.0 through 2.3: WAL `journal_size_limit` clamping after
    checkpoint and subsequent insert.
  - `walvfs.test` 3.1 and 3.2: checkpoint interruption through `xWrite` and
    OOM-before-interrupt result-code precedence.

Implementation:

- Extended `SQLiteVfsIoDynamicPlan` with:
  - `checksumReserveProfile()`
  - `walJournalSizeLimitProfile()`
  - `walCheckpointInterruptProfile()`
- Added dynamic matrix assertions over reserve-byte/page-size/row-count
  combinations, WAL size limits, and checkpoint interrupt priority cases.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
  - passed
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php`
  - passed
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php`
  - `1 test files, 5360 assertions, 0 failures`

Assertion delta:

- Existing focused file now passes `5360` assertions.
- This slice adds 2466 focused behavior assertions by loop expansion:
  - checksum reserve profile: 64 cases with 15 assertions plus 64 cases with
    6 assertions
  - WAL journal-size limit: 18 cases with 13 assertions plus 3 invalid-input
    assertions
  - checkpoint interrupt priority: 80 cases with 11 assertions
  - shared malformed-input guard: 5 assertions

Non-overlap:

- Does not repeat accepted VFS file writer, lock state, process locks,
  rollback-journal apply/commit, sync apply, WAL checkpoint transactions,
  WAL byte truncation, append VFS layout/growth, `io.test` atomic/safe-append
  device-characteristic matrices, nolock/immutable suppression, or SQL
  file-control sequences already present in this focused file.

Dependency closure:

- No new support component is needed. The slice reuses the existing bounded
  VFS/io plan helpers and upstream corpus test harness.

Dashboard expectation:

- Count as focused assertion growth only. No mapped upstream denominator row
  is claimed in this worker handoff.
