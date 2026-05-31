# real-upstream-corpus-date-affinity-dynamic-unixepoch-auto-20260531T021617Z

Slice: `real-upstream-corpus-date-affinity-dynamic-20260531T021617Z-0`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test`
- Ported subtests/sections: `date3-1.1` through `date3-1.8`, the dynamic `date3-1.7` unixepoch identity loop, `date3-2.1` through `date3-2.40`, `date3-3.1` through `date3-3.2`, `date3-4.1` through `date3-4.3`, and `date3-5.0`.

Coverage:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicUnixepochAuto20260531T021617ZTest.php`.
- Focused result: `1 test files, 3071 assertions, 0 failures`.
- Distinct TestRunner PASS cases: 1018.
- Non-overlap: existing date corpus files cover `date.test` fractional Julian `strftime('%W %j', ...)` behavior and `date4.test` libc-style `strftime()` formatting loops. This slice covers `date3.test` unixepoch integer behavior, numeric `auto` boundaries, immediate-position `unixepoch`/`julianday` modifier rules, text no-op `auto`, mixed known/unknown time-value handling, and the first-63-days-1970 auto ambiguity count.

Dependency closure:

- No new support component is needed. The slice reuses the existing `SQLiteCoreScalarFunction` date/time implementation and the hydrated upstream SQLite checkout as read-only source truth.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicUnixepochAuto20260531T021617ZTest.php`
