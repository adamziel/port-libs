# Real Upstream UPSERT RETURNING Dynamic Target-First Batch

Micro-slice: `real-upstream-corpus-upsert-returning-dynamic-20260531T015821Z-0`

Accepted base: `5355cb7ecea35e8be7c9099c3c6dbf4e5ec09d23`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`

Covered upstream sections:

- `upsert1.test` `upsert1-700`, `upsert1-710`, `upsert1-720`
- `upsert1.test` `upsert1-730`, `upsert1-740`, `upsert1-750`
- `upsert1.test` `upsert1-760`, `upsert1-770`, `upsert1-780`
- `returning1.test` changed-row `RETURNING` post-image behavior

Patch summary:

- Added `SQLiteRealUpstreamUpsertReturningDynamicTargetFirstHelperTest.php`.
- Exercises 1000 deterministic target-first UPSERT cases through the existing native `SQLiteUpsertReturningDynamicCorpusPlan::upsert1TargetFirstReturningDynamicCases()` helper.
- Verifies selected conflict target priority, post-update final row image, `RETURNING` stream, update/skipped counts, rowid versus WITHOUT ROWID schema metadata, all-key conflict setup, and dependency closure.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicTargetFirstHelperTest.php
1 test files, 21003 assertions, 0 failures
```

PASS-line movement:

- New focused PASS lines: `8002`
- `lane-status.json` `phpPass`: `1566206 -> 1574208`
- Mapped denominator coverage unchanged: `1589 / 1589`

Dependency closure:

- No new support component is needed.
- Reuses native UPSERT conflict-arm execution, target constraint priority, and changed-row `RETURNING` projection behavior already implemented in libsqlite.

Non-overlap:

- Does not repeat accepted UPSERT excluded/statement-current behavior, select-input behavior, redundant-conflict integrity, partial-index dynamic cases, or the existing large duplicate-yield `returning1-17` stream.
- Adds direct helper-driven coverage for `upsert1.test` target-first priority sections over rowid, explicit unique-index, and WITHOUT ROWID schema families.
