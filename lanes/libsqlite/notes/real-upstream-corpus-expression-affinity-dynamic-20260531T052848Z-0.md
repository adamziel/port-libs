# real-upstream-corpus-expression-affinity-dynamic-20260531T052848Z-0

Base accepted HEAD: `e6f2f82c55065569a50189235fcdfbfbb9091c15`.

Implemented a focused real upstream expression-affinity behavior fix and
dynamic corpus shard from `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`.

Upstream source truth:

- `expr-2.26` and `expr-2.26b`: huge REAL multiplication followed by `*0.0`
  yields a NaN at the host-language level, but SQLite stores/evaluates it as
  SQL `NULL`; `coalesce()` then returns the fallback and `typeof()` reports
  `null`.

Change:

- `SQLiteSelectExpression::numericValue()` now normalizes float `NAN` results
  to SQL `NULL`, preserving existing `INF` REAL behavior.
- Added `SQLiteRealUpstreamExpressionAffinityRealNanDynamic20260531Test.php`.
- The new test uses local `sqlite3` only as an oracle and compares
  `quote(...)`, `typeof(...)`, and `IS NULL` for 10,000 dynamic
  REAL/zero/wrapper expression cases plus source/provenance guards.

Non-overlap:

- This owns the upstream `expr.test` `expr-2.26` / `expr-2.26b`
  NaN-to-NULL REAL expression behavior.
- It avoids accepted overflow promotion, explicit floating-point text, real
  truth, `types2`/`types3`, CASE affinity, LIKE/GLOB, expression `ORDER BY`,
  JSON, WAL, B-tree, VFS, and metadata-only suite admission clusters.

Dependency closure:

- No new support component is needed. The slice reuses native
  `SQLiteSelectSql` expression dispatch and the local `sqlite3` oracle pattern
  already used by adjacent real-upstream corpus tests.

Verification:

- Red-first check: the dynamic behavior exposed PHP `NAN` values surviving as
  `typeof(...) = real` instead of SQLite SQL `NULL`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityRealNanDynamic20260531Test.php`
  initially passed all 10,000 dynamic behavior cases and failed only the stale
  ownership count guard (`1000` expected vs `10000` actual); rerun pending after
  count correction.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityRealNanDynamic20260531Test.php`
  passed with `1 test files, 40012 assertions, 0 failures`.
- `php -l lanes/libsqlite/src/SQLiteSelectExpression.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityRealNanDynamic20260531Test.php`
  passed.
- `git diff --check -- lanes/libsqlite` passed.
- The generic no-domain API guard test is not present on this accepted base.
