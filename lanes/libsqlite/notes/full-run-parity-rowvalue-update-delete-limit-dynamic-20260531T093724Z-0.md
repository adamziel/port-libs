# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T093724Z-0

## Scope

- Extended row-value UPDATE/DELETE dynamic LIMIT/OFFSET evaluation to use the shared SQLite core scalar implementation for math-library scalar functions.
- Covered outer UPDATE LIMIT/OFFSET and row-value DELETE subquery LIMIT/OFFSET with generic `app_settings` fixtures.
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/func7.test`
  - `func7-200`, `func7-210`, `func7-pg-130`, `func7-pg-170` through `func7-pg-210`, `func7-pg-300` through `func7-pg-420`, and `func7-mysql-230` through `func7-mysql-270`
  - Existing row-value and LIMIT placement citations remain `rowvalue4.test` and `limit.test`.

## Evidence

- Red check before source change:
  - `php -r 'require "tools/bootstrap.php"; use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql; try { SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT log2(8)"); echo "unexpected success\n"; } catch (Throwable $e) { echo get_class($e) . ": " . $e->getMessage() . "\n"; }'`
  - Result: `InvalidArgumentException: SQLite UPDATE/DELETE LIMIT expressions must evaluate to an integer`
- Before focused baseline:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php > /tmp/rowvalue-before.out && tail -1 /tmp/rowvalue-before.out && rg -c '^PASS ' /tmp/rowvalue-before.out`
  - Result: `1 test files, 15186 assertions, 0 failures`; `4151` PASS lines
- After focused test:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php > /tmp/rowvalue-after.out && tail -1 /tmp/rowvalue-after.out && rg -c '^PASS ' /tmp/rowvalue-after.out`
  - Result: `1 test files, 15558 assertions, 0 failures`; `4217` PASS lines
- Adjacent row-value dynamic family:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php > /tmp/rowvalue-family.out && tail -1 /tmp/rowvalue-family.out && rg -c '^PASS ' /tmp/rowvalue-family.out`
  - Result: `2 test files, 16183 assertions, 0 failures`; `4344` PASS lines
- Domain API guard:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php > /tmp/no-domain.out && tail -1 /tmp/no-domain.out`
  - Result: `1 test files, 3 assertions, 0 failures`
- PHP lint:
  - `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
  - Result: no syntax errors
  - `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - Result: no syntax errors
- Whitespace/status checks:
  - `git diff --check -- lanes/libsqlite`
  - Result: passed with no output
  - `php -r '$json = file_get_contents("lanes/libsqlite/lane-status.json"); json_decode($json, true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json valid JSON\n";'`
  - Result: `lane-status.json valid JSON`

## Status Delta

- Focused parity assertions: `15186 -> 15558` (`+372`)
- Focused parity PASS lines: `4151 -> 4217` (`+66`)
- `lane-status.json` `phpPass`: `2840323 -> 2840389`
- Mapped coverage unchanged at `1589 / 1589`; `func7.test` was already mapped, this patch adds behavior/PASS growth.

## Dependency Closure

No new support component is needed. The implementation reuses the existing bounded native PHP `SQLiteCoreScalarFunction::sqlFunctionArguments()` support for math scalar functions and wires it into the row-value UPDATE/DELETE LIMIT expression evaluator.

## Non-Overlap

This does not repeat the accepted date/time LIMIT dynamic slice, string scalar LIMIT slice, row-value comparison/update-delete LIMIT baseline, JSON/VFS/WAL/B-tree storage clusters, or any domain-specific API surface. The new behavior is limited to upstream `func7.test` math scalar expressions in row-value UPDATE/DELETE LIMIT and OFFSET positions.
