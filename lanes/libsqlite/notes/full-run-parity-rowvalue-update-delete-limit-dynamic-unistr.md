# Row-Value UPDATE/DELETE Dynamic LIMIT `unistr()` Parity

Micro-slice: `full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T225457Z-0`

Base accepted HEAD: `292ada6b86cc431f7b1537075eacedfb4e905cf4`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/func9.test`
  - `func9-200`: `unistr('G\u00e4ste')` decodes Unicode escapes.
  - `func9-210`: `unistr_quote(unistr('G\u00e4ste'))` produces a SQL literal.
- `/home/claude/port-libs/.upstream-cache/libsqlite/src/func.c`
  - `unistrFunc()` accepts `\XXXX`, `\+XXXXXX`, `\uXXXX`, `\UXXXXXXXX`, and `\\`.
  - invalid Unicode escapes report an error.

## Delta

- Added `unistr()` and `unistr_quote()` to `SQLiteUpdateDeleteReturningSql` dynamic `LIMIT` scalar dispatch.
- Reused `SQLiteCoreScalarFunction::sqlFunctionArguments()` for parity with existing scalar behavior.
- Added focused generic `app_settings` row-value UPDATE/DELETE tests covering:
  - outer `UPDATE ... ORDER BY ... LIMIT/OFFSET` with decoded Unicode text length;
  - comma-form `DELETE ... LIMIT offset,count` using bare and `\u` Unicode escapes;
  - row-value tuple subqueries using `\+` and `\u` forms;
  - `unistr_quote()` in an offset predicate;
  - NULL propagation through `coalesce()`;
  - direct `unistr_quote()` / `quote()` comparison;
  - invalid escape and arity rejection.

## Verification

- Red-first before source change:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitUnistrDynamicTest.php`
  - Result: `1 test files, 8 assertions, 6 failures`
- Focused after source change:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitUnistrDynamicTest.php`
  - Result: `1 test files, 55 assertions, 0 failures`
- Adjacent dynamic LIMIT family:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php lanes/libsqlite/tests/SQLiteUpdateDeleteLimitDynamicExpressionTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitUnistrDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitBindParameterDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - Result: `5 test files, 21657 assertions, 0 failures`

## Non-Overlap

This slice does not cover pending or accepted row-value dynamic LIMIT patches for `current_date`, `current_time`, `current_timestamp`, `timediff()`, `random()`, scalar `like()`/`glob()`, or `unhex()`. The current accepted base already includes broad row-value UPDATE/DELETE LIMIT dynamic parity; this patch only fills the `unistr()` / `unistr_quote()` scalar dispatch gap inside that evaluator.

## Dependency Closure

No new support component is needed. The patch reuses lane-local scalar function, UTF-8 codepoint, SQL literal quoting, row-value UPDATE/DELETE planning, and dynamic LIMIT expression helpers.
