# Real upstream corpus date fractional unixepoch

Slice: `real-upstream-corpus-date-affinity-dynamic-20260530T162413Z-0`

Behavior:
- Ports hydrated upstream `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test` scenario range `date-2.2c-0` through `date-2.2c-999`.
- Covers the upstream dynamic fractional Unix-epoch loop:
  `strftime('%H:%M:%f', 1237962480.%03d, 'unixepoch')` must preserve each millisecond bucket from `.000` through `.999`.
- Adds one generic application queue-bucket assertion proving text numeric affinity keeps millisecond scheduling labels stable without domain-specific table or API names.

Verification:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateFractionalUnixepochCorpusTest.php`
  - `1 test files, 1001 assertions, 0 failures`

Dashboard delta:
- `phpPass`: `188568 -> 189569` from 1001 newly passing focused PHP TestRunner cases.
- `mapped` coverage unchanged at `958 / 1589`; this patch does not claim new denominator rows or release/all parity.

Dependency closure:
- No new support component is needed. The patch reuses `SQLiteCoreScalarFunction` and the existing PHP test harness.

Non-overlap:
- This owns only the upstream `date.test` `date-2.2c-*` fractional Unix-epoch loop, which was not in the prior accepted `SQLiteRealUpstreamDateAffinityDynamicCorpusTest.php` subset.
- It avoids VFS, window, B-tree, suite-denominator metadata, runner-map admission, and source-neutral API work.
