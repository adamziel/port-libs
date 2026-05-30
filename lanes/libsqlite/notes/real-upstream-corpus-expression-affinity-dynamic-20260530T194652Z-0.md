# real-upstream-corpus-expression-affinity-dynamic-20260530T194652Z-0

Base accepted HEAD: `4fa72fa71b26a19fe54f9ce85268cd96396282ab`.

This slice adds `SQLiteRealUpstreamExpressionBetweenDynamicTest.php`, a real
upstream expression/affinity dynamic corpus shard backed by the hydrated
SQLite upstream checkout:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
  - `expr-1.86` through `expr-1.95`: `BETWEEN` and `NOT BETWEEN` with NULL
    lower/upper boundary propagation.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
  - selected currently-supported `e_expr-13.2.*` precedence forms:
    `e_expr-13.2.1`, `e_expr-13.2.10`, `e_expr-13.2.25`, and `e_expr-13.2.28`.

Focused coverage:

- `16 x 16 x 16 x 2 = 8192` literal-storage matrix cases over NULL, integer,
  real, text numeric, leading-zero text, empty text, and alpha text values.
- 4 selected real upstream precedence checks.
- 1 ownership/citation check.
- Verified focused output: `8197` PASS lines, `8201` assertions, `0` failures.

Non-overlap:

- This does not repeat the accepted expression-affinity `affinity2`, `types2`,
  `numcast`, cast-large, null-comparison, operator-precedence, date-affinity,
  SQL expression `ORDER BY`, grouped SELECT text, JSON, WAL, VFS, B-tree, or
  source-neutral cleanup surfaces. It owns the `expr.test` `BETWEEN`/`NOT
  BETWEEN` NULL-boundary matrix widened across literal storage classes.

Exclusions/follow-up:

- The first red run also tried broader `e_expr-13.2` forms and exposed existing
  parser precedence gaps for upper-bound comparison expressions and `AND`
  association around `BETWEEN`. Those failing scenarios are intentionally not
  hidden inside this passing corpus batch. A next parser-focused slice should
  fix and admit `e_expr-13.2.4`, `e_expr-13.2.7`, `e_expr-13.2.19`, and
  `e_expr-13.2.22`.

Dependency closure:

- No new support component is needed. The test reuses the existing
  `SQLiteSelectSql` constant SELECT expression executor and `sqlite3` as the
  local oracle for real upstream behavior.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionBetweenDynamicTest.php`
  - `1 test files, 8201 assertions, 0 failures`
  - selected PASS lines: `8197`
