# Real Upstream Corpus UPSERT RETURNING Dynamic Real Yield

- Session: `port-dev-sqlite-yield-dyn-real-upsert-20260531T034223Z`
- Base accepted HEAD: `ca2d3c3a4732734353ce27d70067c3ae40d81496`
- Slice: `real-upstream-corpus-upsert-returning-dynamic-20260531T034223Z-0`
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
    `upsert5-1.100` through `upsert5-1.505` generalized `ON CONFLICT` arm
    ordering.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
    `returning1-11` multi-row DML `RETURNING` stream ordering.

## Coverage

Added `SQLiteRealUpstreamUpsertReturningDynamicRealYieldCorpusTest.php`, a
PDO-backed real SQLite oracle corpus for dynamic multi-row UPSERT inputs with
ordered conflict arms, `WHERE`-gated `DO UPDATE`, `DO NOTHING`, catch-all arms,
and `RETURNING` row streams. The native path uses
`SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace()` and checks
that final rows, `RETURNING` streams, `changes()`, pre-yield events, and
non-null returning yield events match the oracle.

The batch adds 1,001 distinct TestRunner PASS cases and 1,201 assertions.
It is non-overlapping with the currently accepted target admission/target-first
UPSERT RETURNING batches because it focuses on PDO-oracle dynamic multi-row
yield streams across real `upsert5.test` arm-order families.

## Verification

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningDynamicRealYieldCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1201 assertions, 0 failures
```

Dependency closure: no new support component is needed. The test reuses the
existing native UPSERT/RETURNING conflict-arm planner plus the local PDO SQLite
oracle already used by nearby real-upstream corpus tests.
