# real-upstream-corpus-date-affinity-dynamic-20260531T110931Z-0

Session: `port-dev-sqlite-yield-dyn-real-date-20260531T110931Z`
Base accepted HEAD: `efb7686a64aa17164b1273c5c931fa92a9a94c6c`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/timediff1.test`
- Scenario range: `timediff-4`, the `p1`/`p2` directional matrix:
  - `SELECT datetime($d2, timediff($d1,$d2));`
  - `SELECT datetime($d1, timediff($d2,$d1));`

## Behavior

- Fixed native compound date/time modifiers so `+YYYY-MM-DD HH:MM:SS.SSS`
  and `-YYYY-MM-DD HH:MM:SS.SSS` apply the fractional seconds component.
- Extended the modifier parser to accept the five-digit year fields generated
  by SQLite's own `timediff()` for extreme endpoint spans, for example
  `+14712-01-07 11:59:59.000`.
- Added a real upstream corpus test covering all 320 directional `timediff-4`
  cases, including negative years, Julian-day numeric input, duplicate `E`
  month-edge rows, leap days, the `9999-12-31 23:59:59` endpoint, and the
  fractional `4796-02-29 11:23:55.46` row.
- Each case is checked against a local `sqlite3` oracle for exact `timediff()`,
  `datetime(... timediff ...)`, `date()`, `time()`, `julianday()`, subsecond
  modifier application, and TEXT-affinity modifier storage.

## Verified Movement

- New focused test file:
  `SQLiteRealUpstreamCorpusDateAffinityDynamicTimediff4Roundtrip20260531T110931ZTest.php`
- Focused behavior assertions: `5133`
- Focused PASS cases in that file: `322`
- Countable movement: real PHP TestRunner assertion/PASS growth over already
  mapped upstream inventory; mapped denominator remains `1589 / 1589`.

## Non-overlap

This owns `timediff1.test` `timediff-4` only. It avoids accepted `timediff-3`
exact strings, `timediff-5` modifier grammar, `timediff-6` month-boundary
matrix, `date4` strftime rows, `date19` floor/ceiling, `date20` truncation,
`date3` auto/unixepoch, `date5` calendar cycle, `date-2.2c` millisecond
strftime, and expression-affinity shards.

## Verification

- Red-first before source fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicTimediff4Roundtrip20260531T110931ZTest.php`
  failed with `66` failures around fractional timediff modifier application,
  four/five-digit modifier parsing, and the initial negative-year date-prefix
  test bug.
- Focused after fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicTimediff4Roundtrip20260531T110931ZTest.php`
  passed with `1 test files, 5133 assertions, 0 failures`.
- Related date/timediff family plus API guard:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicTimediff4Roundtrip20260531T110931ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateTimediffDynamicMatrixTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicTimediff6Roundtrip20260531T100244ZTest.php lanes/libsqlite/tests/SQLiteDateTimeTimediffCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateSubsecondDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed with `6 test files, 21706 assertions, 0 failures`.
- `php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicTimediff4Roundtrip20260531T110931ZTest.php`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. The slice reuses native
`SQLiteCoreScalarFunction` date/time parsing and modifier application,
`SQLiteRealExpressionAffinityCorpusPlan` TEXT-affinity storage checks, and the
hydrated SQLite upstream checkout plus local `sqlite3` oracle for expected
values.
