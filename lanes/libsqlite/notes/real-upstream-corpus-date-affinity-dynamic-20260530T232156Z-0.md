# real-upstream-corpus-date-affinity-dynamic-20260530T232156Z-0

Base accepted HEAD: `97bde16e3221376c9c3d6c7f9b2330b164322c56`.

Added a focused real-upstream date corpus batch for SQLite upstream
`test/date.test`:

- `date-3.11.15..3.11.25`: fractional Julian day values through
  `strftime('%W %j', ...)`.
- `date-3.11.99`: text/fractional Julian day week/day stability.

The PHP test dynamically expands the upstream fractional-Julian week/day
behavior into 1,000 bounded Julian day rows, compares the port output against
the local `sqlite3` oracle, and cites the hydrated upstream source file at
`/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicJulianWeek20260530T232156ZTest.php`
  - `1 test files, 3004 assertions, 0 failures`
  - 1,001 PASS lines

Non-overlap:

- Avoids accepted date millisecond `date-2.2c-*`, timezone suffix
  `date-5.*`/`date-6.25..6.27`, date5 calendar roundtrip, statement-now,
  extended `strftime` `%e/%F/%k/%I/%p/%P/%u` rows, localtime, floor/ceiling,
  date2 deterministic schema, date3 modifier placement, unixepoch roundtrip,
  and expression-affinity batches.
- Mapped denominator coverage remains complete at `1589 / 1589`; this is
  selected PASS-line growth only.

Dependency closure:

- Reuses existing native PHP `SQLiteCoreScalarFunction` date/time behavior and
  the existing focused `sqlite3` oracle pattern used by neighboring real
  upstream date-affinity tests.
- No new support component is needed.
