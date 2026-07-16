# real-upstream-corpus-date-affinity-dynamic-20260531T050956Z-0

Status: focused real-upstream corpus test growth for SQLite date/time affinity.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- Scenario: `date-3.40`, ticket #2276 leading-zero `strftime()` formatting for
  early four-digit years.

Focused PHP coverage:

- Added `SQLiteRealUpstreamCorpusDateAffinityDynamicLeadingZeroStrftime20260531T050956ZTest.php`.
- The test cites the exact upstream `date-3.40` Tcl row and adds 1000 dynamic
  early-year `strftime('%d/%f/%H/%W/%j/%m/%M/%S/%Y', ...)` cases.
- Each dynamic case checks the composite formatted value, text return affinity,
  fixed output width, leading-zero year preservation, separator placement, and
  bounded generated-row ownership.
- Focused movement: 1003 TestRunner PASS cases and 6006 assertions.

Non-overlap:

- This owns the `date.test` `date-3.40` leading-zero composite formatting row.
- It avoids accepted `date4` libc-format row ranges, `date5` Gregorian cycle
  roundtrips, `date-2.2c` fractional unixepoch rows, timezone/UTC suffix rows,
  date floor/ceiling and localtime chains, and extended `date-3.20..3.37`
  specifier coverage.

Verification:

- Red-first focused run before the assertion fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicLeadingZeroStrftime20260531T050956ZTest.php`
  produced `1 test files, 3006 assertions, 1000 failures` from an incorrect
  fixed-length expectation in the new test.
- Final focused run:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicLeadingZeroStrftime20260531T050956ZTest.php`
  produced `1 test files, 6006 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. This reuses
  `SQLiteCoreScalarFunction` strftime early-year formatting, millisecond
  formatting, day-of-year, and week-number behavior.
