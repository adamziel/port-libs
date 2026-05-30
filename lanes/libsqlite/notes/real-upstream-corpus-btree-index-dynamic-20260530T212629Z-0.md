# real-upstream-corpus-btree-index-dynamic-20260530T212629Z-0

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/autoindex4.test`.
- Upstream sections covered: `autoindex4-1.0` through `autoindex4-4.8`.
- Focus: automatic partial-index behavior for inner joins, LEFT/RIGHT JOIN
  null-extension equivalents, impossible right-side predicates, correlated
  scalar subquery match counts, ORDER BY regression preservation, empty
  `NOT IN`, empty-subquery `NOT IN`, and optimization-toggle parity.
- Added `SQLiteBTreeIndexDynamicCorpusPlan::autoindex4PartialJoinCases()` with
  1200 distinct focused cases, all cited back to real upstream `autoindex4`
  sections.
- Direct test growth: `SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`
  gains 1200 focused TestRunner PASS cases.
- Non-overlap: this is not the accepted `autoindex1`, `autoindex5`,
  `index4`, `index5`, `index7`, `index8`, `indexA`, `indexfault`, B-tree page
  relocation, overflow freelist, bulk overflow freeblock, index-interior
  merge, or expression-index range-cost coverage.
- Dependency closure: no new support component is needed; the slice reuses the
  existing B-tree/index dynamic corpus plan and TestRunner harness.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php` -> `1 test files, 193019 assertions, 0 failures`.
