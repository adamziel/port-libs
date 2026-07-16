# Date/Time Strftime Modifier Corpus Next6

## Scope

- Added a focused upstream-style PHP corpus for SQLite `strftime()` format substitutions beyond the previously accepted date/time modifier slices.
- Covered fractional seconds, day-of-year, weekday numbering, Sunday/Monday/ISO week fields, ISO week-year fields, 12-hour clock markers, composite `%F`/`%R`/`%T` formats, Julian day formatting, unsupported-format NULL behavior, NULL propagation, and guarded unsupported modifiers.
- Extended fractional timestamp parsing so `strftime('%f', ...)` and `julianday(...)` can preserve subsecond input.

## Verification

- Red-first check before the implementation: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteDateTimeStrftimeModifierCorpusTest.php` initially failed because the new test file did not exist.
- Focused test after implementation:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteDateTimeStrftimeModifierCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
60 PASS lines
1 test files, 60 assertions, 0 failures
```

Shared scalar compatibility check:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
1 test files, 9746 assertions, 0 failures
```

## Dashboard Delta

- `phpPass`: `2017 -> 2077` from the 60 newly passing `TestRunner` PASS cases in `SQLiteDateTimeStrftimeModifierCorpusTest.php`.
- `benchmarkDenominator.mapped`: `455 -> 456` for one newly mapped focused upstream date/time `strftime()` format-substitution evidence row.
- `focusedCoverage.mapped`: `448 -> 449` for the same bounded corpus mapping.

## Non-Overlap

This slice does not repeat the accepted date/time scalar basics, start-of-month/year, signed month/year, or weekday modifier corpus. It focuses on `strftime()` format-code parity and fractional timestamp parsing. It also avoids the current accepted JSON, WAL, VFS, B-tree, SELECT SQL, Unicode GLOB, and release-runner clusters.

## Dependency Closure

No new support component is needed. The implementation uses PHP `DateTimeImmutable` in UTC and bounded native formatting helpers; no ext/sqlite, upstream binary, or shared date/time support package is required.
