# real-upstream-corpus-upsert-returning-dynamic-20260531T062651Z-0

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
  - section `7.1`: `excluded` pseudo-table values win for `ON CONFLICT(z) DO UPDATE`.
  - section `7.2`: reordered primary-key conflict target `(y, x)` updates the current row.
  - section `7.3`: target-qualified `t1.w` resolves to the current row inside the update arm.
  - section `7.4`: target alias `tbl.w` resolves to the current row inside the update arm.

## Behavior Ported

- Added `SQLiteRealUpstreamUpsert4ReturningChainedDynamicTest.php`.
- The new file keeps generic `app_item` table names and executes 1000 deterministic chained section-7 sequences against both PDO SQLite and native `SQLiteUpsertReturningSql`.
- Each sequence checks that the `RETURNING` stream, final current row image, per-statement `changes()`, conflict-target parsing, target-alias binding, and chained current-row growth match SQLite.

## Non-Overlap

- This does not repeat the accepted one-operation `upsert4` section-7 target-alias file.
- This does not repeat the accepted excluded-table ambiguity, `upsert4` section-1 conflict/move behavior, `upsert4` section-2/3/4/5/6 target-admission and replace-precedence behavior, `upsert5` arm-priority matrices, `upsert2` SELECT-input yield matrices, trigger-old-value regression, row-value `UPDATE`/`DELETE RETURNING`, or recursive trigger/view `RETURNING` helpers.
- This slice owns the chained section-7 statement-current row image across multiple UPSERT/RETURNING statements.

## Focused Count

- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsert4ReturningChainedDynamicTest.php`
- Result: `1 test files, 4005 assertions, 0 failures`
- PASS-line growth in the focused file: 1003 TestRunner PASS cases.

## Dependency Closure

- No new support component is needed. The slice reuses `SQLiteUpsertReturningSql` target-alias binding, `excluded` pseudo-table evaluation, conflict-target parsing, and `RETURNING` projection.
