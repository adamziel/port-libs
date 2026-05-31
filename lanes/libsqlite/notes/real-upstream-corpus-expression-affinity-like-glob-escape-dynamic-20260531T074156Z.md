# real-upstream-corpus-expression-affinity-like-glob-escape-dynamic-20260531T074156Z

## Scope

- Added a dynamic upstream-backed PHP corpus for SQLite expression LIKE/GLOB behavior.
- Source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
  - `expr-5.54` through `expr-5.79`: NULL propagation, `LIKE`, `NOT LIKE`, and `ESCAPE` behavior.
  - `expr-6.1` through `expr-6.75`: `GLOB`, `NOT GLOB`, wildcard, bracket/range, negated bracket, case-sensitive, and NULL behavior.
- The PHP test expands those real upstream operator shapes over 2268 ASCII subject/pattern/operator cases and compares `quote()`, `typeof()`, and NULL-ness against a local `sqlite3` oracle.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityLikeGlobEscapeDynamic20260531Test.php`
  - `1 test files, 11346 assertions, 0 failures`
  - 2269 PASS lines: 2268 dynamic cases plus one ownership/countability guard.

## Non-Overlap

- This does not repeat accepted Unicode GLOB range work, accepted expression precedence, real arithmetic, integer-boundary, CASE lazy, `IS DISTINCT`, row-context, or expression-index mismatch slices.
- The slice specifically owns `expr.test` LIKE/GLOB NULL and ESCAPE/bracket-pattern behavior via `SQLiteSelectSql` execution.

## Dependency Closure

- No new support component is needed. The slice reuses the existing native `SQLiteSelectSql` expression executor and the existing local `sqlite3` oracle pattern already used by adjacent real upstream expression tests.
