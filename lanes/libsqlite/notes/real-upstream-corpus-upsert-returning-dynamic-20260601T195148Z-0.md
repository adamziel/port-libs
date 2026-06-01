# Real Upstream Corpus: UPSERT RETURNING quote/is-null Dynamic

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260601T195148Z-0`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test` `returning1-17.0`: `RETURNING quote(x), x IS NULL` returns SQLite scalar quote text plus integer null-test output.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test` `returning1-4.5`: UPSERT `RETURNING` emits one row per changed input row in input order.

Patch:

- Extended the bounded `SQLiteUpsertReturningSql` RETURNING expression evaluator to handle scalar `expr IS NULL` / `expr IS NOT NULL` results and `quote(expr)` through the existing SQLite core scalar dispatcher.
- Added `SQLiteRealUpstreamUpsertReturningNullQuoteExpressionDynamicTest.php` with 250 oracle-backed input streams and 1002 focused PASS cases. The matrix varies inserted rows, repeated conflict rows, NULL payloads, empty text, and quoted text, comparing port output against local PDO SQLite for RETURNING rows, final table rows, change counts, and row order.

Non-overlap:

- Avoids existing `upsert1.test` target-first, row-value, omitted-target, trigger, fault, and literal SELECT batches.
- Avoids existing `returning1.test` dynamic tail coverage for correlated subqueries, temp triggers, writable schema, virtual tables, and recursive triggers.

Verification:

- Red-first before source change: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningNullQuoteExpressionDynamicTest.php` failed the 1000 dynamic cases with `SQLite UPSERT RETURNING literal is not supported: quote(payload)`.
- After source change: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningNullQuoteExpressionDynamicTest.php` passed `1 test files, 2002 assertions, 0 failures`.
- Adjacent check: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpsertReturningSqlTest.php` passed `1 test files, 60 assertions, 0 failures`.
- Adjacent corpus check: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningLiteralSelectDynamicTest.php` passed `1 test files, 6008 assertions, 0 failures`.
- Syntax: `php -l lanes/libsqlite/src/SQLiteUpsertReturningSql.php` and `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningNullQuoteExpressionDynamicTest.php` both reported no syntax errors.
- Guard: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed `1 test files, 8 assertions, 0 failures`.
- Status JSON: `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json valid\n";'` reported `lane-status.json valid`.
- Whitespace: `git diff --check -- lanes/libsqlite` passed with no output.

Dependency closure:

- No new support component is needed. The source change reuses the existing bounded UPSERT RETURNING SQL executor, local PDO SQLite oracle checks in the focused test, and `SQLiteCoreScalarFunction::sqlFunctionArguments('quote', ...)`.
