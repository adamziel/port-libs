# real-upstream-corpus-expression-affinity-dynamic-20260531T001032Z-0

Added `SQLiteRealUpstreamExpressionAffinitySignedLiteralDynamicTest.php` as an additive real upstream expression/affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- Scenario ranges: `e_expr-12.1` signed-number syntax diagram and `e_expr-12.2` literal-value syntax diagram. The volatile `CURRENT_TIME`, `CURRENT_DATE`, and `CURRENT_TIMESTAMP` rows are excluded because upstream pins the Tcl harness clock.

Focused movement:

- 3,723 oracle-backed `SQLiteSelectSql` PASS cases.
- The batch compares native parser/executor output with local `sqlite3` for `quote()`, `typeof()`, signed-number unary forms, numeric addition coercion, escaped string literals, BLOB literal casing, and NULL literal predicates. It also fixes `quote()` float formatting so single-digit scientific exponents match SQLite's zero-padded form, for example `e-06`.

Non-overlap:

- This avoids accepted `types2`, `types3`, `affinity2`, `affinity3`, CAST target-affinity, host-parameter, `expr2` truthiness, LIKE/GLOB, BETWEEN/CASE, expression `ORDER BY`, grouped SELECT, JSON table, VFS/WAL/B-tree, and source-neutral API cleanup batches.
- The new surface is the upstream `e_expr-12` syntax-diagram literal family executed through parser-level `SQLiteSelectSql`, not metadata-only runner admission.

Dependency closure:

- No new support component is needed. The shard reuses existing `SQLiteSelectSql` expression parsing/execution and the adjacent real-upstream sqlite3 oracle pattern.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinitySignedLiteralDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinitySignedLiteralDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinitySignedLiteralDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
