# full-run-parity-rowvalue-update-delete-limit-dynamic-20260601T035537Z-0

Scope: row-value `UPDATE` / `DELETE` dynamic `LIMIT` parity for scalar string
expressions that contain the word `OFFSET` inside quoted literals.

Source truth and non-overlap:
- Upstream anchors: `limit.test` for LIMIT/OFFSET parsing and `rowvalue4.test`
  for row-value DML selection.
- This does not repeat prior bind-parameter, JSON/JSONB scalar, timediff,
  random, unistr, LIKE/GLOB, comma-LIMIT, or UPDATE/DELETE ordered LIMIT
  batches. It fixes keyword splitting only when literal scalar arguments carry
  ` OFFSET ` text.

Red-first evidence:
- Before the source change, executing an `UPDATE ... LIMIT
  replace('2 OFFSET marker', ' OFFSET marker', '') OFFSET
  replace('1 OFFSET skip', ' OFFSET skip', '')` style statement failed with
  `InvalidArgumentException: SQLite UPDATE/DELETE LIMIT expressions must
  evaluate to an integer`.
- Root cause: `SQLiteUpdateDeleteReturningSql::parseLimit()` used a regex that
  split on `OFFSET` inside quoted string literals instead of using the existing
  top-level SQL scanner.

Implementation:
- Replaced the regex `LIMIT ... OFFSET ...` split with
  `topLevelKeywordPosition($sql, 'OFFSET')`, preserving comma-form
  `LIMIT offset,count` handling through `splitComma()`.
- Added explicit malformed multi-comma rejection before evaluating the LIMIT
  expression directly.

Focused test movement:
- Added 64 UPDATE cases where outer dynamic `LIMIT` and `OFFSET` expressions
  are produced from string literals containing `OFFSET`.
- Added 64 DELETE cases where row-value tuple subquery `LIMIT` and `OFFSET`
  expressions contain literal `OFFSET` text.
- Added 9 parser/malformed guard cases covering direct LIMIT, keyword OFFSET,
  comma form, nested string functions, and empty keyword-offset rejection.
- PASS-line delta: +137 focused PASS cases.
- Assertion delta: 20701 -> 21487 assertions (+786) for
  `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`.

Verification:
- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  passed: `1 test files, 21487 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php lanes/libsqlite/tests/SQLiteUpdateDeleteLimitDynamicExpressionTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitRandomDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitUnistrDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitBindParameterDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitTimediffDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed: `8 test files, 23035 assertions, 0 failures`.
- `php -r '$json = json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg() . "\n"); exit(1); } echo "lane-status json ok\n";'`
  passed.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure:
- No new support component is needed. The patch reuses the existing
  top-level SQL keyword scanner and scalar `replace()` LIMIT evaluator.
