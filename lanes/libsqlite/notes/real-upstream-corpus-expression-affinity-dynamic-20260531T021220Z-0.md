# real-upstream-corpus-expression-affinity-dynamic-20260531T021220Z-0

Session: `port-dev-sqlite-yield-dyn-real-expr-20260531T021220Z`
Base accepted HEAD: `b8677cf94d5b050eacc055d83ba1f29b3739b6f1`

Added `SQLiteRealUpstreamExpressionAffinityPrecedenceMatrixDynamicTest.php`
as an additive real upstream expression/affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
  - `e_expr-1.*`: supported binary operator precedence matrix over the 17
    upstream operand triples.

Behavior:

- Fixes parser-level SELECT expression precedence so `||` binds tighter than
  `*`, `/`, and `%`, matching SQLite's `e_expr-1` matrix.
- Adds projection-expression parsing for top-level `AND` and `OR` so the
  upstream precedence matrix can run through `SQLiteSelectSql`, not only direct
  predicate helpers.
- Prevents comparison scanning from misreading `<<` and `>>` as `<` or `>`.
- Corrects the directly coupled accepted expectation for upstream `e_expr-6.5`
  to `2.0`, matching the hydrated upstream test.

Focused coverage:

- `9,793` focused TestRunner PASS cases.
- `48,966` focused assertions in the new test file.
- Expected `phpPass` movement: `1,638,574 -> 1,648,367`.
- Mapped denominator movement: none. The upstream inventory is already mapped;
  this is countable PHP behavior growth over hydrated upstream behavior.

Verification:

- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityPrecedenceMatrixDynamicTest.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityPrecedenceMatrixDynamicTest.php`
  - `1 test files, 48966 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamic0Test.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityPrecedenceMatrixDynamicTest.php`
  - `4 test files, 72040 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed

Non-overlap:

- This shard targets parser-level `e_expr-1` operator precedence over the full
  supported operator-pair matrix.
- It does not repeat accepted expression overflow arithmetic, real-prefix
  casts, `types2`, `types3`, `affinity2`, `affinity3`, CASE/iif, NULL/coalesce,
  BETWEEN, LIKE/GLOB, expression `ORDER BY`, grouped SELECT, JSON, WAL, VFS,
  pager, B-tree, trigger, date, pragma, or metadata-only suite admission.

Dependency closure:

- No new support component is needed. The slice reuses the native
  `SQLiteSelectSql` parser/executor and local `sqlite3` only as an oracle for
  hydrated upstream expected values.
