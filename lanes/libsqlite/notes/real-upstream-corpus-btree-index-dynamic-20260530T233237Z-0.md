# real-upstream-corpus-btree-index-dynamic-20260530T233237Z-0

Base accepted HEAD: d7c5d7f50d0d0c3f24c91125036d23912559b628.

Added a non-overlapping B-tree/index dynamic batch to the existing real upstream corpus plan:

- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index7.test`
- Upstream sections: `index7-3.1` through `index7-8.1`
- Focused behavior: partial UNIQUE index exclusion after bulk update, duplicate sentinel admission, post-VACUUM integrity, ignored database qualifiers inside partial-index predicates, view/subquery planner routing with partial indexes, invalid partial-index DDL token rejection, `IS TRUE` exclusion from an `IS NOT TRUE` partial index, and tiny-table `sqlite_stat1` planner use.
- Local addition: `SQLiteBTreeIndexDynamicCorpusPlan::index7PostUpdateVacuumPlannerCases(1000)` plus 1000 focused `TestRunner` cases in `SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`.
- Non-overlap: does not repeat existing `index7-1.*` stat mutation cases, existing `index7-2.*` WITHOUT ROWID update matrix, accepted index2/index3/index5/index6/index8/index9/indexA/indexexpr/autoindex batches, or older B-tree page-move/overflow/freelist accepted clusters.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php` passed: `1 test files, 313987 assertions, 0 failures`, `20032` PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `1 test files, 3 assertions, 0 failures`.

Expected selected PASS-line movement: +1000, from 1157667 to 1158667.

Dependency closure: no new support component is needed; this reuses the existing real upstream B-tree/index dynamic corpus plan and focused PHP runner.
