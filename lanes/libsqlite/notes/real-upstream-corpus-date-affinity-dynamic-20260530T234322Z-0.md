# real-upstream-corpus-date-affinity-dynamic-20260530T234322Z-0

Added `SQLiteRealUpstreamDateInvalidStrftimeNullDynamicTest.php` as an additive real upstream date/affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- Scenario names: `date-3.18` invalid `strftime()` conversion letters and `date-7.1..7.16` NULL argument propagation for date/time functions.

Focused behavior:

- 1000 distinct dynamic `strftime()` invalid-format cases generated from upstream `date-3.18` conversion letters, across plain/prefixed/suffixed/wrapped format strings and varied timestamps.
- Each invalid-format case also checks BLOB-to-text coercion, `typeof(NULL)`, and that a valid control format for the same timestamp still works.
- 16 direct NULL propagation cases from upstream `date-7.1..7.16`.
- One generic application schedule-guard case using `app_settings`-style keys and no domain-specific libsqlite API.

Focused assertion/PASS count:

- New focused file contributes 1018 TestRunner PASS cases.
- New focused file contributes 5054 assertions.

Non-overlap:

- This ports the invalid `strftime()` conversion and NULL argument sections of real upstream `date.test`.
- It does not repeat accepted Julian-day, unixepoch, timezone, localtime, date2/date3/date4/date5, timediff, weekday, month/floor/ceiling, statement-now, schema determinism, or expression-affinity coverage.
- Mapped denominator remains unchanged because the upstream manifest is already complete; this handoff should count as PHP PASS-line/assertion growth only.

Dependency closure:

- No new support component is needed. The slice reuses native `SQLiteCoreScalarFunction` date/time dispatch, text/blob coercion, `strftime()` formatting, and NULL handling.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDateInvalidStrftimeNullDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateInvalidStrftimeNullDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
