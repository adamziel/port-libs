Status: focused real-upstream expression-affinity corpus coverage for SQLite postfix NULL predicates.

Source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
- `expr.test` `expr-3.25` through `expr-3.28b`: `ISNULL`, `NOTNULL`, `IS NULL`, and `IS NOT NULL` over nullable expressions.
- `expr.test` `expr-7.14`, `expr-7.15`, `expr-7.23`, `expr-7.26`, and `expr-7.27`: postfix NULL predicates inside WHERE truth-composition.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
- `e_expr.test` `e_expr-12.3.70` through `e_expr-12.3.72`: syntax productions `EXPR ISNULL`, `EXPR NOTNULL`, and `EXPR NOT NULL`.

Added coverage:
- `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicPostfixNull20260601T132418ZTest.php`
- 1728 sqlite3-oracle-backed projection cases across 12 source rows, 12 expression forms, 6 postfix NULL predicate spellings, and 2 result projections.
- 5 exact upstream WHERE cases from `expr-7.*` covering `ISNULL`/`NOTNULL` inside `AND`/`OR` composition.
- 1734 focused TestRunner PASS cases total including the ownership/source-truth assertion case.

Non-overlap:
- Does not repeat accepted e_expr parameter binding, null-aware `IS`/`IS NOT` binary comparisons, CASE/iif, CAST-only, scalar subquery, expression ORDER BY, modulo-cast, random-expression fault, JSON, WAL, VFS, B-tree, PRAGMA, trigger, row-value, PDO, or source-neutral cleanup batches.
- No production API names were added, and no domain-specific names were introduced.

Dependency closure:
- No new support component needed; this reuses `SQLiteSelectSql` projection, WHERE predicate, scalar function, CASE, CAST, and row-array execution with the local `sqlite3` oracle and hydrated upstream source-truth files.

Verification:
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicPostfixNull20260601T132418ZTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicPostfixNull20260601T132418ZTest.php`: 1 test files, 3471 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicPostfixNull20260601T132418ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionNullComparisonDynamicTest.php`: 2 test files, 4628 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: 1 test files, 6 assertions, 0 failures.
- `php -r '$path="lanes/libsqlite/lane-status.json"; json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`: lane-status json ok.
- `git diff --check -- lanes/libsqlite`: clean.
