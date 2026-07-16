# real-upstream-corpus-expression-affinity-dynamic-20260531T045252Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- Ported upstream scenario family: `expr-1.24` through `expr-1.26` scalar `min()`/`max()` expression comparison and `expr-1.82` through `expr-1.85` NULL propagation for scalar `min()`/`max()`.

## Behavior Added

- Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicMinMax20260531Test.php`.
- Fixed `SQLiteCoreScalarFunction::minmax()` comparison to use SQLite-style bytewise TEXT/BLOB comparison instead of PHP numeric-string `<=>` comparison.
- Fixed scalar `min()` equal-value tie behavior so `min(0, 0.0)` returns the later equal argument, matching SQLite's storage-class-preserving result.
- The test builds a sqlite3 oracle from real upstream expression semantics, then verifies the PHP `SQLiteSelectSql` executor over `1024` distinct dynamic TestRunner cases.
- The matrix covers INTEGER, REAL, TEXT including leading-space/numeric-looking text, BLOB, NULL operands, two-argument `min()`/`max()`, and composed expression arguments.

## Non-Overlap

- This does not repeat the accepted `expr-1.27..1.37` boolean truthiness slice, accepted REAL arithmetic (`expr-2.*`), overflow arithmetic, row-context expression broad coverage, affinity2 column comparisons, CAST prefix conversion, NULL comparison operators, unbound parameters, bitwise/remainder coverage, expression `ORDER BY`, SELECT subqueries, or MATCH/REGEXP expression behavior.
- It owns only the real upstream scalar `min()`/`max()` comparison branch for this session.

## Verification

- Red-first focused command exposed 66 scalar `min()`/`max()` oracle mismatches before the comparator fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicMinMax20260531Test.php`
- Passing focused command after the fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicMinMax20260531Test.php`
- Result: `1 test files, 4101 assertions, 0 failures`.
- Focused PASS movement: `1025` TestRunner PASS lines (`1024` dynamic behavior cases plus the ownership/source citation case).

## Dependency Closure

- No new support component is needed. The slice reuses existing native `SQLiteSelectSql`, `SQLiteCoreScalarFunction`, scalar expression dispatch, and the local sqlite3 oracle against hydrated upstream SQLite source truth.
