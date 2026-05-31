# real-upstream-corpus-expression-affinity-dynamic-20260531T104758Z-0

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/existsexpr.test`.
- Ported sections: `existsexpr-3.1` through `existsexpr-3.9`, plus explicit collation-placement cases `existsexpr-4.1.1`, `existsexpr-4.1.2`, `existsexpr-4.2`, and `existsexpr-4.4`.
- Added `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicExistsExpr20260531T104758ZTest.php`.

Behavior covered:

- 900 dynamic correlated `EXISTS` cases across 100 numeric row-set seeds, covering composite equality, scalar `min()` / `max()` terms, outer-column filters, arithmetic correlated terms, aggregate subquery row presence, outer expression references inside a subquery, and two-source subqueries.
- 300 dynamic explicit `COLLATE` cases across 60 text row-set seeds, covering left/right `COLLATE nocase`, binary placement, and case-sensitive versus case-insensitive row admission.
- Each case runs the same SQL through `SQLiteSelectSql::execute()` and a local `sqlite3` oracle script, then compares ordered result rows.

Non-overlap:

- This does not repeat the existing `e_expr.test` `e_expr-34` scalar `EXISTS` result matrix, `e_expr-35` / `e_expr-36` scalar subquery matrix, expression shift-boundary coverage, CAST-prefix and REAL conversion shards, LIKE/GLOB coverage, expression ORDER BY, grouped SELECT text, or accepted SELECT subquery text batches.
- This owns only upstream `existsexpr.test` correlated row-admission and explicit collation placement for generic application row sets.

Status delta:

- Focused TestRunner movement: +1201 PASS cases.
- Focused assertion evidence: `1 test files, 1209 assertions, 0 failures`.
- `lane-status.json` `phpPass` moves from `2882847` to `2884048` if accepted.
- Mapped coverage remains `1589 / 1589`; this is PASS-line corpus growth over an already mapped upstream file.

Dependency closure:

- No new support component is needed. The slice reuses the existing `SQLiteSelectSql` executor and the local `sqlite3` oracle for test generation.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicExistsExpr20260531T104758ZTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicExistsExpr20260531T104758ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusExpressionAffinityDynamicExistsExpr20260531T104758ZTest.php`
  - `1 test files, 1209 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -r '$data = json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status.json OK" . PHP_EOL;'`
  - `lane-status.json OK`
- `git diff --check -- lanes/libsqlite`
  - no output
