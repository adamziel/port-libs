# real-upstream-corpus-upsert-returning-dynamic-20260531T022859Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
- Sections `upsert5-3.0` and `upsert5-3.3` through `upsert5-3.6`

Slice:

- Added `SQLiteRealUpstreamUpsertReturningRedundantConflictSeed1524Test.php`.
- Extends the accepted redundant-conflict dynamic corpus with seed range `1025-1524`, disjoint from accepted/default seed ranges `1-1024`.
- Covers 1,000 upstream-derived row-image cases: table scan parity, index scan parity, unique-key preservation, replacement accounting, and redundant `ON CONFLICT` arm bypass.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningRedundantConflictSeed1524Test.php`
- Result: `1 test files, 14002 assertions, 0 failures`
- PASS lines: `4001`
- Expected selected movement: `1697468 -> 1701469 pass / 0 fail`

Dependency closure:

- No new support component needed. This reuses the existing native PHP `SQLiteUpsertReturningDynamicCorpusPlan::redundantConflictIntegrityCases()` corpus generator and row-image/index-parity model.

Root harness:

- Not run; isolated micro-slice.
