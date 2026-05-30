# real-upstream-corpus-date-affinity-dynamic-20260530T183421Z-0

Slice: `real-upstream-corpus-date-affinity-dynamic-20260530T183421Z-0`

Base accepted HEAD: `365df791b359e0dd925a461a6d36ddf8a8d0f5f1`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- Ported sections:
  - `date-9.1..9.7`: negative years and Julian-day round trips.
  - `date-13.2..13.37`: fractional second formatting and fractional day/month/year modifiers.
  - `date-16.1..16.31`: SQLite supported date/time range boundaries.
  - `date-17.1..17.7`: `start of day/month/year` behavior near lower and ordinary boundaries.

## Behavior

`SQLiteCoreScalarFunction` now rejects numeric Julian-day inputs and modifier results outside SQLite's supported date/time range instead of formatting underflowed dates or year `10000`. This fixes upstream-shaped cases such as:

- `date(147483649)` returning `NULL`.
- `datetime(0,'+464269060800 seconds')` returning `NULL`.
- `datetime(37,'start of year')` returning `NULL`.

The new focused corpus adds `2073` assertions, including generated overflow and underflow sweeps derived from the upstream `date-16` boundary rows. It claims focused PHP PASS/assertion growth only; mapped denominator coverage is unchanged.

## Non-Overlap

This slice does not repeat accepted `date2` deterministic schema guards, `date3` auto/unixepoch placement coverage, `date4` strftime parity, `date5` leap-cycle conversion, fractional Unix epoch millisecond coverage, or date floor/ceiling normalization. It owns the remaining `date.test` boundary behavior around lower/upper SQLite date admission after modifiers.

## Verification

- `php -l lanes/libsqlite/src/SQLiteCoreScalarFunction.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamDateBoundaryDynamicCorpusTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateBoundaryDynamicCorpusTest.php` -> `1 test files, 2073 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateBoundaryDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAutoUnixepochModifierCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateFloorCeilingDynamicCorpusTest.php` -> `3 test files, 3889 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` -> passed.

## Dependency Closure

No new support component is needed. The slice reuses native PHP date/time scalar helpers and tightens their SQLite range admission.

## Next Task

Continue date/affinity corpus work only on a non-overlapping upstream section, preferably source-level affinity comparison behavior from `affinity2.test` / `types*.test` that is not already covered by the accepted matrix batches.
