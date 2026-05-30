# real-upstream-corpus-date-affinity-dynamic-20260530T210116Z-0

- Base accepted HEAD: `c7f1da7bda346751170f57e7264f2081e65c2f0a`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`.
- Ported section: `date.test` `date-18.2`, `date-18.3`, `date-18.4`, and `date-18.5` subsec/subsecond behavior.
- Added focused PHP file: `lanes/libsqlite/tests/SQLiteRealUpstreamDateSubsecondDynamicCorpusTest.php`.
- Focused growth: 1206 distinct TestRunner PASS cases, 7213 behavior assertions.
- Non-overlap: avoids accepted date4/date5/date2/date3/date15, weekday, floor/ceiling, UTC/null, fraction-truncation, and statement-now batches. This slice targets subsecond unixepoch/julianday resolution and fractional unixepoch dynamic rows.
- Dependency closure: no new support component required; reuses `SQLiteCoreScalarFunction` date/time scalar behavior.
- Verification:
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDateSubsecondDynamicCorpusTest.php` passed.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateSubsecondDynamicCorpusTest.php` passed: `1 test files, 7213 assertions, 0 failures`.
