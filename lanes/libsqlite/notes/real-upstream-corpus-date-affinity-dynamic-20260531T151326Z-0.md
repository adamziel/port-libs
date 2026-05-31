# real-upstream-corpus-date-affinity-dynamic-20260531T151326Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicLocaltimeTestControl20260531T151326ZTest.php` as an additive real upstream date/affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- Scenario names: `date-6.1` through `date-6.12`, `date-6.20`, and `date-6.21` through `date-6.24`.

Focused behavior:

- `SQLiteCoreScalarFunction::sqlFunctionArgumentsWithLocaltimeRules()` now resolves nonexistent local times during a forward localtime transition with the post-transition offset, matching upstream `date-6.7`.
- The focused corpus verifies deterministic `SQLITE_TESTCTRL_LOCALTIME_FAULT` localtime/UTC conversions, backward-fold UTC selection, localtime failure propagation, and out-of-band historical/future date handling.
- The dynamic section adds 1000 reversible UTC-to-local-to-UTC rows over deterministic alternating-day test-control offsets.
- A generic application schedule sample verifies storage-facing localtime display and UTC roundtrips without adding any domain-specific API.

Focused assertion count:

- 1019 distinct focused TestRunner PASS cases.
- 6077 behavior assertions.
- Expected selected evidence movement: `phpPass` `2928482 -> 2934559`.
- Mapped denominator movement: none; upstream denominator is already `1589 / 1589`.

Red-first evidence:

- Initial focused run before the source fix failed upstream `date-6.7`: expected `2000-10-28 23:40:00`, actual `2000-10-29 00:40:00`.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicLocaltimeTestControl20260531T151326ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicLocaltimeTestControl20260531T151326ZTest.php` => `1 test files, 6077 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicLocaltimeTestControl20260531T151326ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicLocaltimeChain20260531T035308ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicTimezoneOffset20260531T042509ZTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- Result: `4 test files, 12643 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

- This ports the deterministic localtime test-control section from upstream `date.test`.
- It does not repeat date4 strftime row ranges, date5 Gregorian cycles, date3 auto/unixepoch behavior, date15 statement-stable `now`, date6.25+ explicit UTC/no-op chains, timezone-offset parsing, or timediff modifier batches.

Dependency closure:

- No new support component is needed. The slice reuses native `SQLiteCoreScalarFunction` date/time parsing and localtime-rule conversion.
