# real-upstream-corpus-date-affinity-dynamic-20260531T072748Z-0

Base accepted HEAD: `49647c646cee956ed1d4c9609a0c5aac0efc4e84`.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test`
- Ported scenarios:
  - `date3-1.1..1.8` unixepoch boundary and subsecond truncation behavior.
  - `date3-1.7` unixepoch identity behavior, using 1000 deterministic row cases in place of Tcl `rand()`.
  - `date3-2.1..2.40` `auto` modifier Julian-day, Unix timestamp, out-of-range, text no-op, and mixed-source behavior.
  - `date3-3.1..3.2` immediate-position requirement for `unixepoch`.
  - `date3-4.1..4.3` immediate-position and numeric-value requirement for `julianday`.
  - `date3-5.0` first 63 days of 1970 `auto` ambiguity behavior.

## Focused Evidence

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate3Auto20260531Test.php`.
- Focused command:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate3Auto20260531Test.php`
  - Result: `1 test files, 3315 assertions, 0 failures`
  - PASS-line growth: `1142` focused PASS cases.

## Non-Overlap

This owns `date3.test` auto/unixepoch/julianday behavior. It avoids accepted
`date.test`, `date2.test`, `date4.test` rows through `19399`, `date5.test`
Gregorian/leap coverage, expression-affinity operator clusters, and the
accepted source-neutral CAST/LIKE/GLOB work.

## Dependency Closure

No new support component is needed. The tests reuse existing
`SQLiteCoreScalarFunction` date/time dispatch, `quote`, `typeof`, and the local
`sqlite3` oracle for expected scalar values. No upstream runner, ext/sqlite, or
shared checkout mutation is required.
