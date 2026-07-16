# real-upstream-corpus-btree-index-dynamic-20260531T050626Z-0

- Base accepted HEAD: `7d59ee97325649cafd2449deb321f30571bf474f`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index4.test`.
- Owned upstream range: `index4.test` sections `index4-1.2/1.3`, `index4-1.4/1.5`, `index4-1.6`, `index4-1.7`, `index4-1.8`, and `index4-2.2`.
- Non-overlap: avoids accepted/adjacent `index5` write-order, `index8` ORDER BY LIMIT, `index9` bound partial-index planner, `indexA` partial affinity, index-interior merge, page relocation, overflow freelist, and generic status-only suite evidence.
- PHP behavior added: `SQLiteBTreeIndexDynamicCorpusPlan::index4CreateIndexValidationCases()` produces 1200 real upstream CREATE INDEX validation cases over large randomblob tables, limited cache rebuild, mixed text/NULL/overflow-sized rows, single-row and empty tables, and UNIQUE duplicate rejection.
- Focused PASS-line growth type: PASS-line growth from 1203 new focused TestRunner cases in `SQLiteRealUpstreamBtreeIndex4CreateValidationDynamicTest.php`; mapped coverage unchanged.
- Dependency closure: no new support component needed; reuses lane-local B-tree/index corpus planner, schema catalog, integrity, large-row, limited-cache, and UNIQUE constraint diagnostics.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex4CreateValidationDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex4CreateValidationDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
