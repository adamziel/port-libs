# real-upstream-corpus-date-affinity-dynamic-20260531T031230Z-0

Base accepted HEAD: `d3f35d53d135e23f73a270582d60d9916715bb54`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- `date.test` `date-19.1` through `date-19.53`

Implemented coverage:

- Added `SQLiteRealUpstreamCorpusDateAffinityDynamicFloorCeiling20260531T031230ZTest.php`.
- Covers SQLite date modifier `floor` / `ceiling` normalization for invalid month days, month shifts, year shifts, compound year-month-day shifts, and leap-year targets.
- Adds 1,155 focused TestRunner PASS cases and 4,593 behavior assertions.

Non-overlap:

- This owns `date.test` section 19 floor/ceiling normalization.
- It avoids accepted `date3` auto/unixepoch, `date4` strftime rows, `date20` no-round fractional truncation, `date2` modifier-index rows, and expression-affinity cast/type coverage.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicFloorCeiling20260531T031230ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicFloorCeiling20260531T031230ZTest.php`
  - Result: `1 test files, 4593 assertions, 0 failures`

Dependency closure:

- No new support component needed; this reuses `SQLiteCoreScalarFunction` date modifier normalization, floor/ceiling policy, strftime dispatch, and text return affinity.
