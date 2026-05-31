# real-upstream-corpus-date-affinity-dynamic-20260531T015511Z-0

Base accepted HEAD: `5355cb7ecea35e8be7c9099c3c6dbf4e5ec09d23`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- Section: `date-20.4`, `datetime('2024-12-31 23:59:58.9995')` returns `2024-12-31 23:59:58`
- Context: 2025-01-21 SQLite forum case `766a2c9231`, fractional `.9995` is truncated to milliseconds and must not round the whole second upward.

Patch:

- Added `SQLiteRealUpstreamCorpusDateAffinityDynamicRealDate20NoRound20260531T015511ZTest.php`.
- Covers 1200 generated no-op modifier variants across leap, non-leap, epoch, upper-bound, ordinary, and source forum instants.
- Focused behavior assertions check `datetime`, `strftime('%Y-%m-%d %H:%M:%S')`, `date`, `time`, `strftime('%f')`, and text affinity.

Non-overlap:

- This owns `date.test date-20.4` non-59-second no-round behavior.
- It avoids earlier accepted `date20.1..20.3` rollover guards for `23:59:59.999*`, date4 strftime loop ranges, date2 unixepoch fractional rows, localtime/failure rows, and broad boundary-oracle rows.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicRealDate20NoRound20260531T015511ZTest.php`
- Result: `1 test files, 9606 assertions, 0 failures`
- New focused PASS lines: `1203`

Dependency closure:

- No new support component needed.
- Reuses `SQLiteCoreScalarFunction` date/time parser, fractional millisecond truncation, no-op modifier handling, and text-affinity return typing.
