# real-upstream-corpus-vfs-io-dynamic-20260531T060332Z-0

Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`.

Ported section: `io.test` `io-1.1` through `io-1.5`, covering VFS/database write counts for table creation, root-page fill inserts, root split, post-split leaf inserts, and the quick-balance rightmost leaf append path.

Patch scope:

- Added dynamic assertions to `SQLiteRealUpstreamCorpusVfsIoDynamicTest.php` using the existing lane-local `SQLiteVfsIoDynamicPlan::quickBalanceDynamicWriteProfile()` model.
- Non-overlap: this does not touch accepted avfs page-size/default-page-size behavior, ioerr2 autovacuum commit behavior, VFS sync/write/lock application, rollback-journal apply, WAL byte truncation, or file-control/mmap surfaces.
- Expected TestRunner movement from this worktree: focused VFS dynamic file moved from `64802` to `65112` assertions and adds `3` focused PASS cases.

Verification:

- Before: `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php` -> `1 test files, 64802 assertions, 0 failures`.
- After: `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php` -> `1 test files, 65112 assertions, 0 failures`.
- Syntax: `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php` -> no syntax errors.

Dependency closure: no new support component is needed; the slice reuses the existing lane-local VFS I/O dynamic profile and focused PHP TestRunner harness.

Next task: continue VFS I/O corpus burn-down with a non-overlapping real upstream section, preferably one that fixes the previously rejected avfs regression or adds broader runner-countable coverage without changing accepted page-size semantics.
