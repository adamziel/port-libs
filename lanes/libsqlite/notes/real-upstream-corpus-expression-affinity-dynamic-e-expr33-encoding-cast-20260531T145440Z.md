# Real Upstream Corpus: Expression Affinity Dynamic E-Expr33 Encoding Cast

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260531T145440Z-0`

Base accepted HEAD: `a187757827b58c999a1fc6cda2f4be5e163b73e9`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Scenario range: `e_expr-33.1.1` through `e_expr-33.1.5`
- Evidence rule: `R-64550-29191`, casting non-BLOB values to BLOB and BLOB values to non-BLOB values can produce different results under UTF-8, UTF-16LE, and UTF-16BE database encodings.

Patch summary:

- Added encoding-aware TEXT/BLOB cast support to `SQLiteRealExpressionAffinityCorpusPlan` by reusing existing `SQLiteEncodingCollationSourceCursor` text codecs and `SQLiteBlobValue` storage.
- Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicEExpr33EncodingCast20260531T145440ZTest.php` with 1,080 dynamic sqlite3-oracle cases:
  - 180 non-BLOB operands cast to BLOB across three encodings.
  - 180 BLOB operands cast to TEXT across three encodings.
  - Exact upstream invariants for `CAST(123 AS BLOB)`, `CAST('' AS BLOB)`, `CAST('abcd' AS BLOB)`, `CAST(X'abcd' AS TEXT)`, and `CAST(X'' AS TEXT)`.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteRealExpressionAffinityCorpusPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicEExpr33EncodingCast20260531T145440ZTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicEExpr33EncodingCast20260531T145440ZTest.php` passed: `1 test files, 2173 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Status delta:

- Adds 1,082 focused TestRunner PASS cases.
- Expected selected libsqlite evidence moves from `2927535 pass / 0 fail` to `2928617 pass / 0 fail`.
- Mapped denominator remains `1589 / 1589`; this is PASS-line growth over an already mapped upstream unit.

Non-overlap:

- Owns only `e_expr.test` `e_expr-33.1` database-encoding-sensitive TEXT/BLOB casts.
- Avoids accepted `e_expr-27`/`e_expr-28` default BLOB/TEXT casts, `e_expr-29` through `e_expr-32` numeric casts, scalar subqueries, `IN`/`BETWEEN`, `affinity3` REAL predicates, LIKE/GLOB ranges, JSON, WAL, VFS, B-tree, PRAGMA, and trigger slices.

Dependency closure:

- No new support component is needed.
- Reuses existing `SQLiteEncodingCollationSourceCursor` UTF text codecs, `SQLiteBlobValue`, existing expression affinity cast formatting, and the local hydrated sqlite3 oracle.
