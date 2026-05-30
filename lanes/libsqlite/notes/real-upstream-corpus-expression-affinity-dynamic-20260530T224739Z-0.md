# real-upstream-corpus-expression-affinity-dynamic-20260530T224739Z-0

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- `e_expr-13.*`: `BETWEEN` equivalence and precedence behavior.
- `e_expr-12.*` expression forms: simple and searched `CASE`.

## Local coverage

- Added `SQLiteRealUpstreamExpressionAffinityDynamicBetweenCaseTest.php`.
- The test builds 1010 oracle-backed dynamic expressions and one ownership
  guard, for 1011 focused TestRunner PASS cases.
- Assertion shape per dynamic row: native `SQLiteSelectSql` result row count,
  `quote(...)`, `typeof(...)`, and `quote((...) IS NULL)` all compared to a
  local `sqlite3` oracle.
- Focused result: `1 test files / 4045 assertions / 0 failures / 1011 PASS
  lines`.

## Non-overlap

This slice does not repeat the accepted expression-affinity `affinity2`,
`affinity3`, `types2`, `expr2`, real arithmetic, e_expr precedence, or e_expr
NULL/type batches. It owns a distinct `e_expr.test` dynamic `BETWEEN` and
`CASE` cluster through the parser-level `SQLiteSelectSql` path.

## Exclusions

Two real upstream precedence rows exposed a current parser gap and are not
counted in this handoff:

- `e_expr-13.2.4`: `6 BETWEEN 4 AND 8 == 1`
- `e_expr-13.2.7`: `5 BETWEEN 0 AND 0 != 1`

Both currently parse differently from sqlite3 when `BETWEEN` is followed by an
unparenthesized comparison in the upper-bound position. The next parser pass
should fix that precedence behavior and then admit those rows.

## Dependency closure

No new support component is needed. The batch reuses existing native
`SQLiteSelectSql` parsing/execution and the already allowed local `sqlite3`
oracle pattern used by neighboring real upstream corpus tests.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicBetweenCaseTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicBetweenCaseTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
