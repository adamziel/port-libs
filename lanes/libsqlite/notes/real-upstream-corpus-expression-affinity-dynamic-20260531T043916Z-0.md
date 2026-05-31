# real-upstream-corpus-expression-affinity-dynamic-20260531T043916Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- Ported upstream scenario family: `expr-1.27` through `expr-1.37` boolean expression truthiness for `AND`, `OR`, comparison-composed boolean expressions, and NULL-aware truth contexts.

## Behavior Added

- Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicBooleanTruth20260531Test.php`.
- The file builds a sqlite3 oracle from real upstream expression semantics, then verifies the PHP `SQLiteSelectSql` executor over `1024` distinct dynamic TestRunner cases.
- The matrix covers INTEGER, REAL, TEXT numeric-prefix, TEXT nonnumeric, empty TEXT, whitespace-prefixed TEXT, and NULL operands.

## Non-Overlap

- This slice does not repeat accepted REAL arithmetic (`expr-2.*`), overflow arithmetic, row-context expressions, affinity2 column comparisons, CAST prefix conversion, NULL comparison operators, unbound parameters, expression `ORDER BY`, SELECT subqueries, or MATCH/REGEXP expression behavior.
- It owns the boolean truthiness branch of `expr.test` expression affinity coverage for this session.

## Verification

- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicBooleanTruth20260531Test.php`
- Expected focused movement: `1025` TestRunner PASS lines (`1024` dynamic behavior cases plus the ownership/source citation case).

## Dependency Closure

- No new support component is needed. The test reuses the existing bounded `SQLiteSelectSql` executor and the locally hydrated upstream SQLite source plus `sqlite3` as an oracle.
