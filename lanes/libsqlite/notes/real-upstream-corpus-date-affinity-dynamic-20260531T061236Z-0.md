# real-upstream-corpus-date-affinity-dynamic-20260531T061236Z-0

Added `SQLiteRealUpstreamDateNowModifierDynamic20260531T061236ZTest.php` as an
additive real upstream date/affinity corpus shard.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- Scenario range: `date-8.5` through `date-8.19`, plus `date-8.90`.

Behavior ported:

- `datetime('now', modifier)` preserves the same modifier semantics as the
  equivalent literal timestamp for start-of-month/year/day, day/month/year,
  minute/hour, and second modifiers.
- Invalid long modifiers from `date-8.90` return `NULL`.
- The dynamic matrix checks 1000 statement-local `now` values across the real
  upstream modifier family using `statementDateTimeResults()` and the native
  scalar date/time dispatcher.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateNowModifierDynamic20260531T061236ZTest.php`
- Result: `1 test files, 4055 assertions, 0 failures`
- Focused PASS growth: `+1019` real TestRunner cases.

Non-overlap:

- Owns `date.test` `date-8.5..8.19` and `date-8.90`.
- Avoids accepted `date-8.1..8.4` weekday coverage,
  `date-15.1..15.2` stable statement-now coverage, date4 row sweeps, date5
  Gregorian cycles, timezone/localtime chains, date2/date3 guards, and
  affinity2/affinity3 shards.

Dependency closure:

- No new support component needed. This reuses
  `SQLiteCoreScalarFunction::statementDateTimeResults()` and existing
  date/time modifier dispatch.
