# real-upstream-corpus-select-core-dynamic-20260530T234100Z-0

Slice: `real-upstream-corpus-select-core-dynamic-20260530T234100Z-0`

Base accepted HEAD: `1e28a5dbe5f8813a907a64ec2d403f8339418de7`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectG.test`
- `selectG-110`: scalar `VALUES` expression with 100,000 rows returns the first row value.
- `selectG-120`: only the left-most term of a multi-valued scalar `VALUES` expression is evaluated.

Implementation:

- Added scalar parenthesized `VALUES` expression support in `SQLiteSelectSql`.
- The parser now reuses the existing bounded `executeValuesClause()` path and returns a literal expression for the first row / single column, matching SQLite scalar-subquery behavior for the `selectG` shape.
- Multi-column scalar `VALUES` expressions are rejected because a scalar expression must produce one column.

Focused coverage:

- Added `SQLiteRealUpstreamSelectGScalarValuesDynamicTest.php`.
- The file contributes 1003 focused TestRunner PASS cases:
  - 1 hydrated-source citation case.
  - 1 baseline `SELECT (VALUES (1),(2),(3)) AS v` case.
  - 1000 distinct dynamic `selectG-110` scalar `VALUES` variants with varying first values and row counts.
  - 1 malformed two-column scalar `VALUES` rejection case.
- Assertion count is 6009 in the focused run.

Non-overlap:

- This owns `selectG.test` scalar `VALUES` expression behavior only.
- It does not repeat prior `select1` through `select6`, `select8`, `select9`, `selectA` through `selectF`, `selectH`, `selectC` alias-resolution, `selectD` parenthesized join, grouped SELECT text, expression ORDER BY, JSON table source/cursor/constraint work, or metadata-only runner rows.
- Mapped denominator remains unchanged because the upstream manifest is already complete.

Dependency closure:

- No new support component is needed; the change reuses existing native PHP SELECT expression parsing and the existing bounded `VALUES` clause evaluator.

Verification:

- Red probe before fix: `php -r 'require "tools/bootstrap.php"; use PortLibs\LibSqlite\SQLiteSelectSql; var_export(SQLiteSelectSql::execute("SELECT (VALUES (1),(2),(3)) AS v", []));'` failed with `SQLite SELECT SQL expression VALUES (1) is not supported`.
- Focused verification after fix:
  - `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectGScalarValuesDynamicTest.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectGScalarValuesDynamicTest.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `git diff --check -- lanes/libsqlite`
