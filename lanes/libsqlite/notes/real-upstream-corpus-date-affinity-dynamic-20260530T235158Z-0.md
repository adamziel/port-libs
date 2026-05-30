# real-upstream-corpus-date-affinity-dynamic-20260530T235158Z-0

Base accepted HEAD: `8c54cf5d7498c37ac92862dd579a0f2d540ceb41`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date5.test`
- Sections: `date5-jd$jd`, `date5-cal/$date`, and the forward/backward
  400-year Gregorian-cycle expansion using `146097` days per cycle.

Behavior added:

- Added `SQLiteRealUpstreamCorpusDateAffinityDynamicDate5CycleTest.php`.
- The test expands the real upstream `date5.test` seed rows into 1,000 focused
  date/JD conversion cases.
- Each row asserts `date($julian_day)`, `julianday($date)`, SQLite storage
  classes, half-day Julian invariants, and generated cycle direction metadata.

Non-overlap:

- This does not repeat the accepted date-affinity millisecond unixepoch,
  timezone suffix normalization, Julian week fractional rows, weekday modifier,
  timediff, date3/date4, or month-matrix coverage.
- This slice is specifically the `date5.test` leap-year and pre-0400
  Gregorian-cycle corpus.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate5CycleTest.php`
- Result: `1 test files, 7004 assertions, 0 failures`
- PASS-line movement: `1001` focused PASS lines.

Dependency closure:

- No new support component is needed. Existing `SQLiteCoreScalarFunction`
  date/time conversion behavior covers this upstream corpus section.
