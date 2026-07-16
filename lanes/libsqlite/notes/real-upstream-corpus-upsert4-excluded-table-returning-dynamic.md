# real-upstream-corpus-upsert-returning-dynamic-20260531T044540Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
- Upstream scenarios: `upsert4-8.1`, `upsert4-8.2`, `upsert4-8.3`, and `upsert4-8.4`.

Ported behavior:

- A table literally named `excluded` binds `excluded.w` to the current target row when the INSERT has no target alias.
- Adding a target alias makes `excluded.w` and `excluded.x` bind to the incoming UPSERT row.
- Quoted conflict targets such as `[a b]` continue to match the composite unique constraint.
- `DO UPDATE ... WHERE` false predicates suppress both mutation and `RETURNING` rows.
- The native `SQLiteUpsertReturningSql` path is compared to a local SQLite PDO oracle for RETURNING stream, final table image, and change count.

Focused growth:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamUpsert4ExcludedTableReturningDynamicTest.php`.
- 250 deterministic seeds x 4 upstream section variants x 4 focused assertions = 4000 focused behavior assertions, plus source coverage and dependency closure assertions.
- Non-overlap: this targets upstream `upsert4.test` section 8 excluded-table alias binding with RETURNING parity. It does not repeat the recently accepted `UPSERT3 returning composite`, broad `upsert5` arm-priority matrix, large duplicate-yield stream, or redundant-conflict integrity batches.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamUpsert4ExcludedTableReturningDynamicTest.php`
- Result: `1 test files, 4002 assertions, 0 failures`

Dependency closure:

- No new support component needed; this reuses lane-local `SQLiteUpsertReturningSql` quoted conflict targets, excluded-table alias binding, WHERE filtering, and RETURNING projection.
