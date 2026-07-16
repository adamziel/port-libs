Status: focused real-upstream expression-affinity corpus coverage for SQLite `test/exprfault.test`.

Source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/exprfault.test`
- `exprfault-1.1`: scalar subquery through a derived source under an empty outer rowset returns an empty result.
- `exprfault-2`: `SELECT hex ( unhex('ABCDEF') );` preserves blob decoding/encoding through scalar expression dispatch.
- `exprfault-3`: expression-index update body uses `randomblob(500)` and `hex(b)` expression evaluation; this slice covers the scalar expression pieces without attempting SQLite's Tcl OOM fault simulator.

Added coverage:
- `lanes/libsqlite/src/SQLiteSelectSql.php` now accepts optional whitespace between a scalar function name and `(` in SELECT expressions, matching the upstream `hex ( unhex(...) )` spelling.
- `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicExprfault20260601T095205ZTest.php`
- 1000 dynamic `hex(unhex(...))` SQL projection cases across distinct byte payloads, lowercase input, ignored-space input, and ignored-punctuation input.
- 1 exact empty-outer-rowset scalar-subquery case from `exprfault-1.1`.
- 17 `randomblob()` expression-size cases including the upstream `randomblob(500)` size.

Non-overlap:
- Does not repeat accepted e_expr parameter binding, LIKE/GLOB callback dispatch, MATCH/REGEXP dispatch, CASE/iif, CAST, scalar subquery membership, expression ORDER BY, expridx2 write-elision, randexpr1, modulo-cast, JSON, WAL, VFS, B-tree, PRAGMA, trigger, row-value, or source-neutral cleanup batches.
- No production API names were added, and no domain-specific names were introduced.

Dependency closure:
- No new support component needed; this reuses `SQLiteSelectSql` scalar projection and empty-rowset execution, `SQLiteCoreScalarFunction` `hex`, `unhex`, and `randomblob` dispatch, and hydrated upstream `exprfault.test` source-truth evidence.

Verification:
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php && php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicExprfault20260601T095205ZTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicExprfault20260601T095205ZTest.php`: 1 test files, 1029 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicExprfault20260601T095205ZTest.php lanes/libsqlite/tests/SQLiteSelectSqlCoreScalarFunctionCorpusTest.php`: 2 test files, 1101 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: 1 test files, 5 assertions, 0 failures.
- `php -r '$path="lanes/libsqlite/lane-status.json"; json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`: lane-status json ok.
- `git diff --check -- lanes/libsqlite`: clean.
