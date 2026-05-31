# real-upstream-corpus-date-affinity-dynamic-20260531T045635Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows18400To19399Test.php` as an additive real upstream date/affinity corpus shard.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- Scenario range: `date4-18400` through `date4-19399` from the upstream `for {set i 0} {$i<=24858} {incr i}` loop.

Focused behavior:

- 1,000 distinct PHP TestRunner cases compare native `SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$format, $timestamp, 'unixepoch'])` against the same libc-style format expansion used by upstream `date4.test`.
- Each case also checks text timestamp affinity, real timestamp affinity, `typeof()` text result, field-count shape, and stable prefix/suffix formatting.
- Rollup cases cite the hydrated upstream source, assert the non-overlapping range, and record dependency closure.

Non-overlap:

- Owns `date4.test` rows `18400..19399`.
- Avoids accepted date4 rows `0..18399`, `date.test` modifier coverage, `date2.test` deterministic date guards, `date3.test` auto/unixepoch ambiguity coverage, `date5.test` Gregorian cycle coverage, and expression-affinity/cast/type-matrix batches.

Dependency closure:

- No new support component is needed. This reuses existing native `SQLiteCoreScalarFunction` date/time parsing, `strftime` formatting, Unix timestamp modifier dispatch, and generic scalar `typeof()` behavior.

Expected dashboard movement:

- Count as PASS-line growth only: 1,004 focused TestRunner PASS cases in the new file.
- Mapped denominator remains unchanged because `date4.test` is already mapped; this is accepted PHP corpus burnup within the hydrated upstream script.
