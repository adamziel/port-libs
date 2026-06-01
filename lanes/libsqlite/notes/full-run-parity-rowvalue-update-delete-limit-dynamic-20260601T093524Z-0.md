# full-run-parity-rowvalue-update-delete-limit-dynamic-20260601T093524Z-0

Scope: row-value `UPDATE` / `DELETE` dynamic `LIMIT` parity for JSON
mutation, JSONB mutation, `json_array_insert()` / `jsonb_array_insert()`,
`json_patch()` / `jsonb_patch()`, and `json_pretty()` constant expressions.

Source truth and non-overlap:
- Upstream anchors: `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test`
  for LIMIT expression coercion, `rowvalue4.test` for row-value tuple-source
  DML selection, `json104.test` for merge-patch behavior, `json108.test` for
  `json_pretty()` canonical round-trip behavior, and `json109.test` for
  `json_array_insert()` path/value behavior.
- This does not repeat accepted dynamic LIMIT arithmetic, bind-parameter,
  current date/time, timediff, random/randomblob, unistr, LIKE/GLOB, JSON
  constructor/inspection, or string-literal `OFFSET` coverage.

Red-first evidence:
- Before the source change, parsing
  `DELETE FROM app_settings RETURNING setting_id LIMIT coalesce(json_set('{a,,}', '$.a', 1), 2)`
  returned limit `2`.
- SQLite oracle check:
  `sqlite3 ':memory:' "select coalesce(json_set('{a,,}', '$.a', 1), 2);"`
  failed with `malformed JSON`.
- Root cause: unsupported JSON mutation functions fell through to the generic
  row expression fallback. That fallback intentionally converts
  `InvalidArgumentException` into SQL NULL for unsupported constant
  expressions, which allowed `coalesce()` / `ifnull()` wrappers to mask
  malformed JSON errors.

Implementation:
- `SQLiteUpdateDeleteReturningSql` now routes JSON mutation/patch/remove/
  array-insert/pretty scalar calls through the explicit dynamic LIMIT scalar
  evaluator.
- The LIMIT evaluator now validates arity for those functions and reuses the
  existing native JSON helpers, so malformed JSON and malformed path errors
  propagate instead of becoming fallback NULL values.
- Valid JSON text and JSONB results can still be composed through
  `json_array_length()`, `json_extract()`, arithmetic, `LIMIT ... OFFSET ...`,
  and comma-form `LIMIT offset,count` in row-value tuple subqueries.

Focused test movement:
- Added `SQLiteRowValueUpdateDeleteLimitJsonMutationDynamicTest.php`.
- Added 13 UPDATE cases where outer ordered `LIMIT` / `OFFSET` expressions are
  computed from JSON mutation/pretty functions.
- Added 13 DELETE cases where row-value tuple subquery comma-form LIMIT
  expressions are computed from the same JSON mutation/pretty functions.
- Added 8 malformed JSON/path/arity guard cases proving `coalesce()` and
  `ifnull()` no longer mask JSON mutation errors.
- PASS/assertion delta: +35 focused PASS cases and +183 focused assertions.

Verification:
- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitJsonMutationDynamicTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitJsonMutationDynamicTest.php`
  passed: `1 test files, 183 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitJsonMutationDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php lanes/libsqlite/tests/SQLiteUpdateDeleteLimitDynamicExpressionTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitLikeGlobDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitRandomDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitUnistrDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitBindParameterDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitCurrentTimeDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitTimediffDynamicTest.php`
  passed: `10 test files, 23865 assertions, 0 failures`.

Dependency closure:
- No new support component is needed. This reuses existing native JSON
  mutation, patch, remove, array-insert, pretty, JSONB, and row-value
  UPDATE/DELETE LIMIT executor components.
