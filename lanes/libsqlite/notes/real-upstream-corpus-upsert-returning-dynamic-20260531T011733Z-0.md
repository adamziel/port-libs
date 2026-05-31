# Real upstream corpus: UPSERT5 RETURNING yield

Slice: `real-upstream-corpus-upsert-returning-dynamic-20260531T011733Z-0`

Base accepted HEAD: `2541019b82319811accbb79790d214be59d31028`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`
- Ported sections: generalized UPSERT cases `1.$tn.100` through `1.$tn.505`
  for all six upstream table shapes.

Behavior covered:

- generalized `ON CONFLICT` arm priority with explicit primary-key and UNIQUE
  targets;
- targetless catch-all conflict arms;
- `DO NOTHING` arms before and after catch-all arms;
- repeated redundant conflict arms where the first matching arm wins;
- RETURNING yield behavior: changed rows yield one row, skipped `DO NOTHING`
  rows yield none;
- change counter parity with yielded RETURNING rows.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpstreamUpsert5ReturningYieldRealCorpusTest.php`
- Result: `1 test files, 1140 assertions, 0 failures`

Non-overlap:

- This does not repeat the accepted UPSERT/RETURNING SELECT-input,
  dynamic-plan/yield, target-analysis, priority-matrix, or trigger-old-value
  clusters. It ports the real `upsert5.test` generalized multi-arm conflict
  matrix into focused PHP behavior checks over the existing native
  `SQLiteUpsertDoUpdateWherePlan::executeConflictArmsWithYieldTrace()` path.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP
  UPSERT conflict-arm and RETURNING yield-trace primitives.
