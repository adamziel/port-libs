# real-upstream-corpus-select-core-dynamic-real-select-20260531T122049Z

- Base accepted HEAD: `82ffc15bcb109224eed304cd069ec63109a1767a`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectA.test`.
- Ported sections: `selectA-2.72` through `selectA-2.91` duplicate-filled `t3` `UNION` DISTINCT compound merge ordering, plus `selectA-2.92` left-associative `INTERSECT` / `EXCEPT` / `UNION` chain with final `NOCASE` ordering.
- New focused coverage: `SQLiteRealUpstreamSelectADuplicateSourceCompoundDynamic20260531T122049ZTest.php` adds 1000 dynamic seeded compound SELECT cases plus source-truth and non-overlap/dependency-closure checks, for 1002 TestRunner PASS cases and 7009 focused assertions.
- Non-overlap: avoids accepted `selectA` union-all, reversed union, intersect/except low/high set, `selectH` omit-unused, `select7` affinity, JSON table, WAL, B-tree, VFS, atof1, record-storage, row-value, and app-WAL batches.
- Dependency closure: no new support component; this reuses lane-local `SQLiteSelectSql` compound set, `ORDER BY` collation, storage-class comparison, and hydrated SQLite upstream corpus evidence.

Verification so far:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectADuplicateSourceCompoundDynamic20260531T122049ZTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectADuplicateSourceCompoundDynamic20260531T122049ZTest.php` passed: `1 test files, 7009 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectAUnionOrderDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectAUnionDistinctOrderRemainderDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamicSelectAReversedUnionTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectAIntersectExceptDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectACompoundOrderDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectADuplicateSourceCompoundDynamic20260531T122049ZTest.php` passed: `6 test files, 61851 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Root harness: not run - isolated micro-slice.
