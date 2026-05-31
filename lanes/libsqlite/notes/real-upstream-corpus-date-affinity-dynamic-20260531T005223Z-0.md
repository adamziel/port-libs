# real-upstream-corpus-date-affinity-dynamic-20260531T005223Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicDate4RealDate20260531T005223ZTest.php` as an additive real upstream date corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- Scenario: `date4-$i` loop over `SELECT strftime($::FMT,$::TS,'unixepoch')`.

Focused behavior:

- Owns the non-overlapping upstream `date4.test` loop range `date4-02300` through `date4-03299`.
- Verifies native `strftime()` extended format expansion for `%d,%e,%F,%H,%k,%I,%l,%j,%m,%M,%u,%w,%W,%Y,%%,%P,%p,%U,%V,%G,%g`.
- Uses independent PHP UTC calendar expectations for day, date, 12/24-hour clock, day-of-year, weekday, `%U`, `%W`, ISO week-year, and AM/PM values.
- Checks numeric and string unixepoch inputs produce equivalent formatted text for each timestamp.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4RealDate20260531T005223ZTest.php`
- Result: `1 test files, 5008 assertions, 0 failures` with `1002` distinct PASS lines.

Non-overlap:

- This ports a fresh `date4.test` real-date range after existing date4 range coverage for `300..1299` and `1300..2299`.
- It does not repeat accepted date5 calendar roundtrip, date.test weekday, date2 deterministic schema/index, date3 unixepoch/auto/modifier placement, date4 earlier-range, or expression-affinity `types2` / `affinity3` behavior.
- Mapped denominator remains unchanged because the upstream manifest is already complete; this handoff should count as PHP PASS-line/assertion growth only.

Dependency closure:

- No new support component is needed. The slice reuses native `SQLiteCoreScalarFunction` date/time parsing, unixepoch handling, and strftime formatting.
