# Real Upstream Date Affinity Dynamic Corpus

- Session: `port-dev-sqlite-yield-dyn-real-date-20260530T165340Z`
- Base accepted HEAD: `9dc20dce32143ddf9ade7c84c6244ce48fb3e470`
- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date5.test`
- Non-overlap: extends the existing date-affinity dynamic corpus beyond accepted `date.test`, `date4.test`, and fractional-unixepoch coverage with the `date5.test` 400-year leap-cycle JD/calendar matrix.

## Behavior

The slice adds `date5.test` coverage for twelve upstream JD/calendar seed rows and every generated `+/-400 year` cycle inside SQLite's supported calendar range. Each generated row checks both directions:

- `SELECT date($jd)` style Julian-day to calendar text conversion.
- `SELECT julianday($date)` style calendar text to Julian-day conversion.

The red-first run exposed that signed BCE calendar values such as `-0376-02-29` were rejected and that JD `1721118.5` formatted as `0000-02-28` instead of upstream `0000-02-29`. The implementation now accepts negative four-digit SQLite date/datetime years while preserving `+YYYY` rejection, and decomposes Julian days with the Gregorian JD algorithm instead of relying on Unix-timestamp conversion for BCE/year-zero dates.

## Evidence

- Before: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicCorpusTest.php` passed at `1 test files, 600 assertions, 0 failures`.
- Red after adding `date5.test`: same command reached `1474 assertions` with BCE signed-year and year-zero leap-day failures.
- After fix: same command passed at `1 test files, 1474 assertions, 0 failures`.
- Focused assertion delta: `+874` real upstream assertions.
- Expected dashboard movement: `phpPass` `198691 -> 199565`; mapped coverage unchanged at `958 / 1589`.

## Dependency Closure

No new support component is needed. This reuses `SQLiteCoreScalarFunction` date/time scalar dispatch and adds a bounded native Gregorian JD conversion fix.
