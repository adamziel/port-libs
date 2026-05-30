# real-upstream-corpus-date-affinity-dynamic-20260530T211715Z-0

Added `SQLiteRealUpstreamDateLocaltimeFailureDynamicCorpusTest.php` and extended
the existing deterministic localtime-rule helper with a generic `failAtUtc`
instant.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- `date-6.20`: `SQLITE_TESTCTRL_LOCALTIME_FAULT` makes `datetime(...,
  'localtime')` raise `local time unavailable` for the configured failing
  `localtime_r()` instant.

Focused behavior:

- `SQLiteCoreScalarFunction::sqlFunctionArgumentsWithLocaltimeRules()` now
  accepts optional `failAtUtc` values in localtime rule rows.
- The upstream exact failing instant `2000-05-29 14:16:00` throws
  `RuntimeException` with `local time unavailable`.
- Neighboring instants still convert through the active offset rule.
- The dynamic corpus adds 1,004 TestRunner PASS cases and 6,009 focused
  assertions around the same deterministic localtime failure hook.

Non-overlap:

- Existing accepted date files already cover date2 deterministic schema guards,
  date3 auto/unixepoch boundaries, date4/date5 dynamic sweeps, date-13/date-16
  boundaries, date-18 subsecond/localtime fractional preservation, date-19
  floor/ceiling, date-20 fractional truncation, and stable statement `now`.
- This slice owns only the previously unmodeled date-6.20 localtime failure
  branch and its directly coupled deterministic shim behavior. It does not
  claim mapped denominator movement or full SQLite release/all parity.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDateLocaltimeFailureDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateLocaltimeFailureDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateLocaltimeDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateLocaltimeFailureDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateLocaltimeDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure:

No new support component is needed. The patch reuses native PHP date/time
support and the existing lane-local deterministic localtime rule shim.
