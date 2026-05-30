# bulk-upstream-veryquick-shard-expansion-dynamic-20260530T174209Z-0

Base accepted HEAD: `e12ceba2fd83282957420709bd781aee710bc7ca`.

Implemented a real upstream behavior batch from the hydrated SQLite source file
`/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`.

Focused upstream range:

- `e_expr-1.*` binary operator precedence matrix for the supported arithmetic
  and bitwise subset: `*`, `/`, `%`, `+`, `-`, `>>`, `&`, `|`.
- First 16 upstream A/B/C value rows from the Tcl matrix.
- Excluded red follow-ups found during the first local run: concatenation
  precedence around `||`, left-shift `<<` parser ambiguity, and negative
  bitwise subtraction/or rows.
- `e_expr-2.1` through `e_expr-2.3` unary `-`, `+`, and `~`.
- `e_expr-3.1` through `e_expr-3.4` and `e_expr-3.6` unary-plus scalar/NULL
  behavior.

Countable movement:

- Real upstream behavior PASS cases: `1000`.
- Focused TestRunner output: `1 test files, 1006 assertions, 0 failures`.
- PHP PASS-line growth for this new test file: `1001` selected PASS lines
  (`1000` behavior cases plus one ownership/count check).
- Mapped denominator rows: unchanged; this is PASS-line growth only.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamEExprPrecedenceBulkTest.php`
  passed with `1 test files, 1006 assertions, 0 failures`.

Dependency closure:

- Reused existing `SQLiteSelectSql` and `SQLiteSelectExpression` support.
- Used the local `sqlite3` CLI as an oracle for `quote(...)` expected values,
  matching prior accepted differential-oracle practice.
- No new support component is needed.
