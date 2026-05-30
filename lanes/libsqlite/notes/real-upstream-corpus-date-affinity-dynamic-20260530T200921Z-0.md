# real-upstream-corpus-date-affinity-dynamic-20260530T200921Z-0

Base accepted HEAD: `c1a0d2c80ea721e0595b20a5cbe43c5043856066`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- Ported scenarios:
  - `date-3.20..3.37`: extended `strftime()` day, hour, meridiem, ISO date/time, and ISO weekday format specifiers.
  - `date-3.40`: leading-zero preservation for early years and fractional seconds.

## Focused Coverage

- Added `SQLiteRealUpstreamDateStrftimeExtendedDynamicTest.php`.
- The focused file contains 1,800 distinct TestRunner PASS cases and 9,314 behavior assertions.
- Dynamic corpus rows cover:
  - 336 calendar combinations for `%e`, `%k`, `%w`, and `%u`.
  - 1,440 minute-level meridiem combinations for `%I`, `%p`, `%P`, `%l`, and `%R`.
  - Exact upstream named rows for `date-3.20` through `date-3.37` and `date-3.40`.

## Non-Overlap

Existing accepted date-affinity slices cover fractional Unix epoch, date2 deterministic schema guards, date3 auto/unixepoch conversion, date4/date5 boundary/cycle behavior, and date floor/ceiling modifiers. This slice is limited to the extended `strftime()` format specifier corpus from `date.test` 3.20-3.40.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateStrftimeExtendedDynamicTest.php`
  - `1 test files, 9314 assertions, 0 failures`
  - `1800` focused PASS lines
- No root harness run; isolated micro-slice only.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `SQLiteCoreScalarFunction::strftimeSql()` implementation and adds real upstream corpus coverage for already implemented date/time behavior.
