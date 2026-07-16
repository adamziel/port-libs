# real-upstream-corpus-date-affinity-dynamic-20260531T034409Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicComponentValidation20260531T034409ZTest.php` as an additive real upstream date/affinity corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`
- Scenario names: `date-1.18.1` through `date-1.18.5`, `date-1.24` through `date-1.29`, `date-2.26` through `date-2.50`, plus the existing `affinity2-100` typed row setup.

Behavior covered:

- SQLite date/time text parsing accepts upstream's multiple space and `T` separator forms for `julianday()` / `datetime()`.
- SQLite date/time parsing rejects invalid component values that PHP's `DateTimeImmutable` would otherwise normalize, including minute `60`, second `60`, month `00`/`13`, and day `00`.
- Upstream second/minute/hour modifier behavior is checked across `affinity2.test` stored-type rows.
- The dynamic second-modifier expansion adds 1000 distinct TestRunner cases derived from the upstream `date-2.42` long-second modifier pattern.

Implementation:

- `SQLiteCoreScalarFunction::parseDateTimeState()` now validates month/day/hour/minute/second components before constructing `DateTimeImmutable`.
- Day overflow within `1..31` is still preserved for upstream normalization cases such as `datetime('2023-02-31')`.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicComponentValidation20260531T034409ZTest.php`
- Result: `1 test files, 6901 assertions, 0 failures`, with 1632 focused PASS cases.

Non-overlap:

- This does not repeat accepted date2 schema determinism, date2 row/modifier index batches, date3 unixepoch/auto/modifier placement, date4 row-range/real-date/strftime batches, date5 calendar cycles, invalid strftime conversions, floor/ceiling/month-matrix, UTC suffix/null, or expression-affinity `types2` predicate behavior.
- It fixes a parser-level invalid-component behavior gap exposed by real upstream `date.test`, then adds dynamic coverage around that corrected surface.

Dependency closure:

- No new support component is needed. This reuses native `SQLiteCoreScalarFunction` date/time parsing plus existing `SQLiteRealDateAffinityDynamicCorpusPlan` affinity row coercion.
