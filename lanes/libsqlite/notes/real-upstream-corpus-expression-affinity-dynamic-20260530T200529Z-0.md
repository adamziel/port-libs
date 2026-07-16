# real-upstream-corpus-expression-affinity-dynamic-20260530T200529Z-0

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260530T200529Z-0`

Base accepted HEAD: `ab0d9bc9baa20e0418309c1ec67c0447e4a67962`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`
- Ported sections:
  - `affinity2-110` through `affinity2-150`: inserted storage classes for `INTEGER`, `REAL`, `BLOB`, `NUMERIC`, and `TEXT` columns.
  - `affinity2-200` through `affinity2-300`: column-column comparisons, including unary-plus operands where SQLite intentionally removes column affinity.

## Behavior

- Added `SQLiteRealUpstreamExpressionAffinity2DynamicTest.php`.
- The focused corpus creates the real upstream `t1(xi INTEGER, xr REAL, xb BLOB, xn NUMERIC, xt TEXT)` row shape with generic row-array data.
- It compares the PHP SELECT executor against the local `sqlite3` oracle for:
  - all 5 affinity columns as the left operand;
  - all 5 columns and their unary-plus forms as the right operand;
  - 10 comparison spellings: `=`, `==`, `!=`, `<>`, `<`, `<=`, `>`, `>=`, `IS`, `IS NOT`;
  - column-vs-literal and literal-vs-column comparisons over integer, real, text, leading-zero text, nonnumeric text, and NULL literals.
- Total focused growth: 1501 distinct TestRunner PASS cases / 1506 assertions.

## Implementation Fix

- `SQLiteAffinityComparison::normalizeAffinity()` now preserves declared `BLOB` affinity separately from `NONE`.
- Numeric affinity still applies to `TEXT`, `BLOB`, and `NONE` operands as SQLite requires.
- TEXT affinity now applies only to true no-affinity operands, not declared `BLOB` columns. This fixes `affinity2.test` behavior where `xb = xt` is false for row 1 (`integer 1` vs text `'1'`) while `xb = +xt` remains true.

## Non-Overlap

This does not repeat the accepted `types2.test` dynamic matrix, expression NULL comparison, BETWEEN matrix, expression precedence/operator corpus, date affinity, B-tree numeric affinity, Unicode GLOB, or source-neutral CAST/LIKE/GLOB defaults. The owned gap is the real `affinity2.test` column-affinity comparison rule for declared `BLOB` versus no-affinity unary-plus operands.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinity2DynamicTest.php`
  - `1 test files, 1506 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicTypes2MatrixTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionBetweenDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinity2DynamicTest.php`
  - `4 test files, 19808 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The slice reuses the existing row-array SELECT executor, column affinity metadata, `SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities()`, and local `sqlite3` oracle path already used by real upstream expression-affinity dynamic tests.
