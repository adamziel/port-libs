# Real Upstream Corpus: Expression Affinity REAL Literal Dynamic

Slice: `real-upstream-corpus-expression-affinity-dynamic-20260530T224309Z-0`

Base accepted HEAD: `dc9a740fd34e07dba61e9143b3604d183ad170bf`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Sections cited by the test: `e_expr-3.*`, `e_expr-6.*`, `e_expr-7.*`, `e_expr-10.1/10.2`, and `e_expr-12.1/12.2`.

Behavior added:

- Added `SQLiteRealUpstreamExpressionAffinityRealLiteralDynamicTest.php`.
- The test cross-products 27 REAL literal spellings with 40 unary, arithmetic, parenthesized, and comparison expression forms.
- Each case compares PHP `SQLiteSelectSql` execution against local `sqlite3` for `typeof(expr)` and `CAST(expr AS TEXT)`.
- Focused PASS cases added: 1081 PASS lines, including 1080 generated behavior cases and one ownership/citation case.
- Focused assertion count: 1084 assertions.

Non-overlap:

- Does not repeat accepted `types2` affinity column matrices, `affinity2` unary-plus column comparison behavior, `affinity3` REAL view/join affinity, e_expr NULL comparison logic, or BETWEEN/NOT BETWEEN affinity coverage.
- This shard owns literal REAL expression parsing/evaluation and numeric expression storage-class/text-cast parity only.

Dependency closure:

- No new support component needed. The slice reuses the hydrated upstream SQLite test checkout for source truth, local `sqlite3` as oracle, and the existing PHP `SQLiteSelectSql` executor.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityRealLiteralDynamicTest.php`
  - `1 test files, 1084 assertions, 0 failures`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityRealLiteralDynamicTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output
