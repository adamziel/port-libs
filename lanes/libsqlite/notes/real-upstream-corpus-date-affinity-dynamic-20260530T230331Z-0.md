# real-upstream-corpus-date-affinity-dynamic-20260530T230331Z-0

Added `SQLiteRealUpstreamDate5CalendarRoundtripBulkTest.php` as an additive real upstream date/affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date5.test`
- Scenario names: `date5-jd$jd` and `date5-cal/$date`.

Focused behavior:

- 1250 deterministic generated calendar dates from year `0000` through `9992`.
- Independent Gregorian Julian-day oracle in PHP checks `julianday(date-text)`.
- Native `date(julianday)` and `datetime(julianday)` roundtrip back to the generated calendar values.
- Application-shaped retention sample verifies large historical/future retention windows over generic event dates.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDate5CalendarRoundtripBulkTest.php`
- Result: `1 test files, 5008 assertions, 0 failures`.

Non-overlap:

- This ports real upstream `date5.test` calendar/Julian-day roundtrip behavior.
- It does not repeat accepted `date.test` scalar modifier coverage, `date2.test` deterministic schema guards and real-affinity index predicates, `date3.test` unixepoch/auto/modifier placement coverage, `date4.test` strftime coverage, or expression-affinity `types2` predicate behavior.
- Mapped denominator remains unchanged because the upstream manifest is already complete; this handoff should count as PHP PASS-line/assertion growth only.

Dependency closure:

- No new support component is needed. The slice reuses native `SQLiteCoreScalarFunction` date/time parsing, Julian-day conversion, and calendar formatting.
