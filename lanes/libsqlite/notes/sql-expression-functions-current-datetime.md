# SQL Expression Functions Current: Date/Time Scalars

## Behavior

- Extended `SQLiteCoreScalarFunction` date/time scalar handling for SELECT SQL expression use.
- Added fractional `seconds`, `minutes`, `hours`, `days`, `months`, and `years` modifiers.
- Added `subsec` / `subsecond` output for `time()`, `datetime()`, and `unixepoch()`.
- Added UTC normalization for `Z` and `+HH:MM` / `-HH:MM` timestamp suffixes.
- Preserved millisecond precision in `timediff()`.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectSqlCoreScalarFunctionCorpusTest.php`
  - `1 test files, 72 assertions, 0 failures`
  - New focused delta: `+20` PASS cases over the existing 52-case scalar corpus.
- `php lanes/libsqlite/examples/application-select-sql-datetime-functions.php --self-test`
  - `application-select-sql-datetime-functions self-test passed`

## Non-Overlap

This slice avoids numbered production classes and does not repeat accepted expression `ORDER BY`, grouped SELECT SQL, scalar WHERE operands, Unicode GLOB, JSON table, WAL, VFS, or B-tree clusters. It stays inside the assigned SQL expression-functions surface by using the existing SELECT SQL function-expression path.

## Dependency Closure

No new support component is needed. The implementation reuses PHP `DateTimeImmutable`, the existing SELECT SQL parser/executor, and the existing `SQLiteCoreScalarFunction` dispatch.
