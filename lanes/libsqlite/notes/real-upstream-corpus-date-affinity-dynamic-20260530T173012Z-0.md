# real-upstream-corpus-date-affinity-dynamic-20260530T173012Z-0

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test`
  - `date3-1.1..1.8` unixepoch conversion and integer truncation
  - `date3-2.1..2.40` `auto` modifier Julian-day versus Unix-time affinity
  - `date3-3.1..4.3` immediate `unixepoch` / `julianday` modifier rules
  - `date3-5.0` first-63-days-of-1970 `auto` ambiguity
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
  - dynamic `strftime(...,'unixepoch')` formatting samples from the upstream timestamp loop

## Behavior

`SQLiteCoreScalarFunction` now returns SQL `NULL` for invalid date/time
interpretation modifier placement and out-of-range `auto` numeric values,
treats text plus `auto` as a no-op, uses lossless numeric detection for
`unixepoch` / `julianday`, and accepts the upstream Julian-day auto boundary
`5373484.4999999`.

The new focused PHP test file adds 541 focused assertions / PASS lines covering
real upstream `date3.test` and `date4.test` behavior. It is non-overlapping with
the existing fractional-unixepoch corpus, which targets `date.test` millisecond
formatting.

## Verification

- `php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDateAutoUnixepochModifierCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAutoUnixepochModifierCorpusTest.php`
  - `1 test files, 541 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAutoUnixepochModifierCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateFractionalUnixepochCorpusTest.php lanes/libsqlite/tests/SQLiteDateTimeStrftimeModifierCorpusTest.php`
  - `3 test files, 1602 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

## Dependency closure

No new support component is needed. This uses the existing native
`SQLiteCoreScalarFunction` date/time implementation and the hydrated upstream
SQLite test files as source truth.
