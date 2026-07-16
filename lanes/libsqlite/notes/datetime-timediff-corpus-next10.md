# Date/Time `timediff()` Corpus Next10

## Status

- Added `lanes/libsqlite/tests/SQLiteDateTimeTimediffCorpusTest.php` with 47 focused PASS cases for SQLite `timediff(A,B)` behavior.
- Covered same-instant, positive/negative seconds, minute/hour/day/month/year boundaries, leap-day/month-end calendar spans, date-only midnight defaults, ISO `T` and `Z` inputs, fractional-second truncation in current output, Julian numeric values, NULL propagation, malformed argument guards, and Application cron/import interval summaries.
- Focused verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteDateTimeTimediffCorpusTest.php`
  - Result: `1 test files, 47 assertions, 0 failures`

## Dashboard Delta

- `phpPass`: `3236 -> 3283` from the 47 verified PASS lines above.
- `benchmarkDenominator.mapped`: unchanged at `456`; this slice adds PHP corpus coverage only and does not claim a newly mapped upstream inventory unit.

## Non-Overlap

This slice does not repeat accepted VFS rollback/commit/sync/lock/file-writer work, WAL checkpoint/savepoint byte truncation, B-tree page move/root-collapse/overflow freelist work, JSON table source/cursor/constraint work, SELECT SQL text/subquery/GROUP/ORDER/LIMIT work, Unicode GLOB, or the strftime format/modifier corpus. It focuses narrowly on `timediff()` calendar-difference parity.

## Dependency Closure

No new support component is needed. The corpus reuses the existing native PHP `SQLiteCoreScalarFunction` date/time dispatch and PHP `DateTimeImmutable` runtime behavior.
