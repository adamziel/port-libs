# Real Upstream B-tree/Index Dynamic whereK Slice

- Session: `port-dev-sqlite-yield-dyn-real-btree-20260531T031310Z`
- Base accepted HEAD: `d3f35d53d135e23f73a270582d60d9916715bb54`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/whereK.test`
- Owned upstream range: `whereK-1.1` through `whereK-2.1`
- New focused PHP file: `lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereKOrFactoringDynamicTest.php`

## Behavior

This slice adds dynamic B-tree/index planner coverage for upstream `whereK.test`
OR-term factoring:

- `whereK-1.1`: `b>9 OR b=9` folds into an indexed lower bound on `t1bc(b,c)`.
- `whereK-1.2` through `whereK-1.4`: OR-arm order and literal-left comparison
  still factor `b>=8` while preserving the `c>7` residual.
- `whereK-1.5`: the factored lower bound preserves the `c NOT IN (4,5,6)`
  residual result set.
- `whereK-2.1`: the NOCASE join regression keeps the two OR orderings at one
  result row.

The focused file contributes 1000 dynamic behavior cases plus source-range,
invalid-size, and dependency-closure checks: 1003 PASS lines and 14841
assertions.

## Non-overlap

This does not repeat accepted B-tree page relocation, root collapse, overflow
freelist/freeblock, bestindexA/B/C/D/E/F, index7 partial-index stat/update,
index8 ORDER BY LIMIT, indexA partial-affinity, expression-index range cost, or
recent pager/VFS/WAL clusters. It specifically owns upstream `whereK.test`,
which was present in the veryquick manifest but not behavior-ported in the
B-tree/index corpus.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - PASS: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereKOrFactoringDynamicTest.php`
  - PASS: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereKOrFactoringDynamicTest.php`
  - PASS: `1 test files, 14841 assertions, 0 failures`.
  - PASS lines: 1003.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`
  - PASS: `1 test files, 349715 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. The slice reuses existing lane-local
B-tree/index dynamic corpus planning, OR-term factoring metadata, residual
predicate checks, NOCASE comparison expectations, and result-row helpers.
