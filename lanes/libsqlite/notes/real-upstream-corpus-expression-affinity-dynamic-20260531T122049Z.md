# Real Upstream Corpus: Expression Affinity Dynamic 20260531T122049Z

Base accepted HEAD: `82ffc15bcb109224eed304cd069ec63109a1767a`

Owned upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test` `e_expr-27.2`: `CAST(NULL AS ...)` remains NULL.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test` `e_expr-27.3`: BLOB-like type names use BLOB/no-affinity cast behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test` `e_expr-27.4`: non-BLOB values cast to BLOB through the connection text representation.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test` `e_expr-28.1`: BLOB values cast to TEXT through the connection text representation.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test` `e_expr-28.2`: INTEGER and REAL values cast to TEXT render like sqlite3 formatting, including two-digit scientific exponents.

Implementation delta:

- `SQLiteSelectExpression::realTextValue()` now normalizes one-digit scientific exponents when REAL values are rendered as TEXT. The motivating upstream parity case was `CAST(-2.3e-5 AS TEXT)`, where sqlite3 returns `'-2.3e-05'` and the port previously returned `'-2.3e-5'`.
- Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicBlobTextCast20260531T122049ZTest.php` with 1000 dynamic parser-level `CAST(...)` cases. The file hydrates expected `quote()` and `typeof()` results from local `sqlite3` and compares the PHP port through `SQLiteSelectSql`.

Non-overlap:

- Owns only parser-level `e_expr.test` BLOB/TEXT CAST rows and REAL-to-TEXT exponent formatting.
- Avoids accepted `atof1` decimal REAL, `types.test` record storage, scalar subquery arity, affinity3 REAL predicates, IN/BETWEEN, CASE/iif, LIKE/GLOB, JSON, WAL, VFS, B-tree, PRAGMA, trigger, and row-value DML slices.
- Excludes `CAST(... AS NONE)` because the current accepted lane has separate historical coverage for that target spelling; this handoff uses BLOB-containing type names from the upstream BLOB-affinity rule instead.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteSelectExpression.php`
  `No syntax errors detected in lanes/libsqlite/src/SQLiteSelectExpression.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicBlobTextCast20260531T122049ZTest.php`
  `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicBlobTextCast20260531T122049ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicBlobTextCast20260531T122049ZTest.php`
  `1 test files, 7008 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicBlobTextCast20260531T122049ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityRealCastPrefix20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityCastDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicRealOracleTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  `5 test files, 15960 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  passed

Expected movement:

- Focused PASS-line delta: `+1002` selected TestRunner PASS cases.
- `lane-status.json` `phpPass`: `2908763 -> 2909765`.
- Mapped coverage remains `1589 / 1589`; denominator was already fully mapped.

Dependency closure:

- No new support component needed. This reuses `SQLiteSelectSql` parser-level CAST dispatch, `SQLiteBlobValue` storage, `quote()`/`typeof()` scalar helpers, and the hydrated local `sqlite3` oracle.
