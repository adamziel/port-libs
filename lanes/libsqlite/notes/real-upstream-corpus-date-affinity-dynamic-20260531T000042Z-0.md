# real-upstream-corpus-date-affinity-dynamic-20260531T000042Z-0

- Base accepted HEAD: `8c83cd38b21e6ef37afec24c7a1c1aa06c561658`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`.
- Non-overlap: existing accepted coverage includes the first 300 date4 unixepoch samples in `SQLiteRealUpstreamDateAutoUnixepochModifierCorpusTest.php`; this slice owns `date4-300` through `date4-1299`.
- Behavior: native `SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$format, $timestamp, 'unixepoch'])` must match the portable date4 UTC date/time, day-of-year, and weekday formatting subset for 1000 additional upstream loop rows.
- Focused evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Continuation20260531T000042ZTest.php` => `1 test files, 2004 assertions, 0 failures`, with 1001 PASS lines.
- Expected dashboard movement: `phpPass` +1001, from `1262570` to `1263571`; mapped denominator remains `1589 / 1589`.
- Dependency closure: no new support component is needed; this reuses the existing native date/time scalar function implementation and the hydrated upstream SQLite date4 corpus.
- Root harness: not run - isolated micro-slice.
