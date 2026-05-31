# real-upstream-corpus-date-affinity-dynamic-20260531T054200Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicRealDateZeroHour20260531T054200ZTest.php`
as an additive real-upstream date-affinity corpus slice.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- Section: ticket `#1964`, `date-12.1` and `date-12.2`
- Behavior: `datetime('YYYY-MM-DD')` and `datetime('YYYY-MM-DD','+0 hours')`
  both normalize to midnight, with the zero-hour modifier preserving the same
  date/time value.

## Focused Coverage

- `1024` distinct dynamic calendar rows over years `0001` through `9999`.
- Each row verifies native PHP `datetime`, `date`, `time`, `julianday`,
  `strftime`, text-affinity padded date input, and result storage class.
- Expected focused movement: `1026` TestRunner PASS lines and `8200`
  assertions in one focused file.

## Non-Overlap

This slice owns the date.test ticket-1964 zero-hour datetime equivalence
cluster for this session. It avoids accepted date4 row ranges, date2/date3
schema and modifier-index batches, date5 Gregorian-cycle rows, unixepoch
fractions, timezone offsets, leading-zero strftime, invalid strftime,
component-validation, boundary date-13/date-16/date-17/date-19, and
expression-affinity shards.

## Dependency Closure

No new support component is needed. The test reuses native
`SQLiteCoreScalarFunction` date/time/julianday/strftime dispatch.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicRealDateZeroHour20260531T054200ZTest.php`
  - PASS: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicRealDateZeroHour20260531T054200ZTest.php`
  - PASS: `1 test files, 8200 assertions, 0 failures`.

Root harness not run; isolated micro-slice.
