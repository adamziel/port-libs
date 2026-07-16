# real-upstream-corpus-pager-wal-dynamic-20260531T012649Z-0

Accepted base: `af20380a278ad54b2ad38b5d180ded7ec9aac2e7`.

Scope: non-overlapping pager/WAL upstream corpus rows from hydrated SQLite `test/wal3.test`.

Upstream sections ported:

- `wal3.test wal3-2.*`: multiproc/singleproc checkpoint byte-zero boundaries while readers hold snapshots.
- `wal3.test wal3-6.1.*` and `wal3.test wal3-6.2.*`: readmark race retry when a writer appends or a checkpoint unlock race occurs during read-lock acquisition.
- `wal3.test wal3-9.1.*`: many-reader fallback to a shared readmark when exclusive readmark updates are denied.

Focused movement:

- Added `SQLiteRealUpstreamPagerWalDynamicCorpusPlan::wal3ReadmarkRaceRows()` with 302 hydrated upstream-derived row records.
- Added 605 distinct `TestRunner` PASS cases to `SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`.
- Focused result: `1 test files, 57878 assertions, 0 failures`, `5245` PASS lines.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This reuses the existing pager/WAL corpus plan and no-domain-specific API guard.

Non-overlap: avoids prior accepted `wal2`, `walckptnoop`, `waloverwrite`, `walpersist`, `walhook`, WAL byte truncation, checkpoint transaction, rollback-journal, VFS writer/sync/lock, and JSON/B-tree/SQL accepted clusters.
