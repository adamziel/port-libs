# real-upstream-corpus-date-affinity-dynamic-20260531T024907Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicDate4RealDate20260531T024907ZTest.php` as an additive real upstream date/affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- Scenario: `date4-$i` loop over `SELECT strftime($::FMT,$::TS,'unixepoch')`.
- Owned range: `date4-03300` through `date4-04299`, using upstream `TS = i*86390`.

Focused behavior:

- Verifies native `strftime()` extended Linux format expansion for `%d,%e,%F,%H,%k,%I,%l,%j,%m,%M,%u,%w,%W,%Y,%%,%P,%p,%U,%V,%G,%g`.
- Checks integer, string, and real unixepoch affinity inputs produce equivalent formatted text.
- Includes a generic `setting.schedule.*` rollup to keep the coverage tied to application-style scheduling data without domain-specific APIs.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4RealDate20260531T024907ZTest.php`
  - `1 test files, 5011 assertions, 0 failures`
  - 1003 focused TestRunner PASS cases.

Non-overlap:

- This ports the next contiguous `date4.test` real-date range after existing date4 coverage through `03299`.
- It does not repeat accepted date2 deterministic schema/index coverage, date3 unixepoch loops, date5 calendar roundtrips, date.test fractional unixepoch/weekday coverage, or expression-affinity batches.

Dependency closure:

- No new support component is needed. The slice reuses native `SQLiteCoreScalarFunction` date/time parsing, unixepoch handling, and strftime formatting.
