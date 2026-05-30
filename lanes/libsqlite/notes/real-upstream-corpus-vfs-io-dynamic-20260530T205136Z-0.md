# real-upstream-corpus-vfs-io-dynamic-20260530T205136Z-0

Base accepted HEAD: `f32e8deaca85f9598bd0eb6230903f7d3fab9f57`.

Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`.

Ported behavior cluster: `io.test` `io-6.1` and `io-6.2.1.1` through `io-6.2.2.3`, covering atomic-write pager-cache retention after commit. The new focused assertions exercise the existing native `SQLiteVfsIoDynamicPlan::atomicPagerCacheRetentionProfile()` matrix across page sizes, cache sizes, single-table atomic commits, multi-table rollback-journal commits, ordinary non-atomic devices, too-small cache visibility, upstream section citations, and malformed input guards.

Focused assertion delta: accepted baseline for `SQLiteRealUpstreamCorpusVfsIoDynamicTest.php` was `62,066` assertions. This patch raises it to `64,802` assertions, for `+2,736` focused assertions and 4 new PASS cases in that file.

Non-overlap: this extends the already accepted real VFS IO dynamic corpus with previously untested `io-6.*` pager-cache retention assertions. It does not add metadata-only runner rows, duplicate accepted VFS writer/sync/lock/rollback clusters, repeat `io-2.*` atomic journal admission, repeat safe-append/sequential IO traffic, or introduce any domain-specific API.

Dependency closure: no new support component is needed. The test reuses the existing native VFS IO dynamic plan and the current lane test harness.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php` passed: `1 test files, 64802 assertions, 0 failures`.
- Baseline comparison using the accepted version of the same test file passed: `1 test files, 62066 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Root harness: not run - isolated micro-slice.
