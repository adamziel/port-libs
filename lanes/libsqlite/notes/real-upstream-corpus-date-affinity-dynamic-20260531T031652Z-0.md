# real-upstream-corpus-date-affinity-dynamic-20260531T031652Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicDate5ExactCycle20260531T031652ZTest.php` as an exact real upstream date/affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date5.test`
- Scenario names: `date5-jd$jd` and `date5-cal/$date`.
- Ported sections: the 12 `date5data` leap-year source rows plus the upstream 400-year forward loop through year `9999` and backward loop through year `-4712`.

Focused coverage:

- 437 exact upstream cycle rows generated from `date5.test`.
- 1,311 independent dynamic TestRunner PASS cases over `date()`, `julianday()`, and `datetime()`.
- 3,948 focused assertions when including source-citation, generic retention samples, and non-overlap checks.

Non-overlap:

- This owns exact `date5.test` leap-cycle rows, including negative years and the upstream generated 400-year cycle.
- It does not repeat the existing synthetic `SQLiteRealUpstreamDate5CalendarRoundtripBulkTest.php`, date4 `strftime` ranges, date2 deterministic schema guards/index rows, date3 unixepoch/auto/modifier placement, date/floor/ceiling/timediff rows, or expression-affinity batches.
- This claims PASS-line growth only; mapped denominator coverage was already complete at `1589 / 1589`.

Dependency closure:

- No new support component is needed. The slice reuses native `SQLiteCoreScalarFunction` date/time parsing, Julian-day conversion, Gregorian leap-year logic, and scalar type dispatch.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate5ExactCycle20260531T031652ZTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate5ExactCycle20260531T031652ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate5ExactCycle20260531T031652ZTest.php`
  - `1 test files, 3948 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
