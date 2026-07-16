# real-upstream-corpus-expression-affinity-dynamic-20260531T022701Z-0

Added `SQLiteRealUpstreamExpressionAffinityDynamicRealArithmeticTest.php` as a non-overlapping real upstream expression/affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test`

Covered scenarios:

- `affinity3.test` `affinity3-110` through `affinity3-142`: REAL affinity survives arithmetic such as `apr / 100` and keeps result storage classes stable through view/join-shaped expression use.
- `cast.test` `cast-9.*` and `cast-10.*`: `CAST(... AS NUMERIC)` and `CAST(... AS REAL)` preserve integer-vs-real dynamic result classes through SELECT expression contexts.

Focused coverage:

- `1000` dynamic sqlite3-oracle expression cases plus one ownership/provenance case.
- Each dynamic case executes through native `SQLiteSelectSql` and checks `quote(...)`, `typeof(...)`, and NULL-state parity against the local `sqlite3` oracle.
- Focused PASS growth: `+1001` TestRunner cases / PASS lines and `4007` assertions.

Non-overlap:

- This does not repeat the existing `types2.test` predicate-affinity matrix, `e_expr.test` unbound parameter NULL propagation, `e_expr-29..32` CAST prefix/range corpus, expression ORDER BY, grouped SELECT text, Unicode GLOB, LIKE/GLOB predicate metadata, or metadata-only runner admission rows.
- Mapped denominator remains unchanged because the upstream manifest is already complete; this handoff should count as PHP PASS-line/assertion growth only.

Dependency closure:

- No new support component is needed. The slice reuses existing native `SQLiteSelectSql` expression execution, scalar `CAST`, arithmetic/comparison dispatch, `quote()`, and `typeof()` behavior.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicRealArithmeticTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicRealArithmeticTest.php`
- `git diff --check -- lanes/libsqlite`
