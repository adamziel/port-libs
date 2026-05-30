# real-upstream-corpus-date-affinity-dynamic-20260530T211129Z-0

- Base accepted HEAD: `bbccc1d8f736962c4f86ebb79411aec5c77c5f5a`.
- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date2.test`.
- Upstream scenario: `date2.test` section `date2-500`, deterministic `datetime(y,m)` partial-index modifier rows.
- Non-overlap: existing accepted coverage already has `date2-500` rows `1..4`; this slice owns rows `5..68` across the upstream modifier list (`+/-10 days`, `+/-10 hours`, `+/-10 minutes`, `+/-10 seconds`, `+/-10 months`, `+/-10 years`, `start of month`, `start of year`, `start of day`, `weekday 1`, and `unixepoch`).
- Focused growth: `1,090` distinct TestRunner PASS cases, `6,532` focused assertions.
- Verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicDate2ModifierRowsTest.php` passed with `1 test files, 6532 assertions, 0 failures`.
- Dependency closure: no new support component; this reuses `SQLiteCoreScalarFunction` date/time modifier and determinism behavior.
- Root harness: not run - isolated micro-slice.
