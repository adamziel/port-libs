# real-upstream-corpus-expression-affinity-dynamic-20260531T103104Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- Ported scenario family: `expr-1.45a` through `expr-1.46e` plus `expr-1.96` through `expr-1.99`.

## Behavior Added

- Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicShiftBoundary20260531T103104ZTest.php`.
- The shard compares `SQLiteSelectSql` against a local `sqlite3` oracle for bitwise left/right shift behavior over integer, REAL, TEXT, BLOB, boolean-literal, and NULL operands.
- Dynamic coverage is `32` left operands x `16` shift counts x `2` shift operators = `1024` real TestRunner PASS cases, plus ownership/dependency guard cases.

## Non-Overlap

- This owns the upstream `expr.test` shift-boundary family: oversized counts, negative shift-count reversal, sign-extending right shifts, text/BLOB integer coercion, and NULL propagation.
- It does not repeat accepted arithmetic, overflow `+/-/*`, general bitwise literals, NULL coalesce, BETWEEN, `IS DISTINCT`, CASE, collation, LIKE/GLOB, `IN`, `types2/types3`, expression `ORDER BY`, JSON, WAL, VFS, B-tree, PRAGMA, trigger/FK, or suite-evidence slices.

## Verification

- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicShiftBoundary20260531T103104ZTest.php`
- Result: `1 test files, 4104 assertions, 0 failures`.
- Focused movement: `1026` TestRunner PASS lines (`1024` dynamic behavior cases plus two source/dependency guard cases).

## Dependency Closure

- No new support component is needed. This reuses the existing bounded `SQLiteSelectSql` bitwise shift expression executor and the locally hydrated upstream SQLite checkout with `sqlite3` as an oracle.
