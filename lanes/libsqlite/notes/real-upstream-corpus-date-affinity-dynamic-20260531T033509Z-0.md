# real-upstream-corpus-date-affinity-dynamic-20260531T033509Z-0

Base accepted HEAD: `9f3a6190507c2ea8ee290883ee3ce143ab18c8c9`.

Added a disjoint real upstream `date4.test` continuation batch:

- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`.
- Upstream scenario: `date4` loop `for {set i 0} {$i<=24858} {incr i}`, `SELECT strftime($::FMT,$::TS,'unixepoch');`.
- Owned range: `date4-02300` through `date4-03299`, immediately after the existing accepted `date4-01300` through `date4-02299` PHP batch.
- Focused growth: 1002 TestRunner PASS lines and 5006 assertions.
- Non-overlap: this does not repeat accepted date floor/ceiling affinity, date boundary oracle, Julian week, or the earlier date4 `1300..2299` range.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4RealDateContinuation20260531T033509ZTest.php`
  - Result: `1 test files, 5006 assertions, 0 failures`.
  - PASS lines: 1002.

Dependency closure:

- No new support component is needed. The batch reuses existing native `SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ...)` date/time behavior and the hydrated upstream SQLite test file as source truth.

Root harness:

- Not run - isolated micro-slice.
