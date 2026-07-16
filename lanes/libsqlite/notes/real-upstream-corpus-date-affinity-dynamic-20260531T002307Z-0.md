# real-upstream-corpus-date-affinity-dynamic-20260531T002307Z-0

Base accepted HEAD: `aab498f11db56174605363e36ca7a662eb3a6384`.

Implemented one real upstream corpus slice from `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`.

Owned upstream range:

- `date4.test` loop rows `date4-01300` through `date4-02299`
- SQL shape: `SELECT strftime($::FMT,$::TS,'unixepoch')`
- Format shape: `%d,%e,%F,%H,%k,%I,%l,%j,%m,%M,%u,%w,%W,%Y,%%,%P,%p,%U,%V,%G,%g`

Non-overlap:

- Existing accepted `SQLiteDateTimeStrftimeDate4CorpusTest.php` covers `date4-0..999`.
- Existing accepted `SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Continuation20260531T000042ZTest.php` covers `date4-300..1299`.
- This handoff starts at `date4-1300`, so its `1000` generated upstream rows do not overlap the continuation file.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4RealDate20260531T002307ZTest.php`
- Result: `1 test files, 5006 assertions, 0 failures`
- Distinct TestRunner PASS cases: `1002`
- Expected selected `phpPass` movement: `1331008 -> 1332010`

Dependency closure:

- No new support component is needed. The slice reuses existing native `SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ...)` date/time behavior and PHP `DateTimeImmutable` only as the expected-value oracle inside focused tests.

Root harness:

- Not run; isolated micro-slice.
