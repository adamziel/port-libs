# real-upstream-corpus-btree-index-dynamic-20260531T055727Z-0

Status: ready for integration.

This slice adds a non-overlapping real upstream B-tree/index expression-index
batch from the hydrated SQLite checkout:

- Upstream source truth:
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/indexexpr1.test`.
- Upstream sections covered: `indexexpr1-510` through `indexexpr1-2300`.
- Focus: late expression-index behavior after the already accepted
  `indexexpr1-110` through `indexexpr1-410` ranges, including SELECT-list alias
  expression-index lookup, skip-scan over expression terms, expression equality
  joins, NOCASE/RTRIM UNIQUE expression-index behavior, UNIQUE expression
  integrity checks, indexed `IN` truth updates, NULL-key range skipping,
  constant expression indexes, REPLACE and DELETE INDEXED BY expression-index
  regressions, numeric-looking text expression keys, REAL expression affinity,
  JSON arrow aggregate covering, GLOB literal-token behavior, grouped
  expression-index joins, and JSON-subtype re-evaluation guards.
- Focused addition:
  `SQLiteBTreeIndexDynamicCorpusPlan::indexExpressionLateDynamicCases(1000)`
  plus `SQLiteRealUpstreamBtreeIndexExpr1LateDynamicTest.php`.
- Focused PASS-line growth: `1003` distinct TestRunner PASS cases with
  `16919` assertions.

Non-overlap:

- This targets later `indexexpr1.test` sections that were not covered by the
  accepted expression-index batches for `indexexpr1-110` through
  `indexexpr1-410`, `indexexpr2`, or `indexexpr3`.
- It avoids accepted index2/index3/index4/index5/index6/index7/index8/index9,
  indexA, autoindex, bestindex, indexedby, whereK/whereL/whereM/whereN,
  B-tree page relocation/root-collapse/interior merge, overflow freelist/
  freeblock, JSON table, WAL, VFS, PRAGMA, and source-neutral cleanup clusters.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexExpr1LateDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexExpr1LateDynamicTest.php`
  - Result: `1 test files, 16919 assertions, 0 failures`
  - PASS lines: `1003`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This reuses
lane-local B-tree/index dynamic corpus expression-index, collation, mutation,
JSON-subtype, affinity, and planner-detail helpers.
