# real-upstream-corpus-date-affinity-dynamic-20260530T203925Z-0

Base accepted HEAD: `80c609b1de0bbfd42f2c3e021c00d868ce6dbc14`.

Added `SQLiteRealUpstreamDateAffinityWeekdayDynamicCorpusTest.php` with 1,288 focused TestRunner PASS cases and 6,585 assertions.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test`

Covered upstream sections:

- `date.test` `date-2.3..2.12`: weekday modifier advancement and invalid weekday parsing.
- `date.test` `date-8.1..8.4`: same weekday behavior over a dynamic statement-now-equivalent corpus.
- `affinity3.test` `affinity3-200..260`: TEXT id affinity preservation so automatic-index style comparisons do not coerce padded text keys into numeric equality.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityWeekdayDynamicCorpusTest.php`
  - `1 test files, 6585 assertions, 0 failures`

Dependency closure:

- No new support component is needed. The slice reuses existing `SQLiteCoreScalarFunction` date/time modifier execution and `SQLiteRealExpressionAffinityCorpusPlan` affinity comparison helpers.

Non-overlap:

- Does not repeat the existing millisecond `date-2.2c` fractional unixepoch batch, date boundary/floor/ceiling/null/UTC batches, or affinity2/types2 bulk comparison batches. The new corpus owns weekday modifier dynamics plus affinity3 padded text id preservation.
