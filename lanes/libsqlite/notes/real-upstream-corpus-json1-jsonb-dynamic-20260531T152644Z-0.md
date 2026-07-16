# real-upstream-corpus-json1-jsonb-dynamic-20260531T152644Z-0

Source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json102.test`
- Upstream sections: `json102-1700`, `json102-1710`, `json102-1720`

Behavior ported:
- `UPDATE ... SET memo = JSON_REMOVE(memo, '$.y')`
- `UPDATE ... SET memo = JSON_SET(memo, '$.y', value) WHERE ... JSON_TYPE(memo, '$.y') IS NULL`
- `RETURNING` expressions over `json_type`, `json_extract`, `memo->>'y'`, nested `json(jsonb(memo))`, and `json(jsonb_extract(jsonb(memo), '$'))`
- Alternates text JSON and JSONB source row images while preserving the upstream SQL shape.

Non-overlap:
- Existing `SQLiteRealUpstreamJson102IndexedMutationDynamicTest.php` covers the standalone `SQLiteJsonPathIndexedUpdatePlan` helper for the same upstream indexed mutation scenario.
- This slice wires the upstream SQL through `SQLiteUpdateDeleteReturningSql`, so JSON assignment functions, JSON predicates, JSONB nested calls, and arrow operators execute through the UPDATE/RETURNING expression evaluator.
- Avoids JSON table cursor/source/constraint batches, JSON aggregate/window batches, `json109` array-insert SELECT SQL, and the existing indexed-update helper surface.

Red-first evidence:
- Before the source change, `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102UpdateSqlDynamic20260531Test.php` failed with `SQLite UPDATE/DELETE literal is not supported: JSON_REMOVE(memo, '$.y')` for the 1000 dynamic rows.

Focused passing evidence:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102UpdateSqlDynamic20260531Test.php`
  - `1 test files, 28007 assertions, 0 failures`
  - Adds 1001 focused PASS cases.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpdateDeleteReturningSqlTest.php lanes/libsqlite/tests/SQLiteUpdateDeleteReturningCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102IndexedMutationDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson109SelectSqlDynamic20260531Test.php lanes/libsqlite/tests/SQLiteJsonInspectionSqlTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102UpdateSqlDynamic20260531Test.php`
  - `6 test files, 57137 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
  - no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamJson102UpdateSqlDynamic20260531Test.php`
  - no syntax errors
- `git diff --check -- lanes/libsqlite`
  - clean

Dependency closure:
- No new support component is needed. This reuses existing native JSON/JSONB helpers and extends the existing UPDATE/DELETE RETURNING expression evaluator.

Root harness:
- Not run - isolated micro-slice.
