# real-upstream-corpus-upsert-returning-dynamic-20260531T021423Z-0

Base accepted HEAD: `b8677cf94d5b050eacc055d83ba1f29b3739b6f1`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
- Covered sections:
  - `upsert5.test` redundant ON CONFLICT target integrity around section `3.0`
  - `upsert5.test` redundant target table/index consistency sections `3.3` through `3.6`

## Patch

- Added `SQLiteRealUpstreamUpsertReturningRedundantConflictExtendedTailTest.php`.
- The test extends the existing accepted redundant-conflict seed range from `1..524` to the non-overlapping tail range `525..1024`.
- Each seed validates the native `SQLiteUpsertReturningDynamicCorpusPlan::redundantConflictIntegrityCases()` row image, redundant conflict-arm bypass, and maintained-index agreement after REPLACE-like conflict handling.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningRedundantConflictExtendedTailTest.php`
  - `1 test files, 8501 assertions, 0 failures`
  - `1501` PASS lines

## Status Delta

- `phpPass`: `1638574` -> `1640075` (`+1501`)
- `benchmarkDenominator.mapped`: unchanged at `1589 / 1589`
- Root harness: not run - isolated micro-slice

## Non-overlap

- Avoids accepted redundant-conflict seed ranges `1..524`.
- Does not repeat accepted `upsert2`, `upsert3`, `upsert4`, `upsertfault`, or returning fault behavior.
- Adds no new WordPress/wp source text and no production API.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP UPSERT/RETURNING corpus planning helpers and the lane test runner.
