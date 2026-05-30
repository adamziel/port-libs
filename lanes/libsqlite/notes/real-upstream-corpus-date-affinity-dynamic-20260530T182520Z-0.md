# real-upstream-corpus-date-affinity-dynamic-20260530T182520Z-0

Base accepted HEAD: `f9e4e2d5498742752e9304fb10cad66aa60851fc`.

Added real upstream `date5.test` coverage for Gregorian leap-year Julian-day
conversion over SQLite's 400-year cycle expansion. Source truth:
`/home/claude/port-libs/.upstream-cache/libsqlite/test/date5.test`.

Ported upstream scenarios:

- `date5-jd$jd`: `SELECT date($jd)` for the 12 upstream seed rows plus every
  generated +/-400-year cycle in the upstream loop.
- `date5-cal/$date`: `SELECT julianday($date)` for the same generated dates.

The PHP focused test expands the exact upstream seed table and cycle bounds
into 437 generated rows. Each row asserts equivalent scalar paths through
`date()`, `datetime()`, `strftime('%F')`, `strftime('%J')`,
`julianday(date)`, `julianday(datetime)`, explicit `julianday` modifier,
`start of day`, and date-part extraction. This is non-overlapping with the
accepted date2/date3/date4 coverage in the current lane status because it owns
the date5 Gregorian 400-year leap-cycle regression.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDate5GregorianCycleCorpusTest.php`
  - `1 test files, 5245 assertions, 0 failures`
  - `438` focused PASS lines

Dashboard expectation:

- `phpPass` +438 if the integrator counts the new focused test file.
- Mapped denominator unchanged; this ports behavior from an already known
  upstream source file rather than adding a new manifest row.

Dependency closure:

- No new support component is needed. The existing native
  `SQLiteCoreScalarFunction` date/time implementation covers the required
  Julian-day and Gregorian date conversion behavior.
