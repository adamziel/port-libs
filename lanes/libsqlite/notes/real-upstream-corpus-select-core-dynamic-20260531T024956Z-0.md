# real-upstream-corpus-select-core-dynamic-20260531T024956Z-0

Added `SQLiteRealUpstreamSelect2WhereExpressionDynamicTest.php` as an additive
real upstream SELECT core corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select2.test`
- `select2-4.1`: scalar `max(a,b)` in a cross-join `WHERE` predicate.
- `select2-4.2` and `select2-4.3`: bare numeric column truth and `NOT` truth in
  `WHERE`.
- `select2-4.4` and `select2-4.5`: scalar `min(a,b)` truth and negated truth in
  `WHERE`.
- `select2-4.6` and `select2-4.7`: searched `CASE` predicates in `WHERE`,
  including the upstream no-`ELSE` NULL-false branch and inverted `ELSE`
  branch.

Behavior change:

- `SQLiteSelectSql::predicate()` now recognizes a full searched/simple `CASE`
  expression as a truth-tested predicate before scanning for comparison
  operators inside `WHEN` clauses. Before this patch,
  `WHERE CASE WHEN a=b-1 THEN 1 END` split on the inner `=` and threw
  `SQLite SELECT SQL CASE expression is unterminated`.

Focused selected movement:

- New local TestRunner PASS cases: `1268`.
- New behavior assertions: `7599`.
- Expected selected `phpPass` movement when admitted: `+1268`, from `1742742`
  to `1744010`.
- Mapped denominator remains `1589 / 1589`; `select2.test` is already present
  in the hydrated upstream manifest.

Non-overlap:

- This owns the residual `select2-4.1..4.7` WHERE-expression truth cluster.
- It does not repeat accepted `select6` derived tables, `select7` CASE GROUP
  BY, `select8` LIMIT/OFFSET, `selectC` alias resolution, `selectD` nested
  joins, grouped SELECT text, expression `ORDER BY`, JSON table cursor/source/
  constraint work, or metadata-only runner rows.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect2WhereExpressionDynamicTest.php`
  -> `1 test files, 7599 assertions, 0 failures`.
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelect2WhereExpressionDynamicTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure:

- No new support component is needed. This reuses existing `SQLiteSelectSql`
  and `SQLiteSelectExpression` SELECT execution support.
