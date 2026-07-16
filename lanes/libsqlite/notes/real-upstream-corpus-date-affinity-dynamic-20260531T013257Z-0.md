# real-upstream-corpus-date-affinity-dynamic-20260531T013257Z-0

- Base accepted HEAD: `472430c1daaad1016852e97d68cabd3ea687d289`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`.
- Ported upstream behavior: `date.test` `date-20.1`, `date-20.2`, and `date-20.3`, covering fractional-second `datetime()` truncation near `23:59:59.999...` without rolling over.
- PHP test added: `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicFractionalTruncation20260531T013257ZTest.php`.
- Focused growth: `1126` TestRunner PASS cases, `5619` behavior assertions.
- Non-overlap: avoids accepted `date-2.2c` unixepoch fractional `strftime`, date4 libc `strftime` rows, date11/date13 modifier matrices, and date18 subsecond/unixepoch corpus.
- Dependency closure: no new support component needed; this reuses `SQLiteCoreScalarFunction` date parsing, `datetime`, `strftime`, and `typeof` return-affinity behavior.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicFractionalTruncation20260531T013257ZTest.php`
  - Result: `1 test files, 5619 assertions, 0 failures`.
