# real-upstream-corpus-upsert-returning-dynamic-20260531T000849Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsertfault.test`
  - `upsertfault-1`: restore database, inject OOM faults, then retry `INSERT ... ON CONFLICT(b,c) DO UPDATE SET d=d+1` without losing the final row image.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returningfault.test`
  - `returningfault-1`: `RETURNING (SELECT * FROM sqlite_temp_schema)` reports the scalar-subquery column-count error and leaves no inserted row behind.
  - `returningfault-2`: virtual-table `INSERT ... RETURNING *` either yields `hello world` or reports `vtable constructor failed: tcl` under fault injection, with cleanup.

## Implementation

- Added `SQLiteUpsertReturningFaultPlan`, a generic bounded model for these fault paths:
  - recoverable UPSERT OOM checkpoints around conflict lookup, row update, index rewrite, and statement reset;
  - RETURNING scalar-subquery error cleanup with stable temp schema state;
  - virtual-table RETURNING success/constructor-fault branches with released allocations.
- Added `SQLiteRealUpstreamUpsertReturningFaultDynamicTest.php` with 1003 TestRunner PASS cases and 7208 focused assertions.

## Non-Overlap

This does not repeat accepted `upsert2` trigger lifecycle/yield traces, `upsert3` literal `excluded` behavior, `upsert4` conflict-table-kind behavior, `upsert5` arm priority matrices, no-target duplicate rowid streams, SELECT-input UPSERT, autoincrement UPSERT, or correlated RETURNING subquery streams. This slice owns the upstream fault-injection cleanup/retry behavior from `upsertfault.test` and `returningfault.test`.

## Verification

- `php -l lanes/libsqlite/src/SQLiteUpsertReturningFaultPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningFaultDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsertReturningFaultDynamicTest.php` passed: `1 test files, 7208 assertions, 0 failures`, with 1003 PASS lines.

## Dashboard Delta

- Focused PASS-line delta: `+1003`.
- `lane-status.json` moves `phpPass` from `1292330` to `1293333`.
- `benchmarkDenominator.mapped` remains `1589 / 1589`; this is behavior growth over already mapped upstream corpus files, not new denominator inventory.

## Dependency Closure

No new support component is needed. The slice reuses lane-local PHP row-array UPSERT and RETURNING fault-state modeling; it does not require a live SQLite extension, Tcl faultsim runner, or external service.
