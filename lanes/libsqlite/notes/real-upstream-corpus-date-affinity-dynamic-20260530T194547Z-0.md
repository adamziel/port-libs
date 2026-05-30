# Real Upstream Date Affinity Dynamic Auto Corpus

Slice: `real-upstream-corpus-date-affinity-dynamic-20260530T194547Z-0`

Base accepted HEAD: `4fa72fa71b26a19fe54f9ce85268cd96396282ab`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`

Ported sections:

- `date3.test` `date3-2.40`: `auto` modifier equivalence for text, Julian-day, and Unix-epoch time-values.
- `date3.test` `date3-2.30`, `date3-3.1..3.2`, and `date3-4.1..4.3`: text no-op and immediate `unixepoch`/`julianday` modifier guards.
- `date.test` `date-19.40..19.45` and `date-19.50..19.52`: `floor`/`ceiling` month and compound year-month ambiguity resolution.

## Focused Coverage

Added `SQLiteRealUpstreamDateAffinityDynamicAutoCorpusTest.php` with 1,015 TestRunner PASS cases and 11,067 behavior assertions.

The 1,000 dynamic cases are distinct day offsets over the `date3-2.40` equivalence shape. Each case verifies the same civil datetime is reached from:

- text time-value plus `auto`;
- Julian-day numeric plus `auto`;
- Unix-epoch integer plus `auto`;
- explicit `julianday` and `unixepoch` interpretations.

## Non-Overlap

This does not repeat the existing `SQLiteRealUpstreamDateAffinityDynamicNextCorpusTest.php` fractional `strftime(..., 'unixepoch')` millisecond bucket coverage or the existing date modifier batch over `date.test` `date-13.x` day/hour/minute/second offsets.

No production source was changed and no domain-specific libsqlite API was added.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicAutoCorpusTest.php`
  - `1 test files, 11067 assertions, 0 failures`
  - 1,015 selected PASS lines

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP date/time scalar functions and the focused TestRunner.
