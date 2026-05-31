# real-upstream-corpus-date-affinity-dynamic-20260531T064107Z-0

- Base accepted HEAD: `adb26e7f16ecd89937cf2d16ad3f15841131934b`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`.
- Owned upstream scenarios: `date.test` `date-10.1`, `date-10.2`, and `date-10.3`, where bare `HH:MM:SS` inputs use the default date `2000-01-01` across `datetime()`, `date()`, and `strftime('%Y-%m-%d %H:%M', ...)`.
- Focused expansion: 864 generated `HH:MM:SS` rows across 24 hours, selected minute/second boundaries, type-affinity checks for returned text values, 8 malformed time-only rejection rows, and 2 source/non-overlap guard rows.
- Focused result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicTimeOnlyDefault20260531T064107ZTest.php` passed with `1 test files, 6951 assertions, 0 failures`.
- PASS-line delta: +874 focused TestRunner PASS cases.
- Non-overlap: this owns only `date.test` date-10 time-only default-date behavior. It avoids accepted date-2 modifiers, date-3 strftime extended rows, date-4 loops, date-5 timezone, date-8 now modifiers, date-11 HH:MM modifiers, date-13 fractional modifiers, date-19 floor/ceiling, date-20 truncation, and affinity2/3 expression batches.
- Dependency closure: no new support component is needed; this reuses existing `SQLiteCoreScalarFunction` date/time dispatch.
