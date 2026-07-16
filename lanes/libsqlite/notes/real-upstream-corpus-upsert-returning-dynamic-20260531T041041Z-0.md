# real-upstream-corpus-upsert-returning-dynamic-20260531T041041Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsertfault.test`
  - `upsertfault-1`: `INSERT INTO t1 VALUES(3,2,2,NULL) ON CONFLICT(b,c) DO UPDATE SET d=d+1` updates the conflicting row instead of inserting the incoming row.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returningfault.test`
  - `returningfault-1`: a scalar subquery in `RETURNING` that expands to five `sqlite_temp_schema` columns is rejected with `sub-select returns 5 columns - expected 1`.

## Behavior Ported

- Extended `SQLiteRealUpstreamUpsertReturningFaultDynamicTest.php`.
- The existing file already covered the production `SQLiteUpsertReturningFaultPlan` fault-recovery model; this slice preserves that coverage and adds native `SQLiteUpsertDoUpdateWherePlan` row-array checks for 1000 seeded variants of the real `upsertfault-1` conflict-update shape.
- Each seeded case asserts final row image, inserted/updated partitioning, `RETURNING` post-update row image, change accounting, and projected `RETURNING` scalar values.
- The same file adds focused `returningfault-1` arity checks for five-column scalar-subquery rejection and one-column scalar-subquery admission.

## Non-Overlap

- This does not repeat accepted `upsert4` conflict-target admission, `upsert5` arm-priority/yield matrices, `returning1-17` duplicate row streams, `returning1-20` correlated delete subquery recomputation, SELECT-source UPSERT, trigger/FK RETURNING, row-value RETURNING, or app-WAL/rowvalue conflict slices.
- This slice owns the upstream fault-family UPSERT conflict-update success path plus the `RETURNING` scalar-subquery arity guard.

## Focused Count

- Focused file total after the extension: 6006 TestRunner PASS cases and 14211 assertions.
- Additive focused movement over the accepted file shape: 5003 TestRunner PASS cases from the new seeded conflict-update and scalar-arity assertions.

## Dependency Closure

- No new support component is needed. The slice reuses the existing native PHP UPSERT executor and a bounded local scalar-subquery arity model for the cited `RETURNING` error behavior.
