# full-run-parity-rowvalue-update-delete-limit-dynamic-20260601T164624Z-0

Scope: row-value `UPDATE` / `DELETE` dynamic `LIMIT` parity for SQLite
`BETWEEN` precedence with same-precedence tails.

Source truth and non-overlap:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
  `e_expr-13.2.*` covers `BETWEEN` grouping left-to-right with `==`, `!=`,
  and `LIKE`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/src/parse.y` keeps `IS`,
  `MATCH`, `LIKE_KW`, `BETWEEN`, `IN`, `ISNULL`, `NOTNULL`, `NE`, and `EQ`
  at one left-associative precedence level.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` covers
  `UPDATE_DELETE_LIMIT` expression admission, and `rowvalue4.test` anchors
  row-value tuple subquery selection behavior.
- This does not repeat accepted arithmetic, CASE/iif, collate postfix,
  NULL-safe distinct comparison, JSON mutation, LIKE/GLOB function/predicate
  standalone, timediff, random/randomblob, bind parameter, cast-affinity, or
  string-literal `OFFSET` token splitting batches.

Red-first evidence:
- Before the source change,
  `SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 6 BETWEEN 4 AND 8 == 1")`
  returned `0`; SQLite expects `1` because this groups as
  `(6 BETWEEN 4 AND 8) == 1`.
- The new focused test initially failed with 88 behavior failures across
  parser, UPDATE window, and DELETE tuple-source cases.

Implementation:
- `SQLiteUpdateDeleteReturningSql` now separates a same-precedence tail from
  the upper operand of a dynamic LIMIT `BETWEEN` expression.
- The evaluated `BETWEEN` result is then fed through the existing LIMIT
  predicate evaluator for `==`, `=`, `!=`, `<>`, `LIKE`/`GLOB`, `IS` /
  `IS NOT` / distinct forms, truth forms, `IN` / `NOT IN`, and nested
  `BETWEEN` / `NOT BETWEEN` tails.
- Parenthesized upper operands still keep their explicit grouping, and
  `<`/`>`/`<=`/`>=` remain part of the upper expression because SQLite gives
  them tighter precedence than `BETWEEN`.

Focused test movement:
- Added `SQLiteRowValueUpdateDeleteLimitBetweenPrecedenceDynamicTest.php`.
- Added 12 parser parity cases for left-to-right `BETWEEN` tails and explicit
  grouping controls.
- Added 40 dynamic UPDATE outer-window cases and 40 dynamic DELETE row-value
  tuple-source subquery cases.
- Added 4 malformed guard cases.
- PASS-line delta: +97 focused PASS cases.
- Assertion delta: +300 focused assertions in the new file.
- `lane-status.json` `phpPass` moves from `6150052` to `6150149`.

Verification:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitBetweenPrecedenceDynamicTest.php`
  passed: `1 test files, 300 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimit*DynamicTest.php lanes/libsqlite/tests/SQLiteUpdateDeleteLimitDynamicExpressionTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed: `13 test files, 2966 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitBetweenPrecedenceDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php lanes/libsqlite/tests/SQLiteUpdateDeleteLimitDynamicExpressionTest.php`
  passed: `4 test files, 22438 assertions, 0 failures`.
- Final lint, JSON validation, diff check, and source-neutral guard are
  recorded in the worker final response.

Dependency closure:
- No new support component is needed. This reuses the native PHP
  `SQLiteUpdateDeleteReturningSql` LIMIT evaluator, row-value tuple-source
  executor, and source-neutral application fixtures.
