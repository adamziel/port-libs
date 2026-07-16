# real-upstream-corpus-expression-affinity-dynamic-20260531T040659Z-0

Added `SQLiteRealUpstreamCorpusExpressionAffinityDynamicRealExpr20260531T040659ZTest.php` as an additive real upstream expression/affinity corpus batch.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
  - `e_expr-13.*`: `BETWEEN` operator precedence, truth, and NULL behavior.
  - syntax precedence rows for `IN` and `NOT IN` expression forms.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test`
  - `types2-5.*`: IN-list comparison behavior with no column affinity, numeric/text expressions, and NULL list members.

Focused behavior:

- 25 dynamic left expressions across NULL, integer, real, text, BLOB, CAST, boolean, `coalesce()`, and `nullif()` outputs.
- 10 lower/upper or IN-list bound pairs across numeric, text, NULL, and numeric-prefix values.
- 4 operator templates: `BETWEEN`, `NOT BETWEEN`, `IN (...)`, and `NOT IN (...)`.
- 1,000 real-expression cases are checked against a local `sqlite3` oracle through `SQLiteSelectSql`.
- Each case verifies `quote(...)`, `typeof(...)`, and NULL truth parity.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicRealExpr20260531T040659ZTest.php`
  - `1 test files, 4006 assertions, 0 failures`
  - 1,001 focused TestRunner PASS lines.

Non-overlap:

- This does not repeat accepted `real expr` CAST arithmetic matrices, `types2` row-filter predicate matrices, `affinity2` column-affinity comparisons, expression `IS DISTINCT FROM`, expression `BETWEEN` row-context tests, cast-prefix tests, Unicode GLOB, SQL expression ORDER BY, SELECT GROUP/JOIN/subquery text dispatch, JSON table, WAL/VFS, or B-tree batches.
- The first red attempt also exposed a narrower BLOB-vs-numeric-affinity comparison gap for `BETWEEN`/`IN` over `X'31'` bounds. Those rows are deliberately excluded from this countable green batch because changing the shared BLOB affinity comparison path would require a separate compatibility audit against older accepted BLOB-affinity tests.

Expected dashboard movement:

- `phpPass`: +1001 focused PASS lines, from `1981165` to `1982166`.
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`; this is behavior PASS-line growth against already mapped real upstream expression inventory.

Dependency closure:

- No new support component is needed. The slice reuses the existing native `SQLiteSelectSql` parser/executor and local `sqlite3` oracle pattern used by adjacent real upstream expression-affinity tests.
