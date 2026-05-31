# real-upstream-corpus-expression-affinity-dynamic-20260531T010128Z-0

Accepted base: `e307a7e809c115b0b6fbc55bff5508bf94d58480`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`
- Scenarios: `affinity2-500` through `affinity2-507` and `affinity2-600` through `affinity2-601`

Patch summary:

- Added `SQLiteRealUpstreamExpressionAffinityUnaryDynamicTest.php`.
- The new corpus uses `sqlite3` as an oracle for 1,352 dynamic expression cases covering unary plus/minus chains over text and blob literals, casts to NUMERIC/REAL/INTEGER/TEXT, comparison with TEXT-affinity values, and the large integer vs REAL boundary from affinity2.
- Fixed `SQLiteCoreScalarFunction::formatFloat()` for large integer-valued REAL quote output so `quote(CAST(3175546974276630385 AS REAL))` matches upstream SQLite's exact scientific decimal.

Focused evidence:

- Initial focused run exposed 2 failures in the large integer-valued REAL `quote()` rows.
- After the source fix:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityUnaryDynamicTest.php`
  - Result: `1 test files, 6766 assertions, 0 failures`
  - PASS-line delta: `+1353`
- Related expression-affinity check:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityUnaryDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityDynamicRealExprTest.php lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityRealLiteralDynamicTest.php`
  - Result: `3 test files, 24752 assertions, 0 failures`
- Syntax:
  - `php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php`
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamExpressionAffinityUnaryDynamicTest.php`
- API guard:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 3 assertions, 0 failures`
- Whitespace:
  - `git diff --check -- lanes/libsqlite`
  - Result: passed with no output

Non-overlap:

- Avoids the accepted expression affinity real-index drift corpus and the existing broad cast/arithmetic/comparison matrix.
- This slice owns affinity2 unary text/blob numeric coercion and the affinity2 large integer vs REAL quote/comparison boundary only.

Dependency closure:

- No new support component is needed. The slice reuses the lane-local SELECT SQL executor, core scalar `quote()` / `typeof()` functions, and a local `sqlite3` oracle during focused test generation.
