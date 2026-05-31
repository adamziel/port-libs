# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T091521Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
RETURNING LIMIT parity.

Behavior covered:

- Extends `SQLiteUpdateDeleteReturningSql` LIMIT/OFFSET expression evaluation
  to dispatch SQLite date/time scalar functions through the existing
  `SQLiteCoreScalarFunction` implementation.
- Treats numeric strings returned by scalar functions as numeric arithmetic
  operands for LIMIT/OFFSET expressions, matching SQLite coercion for cases
  such as `strftime('%d', ...) - 1`.
- Replaces the broad scalar-function regex with a whole-function-call parser so
  function-leading arithmetic such as `julianday(...) - julianday(...)` is not
  misparsed as one malformed function call.
- Adds UPDATE and DELETE RETURNING windows over generic `app_settings` rows for
  `unixepoch`, `strftime`, `julianday`, `date`, `time`, and `datetime`
  LIMIT/OFFSET expressions, including malformed non-integer/null date cases.

Upstream parity source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  expression-valued LIMIT/OFFSET windows.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test` for
  SQLite date/time scalar behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test` for
  tuple subquery selection through DELETE RETURNING.

Focused growth:

- Red check before implementation: `unixepoch('1970-01-01 00:00:03')` in a
  DELETE LIMIT failed with `SQLite UPDATE/DELETE LIMIT expressions must evaluate
  to an integer`.
- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` grew from 14,548 to
  15,186 focused assertions.
- The slice adds 106 focused TestRunner PASS cases and 638 focused assertions.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 15186 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php`
  - `1 test files, 625 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php`
  - `2 test files, 15811 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses the existing
  `SQLiteCoreScalarFunction` date/time implementation and the existing
  row-value UPDATE/DELETE RETURNING executor.

Non-overlap:

- This avoids pending or just-ready row-value dynamic slices for LIKE/GLOB,
  `IS DISTINCT FROM`, `random()`, `sqlite_version()`, and `sqlite_source_id()`
  LIMIT/OFFSET behavior.
- It does not add WordPress-specific API, examples, fixture names, or source
  declarations.
