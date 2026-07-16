# real-upstream-corpus-json1-jsonb-dynamic-20260531T121018Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- Upstream section `json101-17.1`: empty `t1`/`t2` with `SELECT * FROM t1 LEFT JOIN t2 ON (SELECT b FROM json_each ORDER BY 1)` returning no rows.

Patch:

- `SQLiteSelectSql::predicate()` now preserves parenthesized scalar `SELECT` and `VALUES` expressions as truth predicates instead of unwrapping them into unsupported bare `SELECT ...` expressions.
- `SQLiteSelectResult::leftJoin()` now returns an empty result immediately when the left side is empty, matching the upstream `json101-17.1` empty-left boundary before right-side NULL-extension columns are needed.
- Added `SQLiteRealUpstreamJson101JoinOnSubqueryDynamic20260531Test.php` with the exact upstream empty case plus 1000 dynamic JSON1/JSONB cases covering `json_each(...)`, `json_each(jsonb(...))`, and bare `json_each` scalar-subquery NULL behavior under `LEFT JOIN ... ON`.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101JoinOnSubqueryDynamic20260531Test.php`
- Result: `1 test files, 9005 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101JoinOnSubqueryDynamic20260531Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- Result: `2 test files, 9008 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
- Result: `No syntax errors detected`
- `php -l lanes/libsqlite/src/SQLiteSelectResult.php`
- Result: `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson101JoinOnSubqueryDynamic20260531Test.php`
- Result: `No syntax errors detected`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "lane-status.json OK\n";'`
- Result: `lane-status.json OK`
- `git diff --check -- lanes/libsqlite`
- Result: clean
- PASS movement: `+1003` focused TestRunner PASS cases, moving lane selected evidence from `2906760` to `2907763` pass / `0` fail.
- Mapped denominator movement: none; mapped coverage remains `1589 / 1589`.

Non-overlap:

- This owns only upstream `json101-17.1` scalar subquery truth evaluation inside a `LEFT JOIN ... ON` predicate over `json_each ORDER BY`.
- It avoids existing JSON table cursor/source/hidden/visible constraint slices, `json101-15` parenthesized `JSON_EACH` alias-star coverage, `json101-13` correlated `json_each` planning, JSON mutation/aggregate/window batches, SELECT subquery arity coverage, and generic LEFT JOIN JSON table lateral null-extension tests.

Dependency closure:

- No new support component is needed. The slice reuses native `SQLiteSelectSql`, `SQLiteSelectResult`, JSON table source planning, and JSONB scalar function dispatch.

Root harness:

- Not run - isolated micro-slice.
