# Real Upstream Corpus Date Affinity Dynamic Julian Modifier

Slice: `real-upstream-corpus-date-affinity-dynamic-20260531T073249Z-0`

Base accepted HEAD: `9c30c680e4b44fbeb2fc11612b28622bb7d8e322`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- `date.test` ticket `#3618`
- Exact upstream rows: `date-13.11` through `date-13.24`

Behavior added:

- Adds `SQLiteRealUpstreamCorpusDateAffinityDynamicJulianModifier20260531T073249ZTest.php`.
- Covers REAL Julian day inputs to `julianday()` with signed fractional day, hour, minute, second, month, and year modifiers.
- Extends the exact upstream rows with 1,024 deterministic REAL Julian-day modifier cases for day/hour/minute/second deltas.
- Verifies REAL storage class preservation, modifier delta arithmetic, datetime text projection, and Julian-day round-trip behavior.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicJulianModifier20260531T073249ZTest.php`
- Result: `1 test files, 6208 assertions, 0 failures`
- PASS-line growth: `+1040`

Non-overlap:

- Avoids accepted `date4` row ranges, `date2`/`date3` schema and modifier-index batches, `date5` Gregorian-cycle rows, unixepoch fractions, timezone offsets, zero-hour `date-12`, leading-zero `strftime`, invalid `strftime`, and expression-affinity shards.

Dependency closure:

- No new support component needed.
- Reuses native `SQLiteCoreScalarFunction` `julianday()` / `datetime()` modifier dispatch.
