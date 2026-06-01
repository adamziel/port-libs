# Real Upstream Corpus VFS Cacheflush Fault Dynamic

- Micro-slice: `real-upstream-corpus-vfs-io-dynamic-20260601T200129Z-0`
- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/cffault.test`
- Upstream sections: `cffault-1.1`, `cffault-1.2`, `cffault-2.1`, `cffault-2.2`, `cffault-2.3`, `cffault-2.4`
- Behavior ported: `sqlite3_db_cacheflush()` fault handling inside active update transactions, table-scan callbacks, indexed payload rows, release-memory retry, commit paths, rollback path, and integrity preservation.
- Non-overlap: this slice does not repeat accepted tempfault/ioerr/pagerfault, VFS writer/sync/lock-state, rollback-journal apply, WAL byte truncation, JSON table cursor/source/constraint, B-tree page move/root-collapse/overflow release, or SELECT text execution clusters. It owns the previously unported `cffault.test` cacheflush fault boundary.
- Focused growth: `+1003` TestRunner PASS cases, `42019` focused assertions.
- Mapped denominator: unchanged at `1589 / 1589`; this is accepted-test growth from a hydrated real upstream script, not a new manifest admission.
- Dependency closure: existing pager/VFS fault-planning primitives are reused; no new support component is required.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php` -> no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsCacheflushFaultDynamicTest.php` -> no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsCacheflushFaultDynamicTest.php` -> `1 test files, 42019 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 8 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite` -> passed

Next task:

- Continue real upstream VFS I/O work by targeting another hydrated, non-overlapping script section that exercises native file-handle, pager, or WAL behavior rather than adding metadata-only rows.
