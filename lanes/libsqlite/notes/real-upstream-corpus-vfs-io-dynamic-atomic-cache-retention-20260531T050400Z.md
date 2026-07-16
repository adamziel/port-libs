# real-upstream-corpus-vfs-io-dynamic-atomic-cache-retention-20260531T050400Z

Slice: `real-upstream-corpus-vfs-io-dynamic-20260531T050400Z-0`

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`
- `io-6.1`: builds an atomic-write-capable database image with a large warmed `t3` payload.
- `io-6.2.1`: verifies `PRAGMA integrity_check` before each commit variant.
- `io-6.2.2`: executes one-table and two-table commit variants while the database image is fully cached.
- `io-6.2.3`: writes direct corrupt bytes to the database file and expects cached `integrity_check` to remain `ok`.

Behavior added:

- Added `SQLiteVfsIoDynamicPlan::atomicCommitPagerCacheRetention()`.
- Added `SQLiteRealUpstreamCorpusVfsIoDynamicAtomicCacheRetentionTest.php`.
- The dynamic test ports `io-6.*` pager-cache retention behavior over a matrix of warmed payload sizes, cache capacities, single-table atomic commits, multi-page rollback-journal commits on an atomic-capable device, and direct disk corruption ranges.
- Focused coverage: 1536 distinct behavior cases plus one source-row guard,
  1537 TestRunner PASS lines, 38402 assertions.

Non-overlap:

- This does not repeat the accepted VFS I/O sync matrix for `io-2` through `io-5`, atomic journal admission, safe-append journal sizing, default page-size selection, VFS locked writer/sync/rollback apply, WAL checkpoint/savepoint byte truncation, or VFS lock/process-lock clusters.
- The owned behavior is only upstream `io.test io-6.*`: a warmed pager cache must survive atomic-capable commit variants so direct post-commit file corruption is not observed by the cached integrity check.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicAtomicCacheRetentionTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicAtomicCacheRetentionTest.php`
  - `1 test files, 38402 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

Dashboard expectation:

- Mapped denominator remains unchanged because upstream inventory is already complete.
- Count as PHP PASS-line/assertion growth for real upstream `io.test` behavior only.

Dependency closure:

- No new support component is needed. The slice reuses existing bounded VFS I/O dynamic planning and adds pager-cache retention metadata for atomic-capable commit behavior.
