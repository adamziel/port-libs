# full-run-parity-rowvalue-update-delete-limit-dynamic-20260601T150622Z-0

Scope: row-value `UPDATE` / `DELETE` dynamic `LIMIT` parity for SQLite
NULL-safe distinct comparison predicates and the `==` equality alias.

Source truth and non-overlap:
- Upstream anchors:
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/expr.test`
  sections `expr-1.111b` through `expr-1.122b` for
  `IS NOT DISTINCT FROM` and `IS DISTINCT FROM` NULL-safe semantics,
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test` for
  comparison expression grammar, `limit.test` for LIMIT expression admission,
  and `rowvalue4.test` for row-value DML selection.
- This does not repeat accepted dynamic LIMIT arithmetic, bind parameters,
  current date/time, timediff, random/randomblob, unistr, LIKE/GLOB, JSON
  mutation, cast affinity, collation postfix, comma-LIMIT, or ordered
  UPDATE/DELETE selection batches.

Red-first evidence:
- Before the source change,
  `SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 1 IS DISTINCT FROM 2")`
  failed with `InvalidArgumentException: SQLite UPDATE/DELETE LIMIT
  expressions must evaluate to an integer`.
- Before the source change,
  `SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 1 IS NOT DISTINCT FROM 1")`
  returned `0` because the parser split at `IS NOT` before recognizing the
  longer NULL-safe comparison operator.
- Before the source change, `LIMIT 1 == 1` and row-value constant tuple
  predicates such as `LIMIT (1,2) IS DISTINCT FROM (1,3)` were rejected as
  non-integer expressions.

Implementation:
- `SQLiteUpdateDeleteReturningSql` now recognizes `IS DISTINCT FROM` and
  `IS NOT DISTINCT FROM` before shorter `IS` / `IS NOT` operators.
- Scalar distinct comparisons use the same NULL-safe behavior as SQLite and
  preserve existing `binary`, `nocase`, and `rtrim` collation handling.
- Constant row-value tuple distinct comparisons are admitted in LIMIT/OFFSET
  expressions, reusing existing row-value arity and scalar comparison helpers.
- The dynamic LIMIT predicate evaluator now treats `==` as SQLite's equality
  alias for `=`.

Focused test movement:
- Added `SQLiteRowValueUpdateDeleteLimitDistinctDynamicTest.php`.
- Added 32 UPDATE outer-window cases where dynamic LIMIT/OFFSET expressions
  combine scalar distinct predicates, row-value constant tuple predicates,
  NULL-safe comparisons, and `==`.
- Added 32 DELETE cases where row-value tuple-source subquery LIMIT/OFFSET
  expressions use the same comparison forms before matching DML rows.
- Added 15 parser and malformed guard cases for scalar NULL-safe comparisons,
  row-value tuple comparisons, collated distinct comparisons, `==`, and
  row-value arity/scalar mismatch errors.
- PASS-line delta: +80 focused PASS cases.
- Assertion delta: +405 focused assertions in the new file.
- `lane-status.json` `phpPass` moves from `5975230` to `5975310`.

Verification:
- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDistinctDynamicTest.php` passed.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDistinctDynamicTest.php`
  passed: `1 test files, 405 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitBindParameterDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitCastAffinityDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitCollateDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitCurrentTimeDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDistinctDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitJsonMutationDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitLikeGlobDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitRandomDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitTimediffDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitUnistrDynamicTest.php lanes/libsqlite/tests/SQLiteUpdateDeleteLimitDynamicExpressionTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed: `14 test files, 24777 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure:
- No new support component is needed. This reuses the existing native
  UPDATE/DELETE RETURNING SQL parser, dynamic LIMIT evaluator, row-value tuple
  comparison helpers, and source-neutral application fixtures.
