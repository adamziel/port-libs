# real-upstream-corpus-date-affinity-dynamic-20260530T224634Z-0

Status: ready for integration from accepted base `dc9a740fd34e07dba61e9143b3604d183ad170bf`.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date5.test`

## Ported Behavior

- `date.test` boundary sections `date-13`, `date-16`, `date-17`, and `date-20`:
  relative day/hour/minute/second modifiers, lower and upper Julian/date bounds,
  start-of-day/month/year handling, and high fractional second no-rounding inputs.
- `date5.test` Gregorian-cycle source rows:
  leap, non-leap, century, 400-year, Meeus, and Julian-day reference dates.

The new test file compares the native PHP scalar date/time functions against the
local `sqlite3` oracle for `date`, `time`, `datetime`, `julianday`, and
`unixepoch` over a 20 by 10 by 5 matrix, producing 1,000 distinct corpus cases
plus source/count guard cases.

## Non-Overlap

This does not repeat the existing accepted date2 deterministic schema guards,
date3 unixepoch/auto matrix, date4 libc strftime parity, existing date5
400-year row cycle test, or the earlier oracle-batch date/affinity cast matrix.
The owned gap is boundary modifier composition across date.test sections
`13/16/17/20` combined with date5 source dates and oracle-backed storage-class
checks.

One precision-sensitive candidate was deliberately excluded from the green
matrix: `julianday(5373484.4999999, negative boundary modifiers)` differs from
the local SQLite oracle just beyond the test tolerance. That remains a concrete
future precision-fix target, not counted in this handoff.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityBoundaryOracleCorpusTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityBoundaryOracleCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityBoundaryOracleCorpusTest.php`
  - `1 test files, 4005 assertions, 0 failures`
  - `1001` PASS lines

Dependency closure: no new support component is needed; the test reuses the
existing native scalar date/time implementation and the already available local
`sqlite3` oracle for real upstream parity checks.
