# real-upstream-corpus-btree-index-dynamic-20260531T074724Z-0

## Scope

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/indexexpr3.test`.
- Ported sections: `indexexpr3-1.1` through `indexexpr3-2.5`.
- Behavior cluster: JSON expression indexes, indexed-expression substitution, Function opcode elimination, and covering-vs-non-covering expression-index planner distinctions.

## Focused PHP Movement

- Added `SQLiteBTreeIndexDynamicCorpusPlan::indexexpr3JsonExpressionCoveringCases()`.
- Added `SQLiteRealUpstreamBtreeIndexExpr3JsonExpressionDynamicTest.php`.
- Focused dynamic cases: `1000` distinct TestRunner cases plus source-range, invalid-input, and dependency-closure guards.
- Non-overlap: this uses upstream `indexexpr3.test`; existing accepted B-tree/index dynamic batches covered `indexexpr1.test`, `indexexpr2.test`, `index7.test`, `indexA.test`, `index5.test`, and related planner/index families but did not include this `indexexpr3` JSON-expression-index covering cluster.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local B-tree/index dynamic corpus planner and JSON expression metadata fixtures.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexExpr3JsonExpressionDynamicTest.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexExpr3JsonExpressionDynamicTest.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php` passed: `2 test files, 80812 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.
- `lanes/libsqlite/lane-status.json` validated as JSON.
