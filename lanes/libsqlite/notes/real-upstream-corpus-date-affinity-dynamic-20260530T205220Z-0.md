# real-upstream-corpus-date-affinity-dynamic-20260530T205220Z-0

Status: ready for integration on accepted base `f32e8deaca85f9598bd0eb6230903f7d3fab9f57`.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`

Ported behavior:

- `date.test` `date-6.1..6.12`: deterministic localtime/utc conversion across the SQLite test-control transition cases, including ambiguous local timestamps.
- `date.test` `date-6.21..6.24`: out-of-band year localtime/utc conversion.
- `date.test` `date-6.28..6.32`: chained `utc` and `localtime` modifiers, including UTC-suffixed inputs and repeated localtime no-op behavior.
- `date.test` `date-18.1`: `localtime` preserves fractional seconds for `%f`.

Implementation:

- Added `SQLiteCoreScalarFunction::sqlFunctionArgumentsWithLocaltimeRules()` as a lane-local deterministic test-control hook for date/time dispatch.
- The normal `sqlFunctionArguments()` path is unchanged for process-local timezone behavior; deterministic localtime rules are opt-in and generic.

Focused coverage:

- Added `SQLiteRealUpstreamDateLocaltimeDynamicCorpusTest.php`.
- Focused PASS cases: 1024 distinct TestRunner cases.
- Focused assertions: 5048.
- The generated matrix covers 1000 UTC/local roundtrips derived from the upstream `date-6` localtime/utc behavior without using process timezone state.

Non-overlap:

- Existing accepted date coverage owns fractional unixepoch milliseconds, UTC/null handling, floor/ceiling, month matrix, Gregorian cycles, date3/date4/date5, statement-now stability, and long fractional truncation.
- This slice owns only the previously blocked deterministic localtime/utc test-control behavior plus `date-18.1` fractional preservation through localtime.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDateLocaltimeDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateLocaltimeDynamicCorpusTest.php` => `1 test files, 5048 assertions, 0 failures`

Dependency closure:

- No external support component is needed. The missing support was lane-local PHP date/time dispatcher state for deterministic localtime conversion.
