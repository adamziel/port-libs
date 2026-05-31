# real-upstream-corpus-expression-affinity-dynamic-20260531T042519Z-0

Ported a bounded expression-affinity gap from the hydrated upstream SQLite
`test/e_expr.test` corpus:

- `e_expr-18.2.*`: `REGEXP` is syntactic sugar for an application-defined
  `regexp(Y,X)` function.
- `e_expr-19.2.*`: `MATCH` is syntactic sugar for an application-defined
  `match(Y,X)` function.

The source change lets `SQLiteSelectSql` parse `MATCH`, `NOT MATCH`, `REGEXP`,
and `NOT REGEXP` as scalar SELECT expressions, reusing the existing predicate
evaluator. The predicate evaluator now also handles the negated `NOT MATCH` and
`NOT REGEXP` forms consistently.

Focused growth: 273 TestRunner PASS cases / 1063 assertions in
`SQLiteRealUpstreamExpressionAffinityMatchRegexpDynamic20260531T042519ZTest.php`.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityMatchRegexpDynamic20260531T042519ZTest.php`
  - `1 test files, 1063 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityLogicalDynamic20260531Test.php`
  - `2 test files, 25281 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteSelectPredicate.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteSelectPredicate.php`
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityMatchRegexpDynamic20260531T042519ZTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityMatchRegexpDynamic20260531T042519ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - Not run: the guard file is not present in this isolated worktree.
- `git diff --check -- lanes/libsqlite`
  - Passed.

Non-overlap: this owns the application-defined MATCH/REGEXP gap explicitly
excluded from the older broad `e_expr-7.*` expression-affinity corpus. It does
not repeat accepted LIKE/GLOB NULL/exact behavior, Unicode GLOB ranges,
COLLATE, CASE/iif, EXISTS/scalar subqueries, CURRENT_* literals, CAST prefix or
lossy CAST behavior, `types2`/`affinity2`/`affinity3` matrices, SQL expression
ORDER BY, grouped SELECT text, JSON, B-tree, WAL, VFS, pager, trigger, or date
batches.

Dependency closure: no new support component is needed. The patch reuses
lane-local SELECT expression parsing and predicate callback dispatch.
