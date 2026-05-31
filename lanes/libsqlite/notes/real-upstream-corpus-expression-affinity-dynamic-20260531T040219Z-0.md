# Real Upstream Corpus Expression Affinity Dynamic

Session: `port-dev-sqlite-yield-dyn-real-expr-20260531T040219Z`

Base accepted HEAD: `86b40e76030ee95766e1bca45c19abb4f5a3c27f`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`
- Upstream scenario range: `affinity2-100` through `affinity2-300`

Coverage added:

- New focused test file: `SQLiteRealUpstreamExpressionAffinityDynamicMatrixTest.php`
- Ports the upstream storage-affinity and comparison-affinity behavior for `INTEGER`, `REAL`, `NUMERIC`, `BLOB`/none, and `TEXT` columns.
- Widens the upstream comparison family across 12 inserted literal forms, 5 affinity columns, 6 comparison operators, and explicit unary/cast affinity edges.
- Focused movement: `1,897` real PHP TestRunner PASS cases, `5,694` assertions.

Non-overlap:

- Does not repeat accepted `expr.test` REAL arithmetic, int64 overflow arithmetic, NULL comparison, flexnum, lossy-cast, or real-expr2 shards.
- This owns the `affinity2.test` storage/conversion and comparison-affinity matrix through the SELECT SQL executor.

Dependency closure:

- No new support component is needed. The test reuses the existing bounded `SQLiteSelectSql` executor and the local `sqlite3` oracle already used by real upstream expression shards.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicMatrixTest.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicMatrixTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
