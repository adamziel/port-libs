# real-upstream-corpus-select-core-dynamic-20260531T105138Z-0

Slice: `real-upstream-corpus-select-core-dynamic-20260531T105138Z-0`

Base accepted HEAD: `229ee6ac6ba54ebcac89b65db02638641eecef2d`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select8.test`
- Ported scenarios: `e_select-9.1`, `e_select-9.2`, `e_select-9.7`, `e_select-9.8`, `e_select-9.10`, and select8's upstream dynamic expected-result shape for `select8-1.1` through `select8-1.3`.

Behavior covered:

- LIMIT and OFFSET expressions accept values only when they can be losslessly converted to integers.
- Integral REAL values, quoted integral numeric text, scalar subquery integers, and concatenated integral numeric text are accepted for LIMIT/OFFSET.
- NULL, BLOB, non-numeric text, aggregate text, and non-integral REAL values reject with a `datatype mismatch` LIMIT diagnostic.
- Negative OFFSET remains normalized to zero by the existing result layer.
- Existing select8 grouped LIMIT/OFFSET coverage now mirrors upstream Tcl by slicing the engine's base grouped result instead of asserting a deterministic no-ORDER-BY grouped row order.

Focused growth:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamESelectLimitDatatypeDynamic20260531T105138ZTest.php`.
- New TestRunner PASS-line growth is `1002` distinct cases.
- Focused new-test run produced `1 test files, 57010 assertions, 0 failures`.
- Red-first check before the source fix failed `1000 / 1002` cases because `SQLiteSelectSql` rejected quoted/integral REAL LIMIT values and truncated non-integral REAL values.

Non-overlap:

- This slice owns only e_select LIMIT/OFFSET datatype conversion and select8's upstream expected-result test repair.
- It avoids accepted comma-LIMIT, negative LIMIT row slicing, select9 compound limit sweeps, SELECT DISTINCT/ALL, empty aggregate, joins, grouped SELECT execution, expression ORDER BY, JSON table, WAL, VFS, B-tree, PRAGMA, trigger, and metadata-only runner rows.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectLimitDatatypeDynamic20260531T105138ZTest.php`
  - Result: `1 test files, 57010 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect8LimitOffsetDynamicTest.php`
  - Result: `1 test files, 6020 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamESelectLimitDatatypeDynamic20260531T105138ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamLimitDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect8LimitOffsetDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect9CompoundLimitDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicNegativeLimitBatch0Test.php lanes/libsqlite/tests/SQLiteSelectLimitOffsetCurrentSourceTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `7 test files, 89781 assertions, 0 failures`.
- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamESelectLimitDatatypeDynamic20260531T105138ZTest.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelect8LimitOffsetDynamicTest.php`
  - Result: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status valid\n";'`
  - Result: `lane-status valid`.
- `git diff --check -- lanes/libsqlite`
  - Result: no whitespace errors.

Dependency closure:

- No new support component is needed. The slice reuses `SQLiteSelectSql`, `SQLiteSelectExpression`, scalar subquery evaluation, concatenation expression support, and the hydrated upstream SQLite SELECT corpus.
