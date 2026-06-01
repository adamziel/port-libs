# full-run-parity-rowvalue-update-delete-limit-dynamic-20260601T130342Z-0

Scope: row-value `UPDATE` / `DELETE` dynamic `LIMIT` parity for `COLLATE`
postfix expressions.

Source truth and non-overlap:
- Upstream anchors: `e_expr.test` section `9` for `COLLATE` as a unary
  postfix operator and for parenthesized comparison behavior, `limit.test` for
  LIMIT/OFFSET expression admission, and `rowvalue4.test` for row-value DML
  selection behavior.
- This does not repeat accepted string-literal `OFFSET` splitting, LIKE/GLOB,
  timediff, current-time, cast-affinity, JSON mutation, randomblob/zeroblob,
  unistr, bind-parameter, comma-LIMIT, or ordered UPDATE/DELETE selection
  batches.

Red-first evidence:
- Before the source change,
  `SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT 2 COLLATE nocase")`
  failed with `InvalidArgumentException: SQLite UPDATE/DELETE LIMIT expression
  is not supported`.

Implementation:
- `SQLiteUpdateDeleteReturningSql` now recognizes top-level `COLLATE` postfix
  expressions in dynamic LIMIT/OFFSET scalar contexts.
- Scalar value collations are a no-op, matching SQLite's expression value
  behavior.
- Comparison predicates inside LIMIT/OFFSET now honor `binary`, `nocase`, and
  `rtrim` collations for equality, `IS`, `BETWEEN`, and `IN` expressions.
- Parenthesized comparisons keep SQLite precedence behavior: applying
  `COLLATE` to the already-computed boolean does not change the inner
  comparison result.

Focused test movement:
- Added `SQLiteRowValueUpdateDeleteLimitCollateDynamicTest.php`.
- Added 32 dynamic UPDATE outer-window cases.
- Added 32 dynamic DELETE row-value tuple-subquery cases.
- Added 12 parser and malformed guard cases for value postfix collations,
  `nocase`/`binary`/`rtrim`, parenthesized comparison precedence, unsupported
  comparison collations, and malformed collation names.
- PASS-line delta: +77 focused PASS cases.
- Assertion delta: +401 focused assertions in the new file.

Verification:
- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitCollateDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitCollateDynamicTest.php`
  passed: `1 test files, 401 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitBindParameterDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitCastAffinityDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitCollateDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitCurrentTimeDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitJsonMutationDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitLikeGlobDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitRandomDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitTimediffDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitUnistrDynamicTest.php lanes/libsqlite/tests/SQLiteUpdateDeleteLimitDynamicExpressionTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed: `13 test files, 24372 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure:
- No new support component is needed. This reuses the existing row-value
  UPDATE/DELETE LIMIT evaluator and adds bounded native collation handling
  inside that evaluator.
