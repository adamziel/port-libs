# real-upstream-corpus-date-affinity-dynamic-20260531T001430Z

- Base accepted HEAD: `a90bd8ebc7d2ac86175490c2392e0f42be214ce6`.
- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`.
- Owned upstream range: `date4-1300` through `date4-2299`.
- Behavior: `strftime('%Y-%m-%d %H:%M:%S %j %w %U %W', TS, 'unixepoch')` for 1000 distinct upstream `TS = i * 86390` values.
- Focused result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Continuation20260531T001430ZTest.php` passed with `1 test files, 3006 assertions, 0 failures` and `1001` PASS lines.
- Expected dashboard movement if accepted: `phpPass +1001`; mapped denominator remains `1589 / 1589`.
- Non-overlap: extends the accepted/current date4 continuation surface beyond the existing `date4-300..1299` focused file and does not touch prior date4/date5/date2 modifier, unixepoch fraction, UTC suffix, or affinity3 coverage.
- Dependency closure: no new support component is needed; this reuses `SQLiteCoreScalarFunction::sqlFunctionArguments()` and the hydrated upstream SQLite corpus.
