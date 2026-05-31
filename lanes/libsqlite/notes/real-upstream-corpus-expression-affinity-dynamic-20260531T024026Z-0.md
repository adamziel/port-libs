# real-upstream-corpus-expression-affinity-dynamic-20260531T024026Z-0

Session: `port-dev-sqlite-yield-dyn-real-expr-20260531T024026Z`
Base accepted HEAD: `47e43ea345c857243140b52082e7a664319c5aa0`

Added `SQLiteRealUpstreamExpressionAffinitySyntaxDiagramDynamicTest.php` as
an additive real upstream expression/affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
  - `e_expr-12.3`: expression syntax diagram forms.

Behavior:

- Exercises parser-level `SQLiteSelectSql` expression execution over upstream
  syntax forms including column references, unary and binary operators,
  `CAST`, `COLLATE`, `LIKE`/`GLOB`, `BETWEEN`, `IN`, `CASE`, scalar functions,
  boolean precedence, and expression composition.
- Expands the upstream syntax forms across 16 dynamic row values covering
  NULL, integer, REAL, numeric text, plain text, and BLOB storage classes.
- Uses local `sqlite3` only as an oracle for expected `quote()`, `typeof()`,
  and `IS NULL` results. No generated fake upstream script ids were added.

Focused coverage:

- `1,009` focused TestRunner PASS cases.
- `4,038` focused assertions.
- Expected `phpPass` movement: `1,726,669 -> 1,727,678`.
- Mapped denominator movement: none. The upstream inventory is already mapped;
  this is countable PHP behavior growth over hydrated upstream behavior.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinitySyntaxDiagramDynamicTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinitySyntaxDiagramDynamicTest.php`
  - `1 test files, 4038 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed

Non-overlap:

- This shard targets `e_expr-12.3` expression syntax-diagram forms through
  dynamic storage-class rows.
- It avoids accepted `e_expr-1` precedence matrix, `e_expr-7` result storage
  matrix, `e_expr-8` NULL comparison, `e_expr-9` collation, `e_expr-11`
  parameter-token, BETWEEN/CASE dynamic batches, casts, `types2`, `types3`,
  `affinity2`, `affinity3`, expression ORDER BY, grouped SELECT, JSON, WAL,
  VFS, pager, B-tree, trigger, date, pragma, and suite-admission surfaces.
- Unsupported postfix `ISNULL`/`NOTNULL` syntax and scalar `abs()` numeric
  return-class gaps were intentionally excluded for a future behavior-fix
  slice rather than weakening this corpus.

Dependency closure:

- No new support component is needed. The slice reuses native
  `SQLiteSelectSql`, `SQLiteSelectExpression`, `SQLiteBlobValue`, and the
  existing focused TestRunner. The `sqlite3` binary is used only as a local
  oracle for hydrated upstream expected values.
