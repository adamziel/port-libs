## real-upstream-corpus-trigger-fkey-dynamic-deferred-restrict-op-once-20260531

Base accepted HEAD: `e6f2f82c55065569a50189235fcdfbfbb9091c15`.

Added focused PHP coverage for real upstream SQLite trigger/FK behavior using the hydrated upstream source in `/home/claude/port-libs/.upstream-cache/libsqlite/test`:

- `fkey6.test` sections `fkey6-3.2.*`, `fkey6-3.3.*`, and `fkey6-4.1..4.2`: `PRAGMA defer_foreign_keys` defers `RESTRICT`, allows an AFTER DELETE trigger to repair a row before commit, and still fails commit when violations remain outstanding.
- `triggerF.test` sections `triggerF-1.1.0..1.4.2`: WITHOUT ROWID `DELETE`, `INSERT OR REPLACE`, and `UPDATE OR REPLACE` trigger log ordering for none/AFTER/BEFORE/BEFORE+AFTER DELETE triggers.
- `triggerG.test` sections `triggerG-100..110` and `triggerG-200`: recursive trigger `SELECT` programs rerun per recursive frame and keep `OP_Once` state frame-local for single-source and joined indexed SELECT shapes.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicDeferredRestrictOpOnceTest.php`
  - `1 test files, 5473 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This reuses existing generic trigger/FK behavior primitives in `SQLiteDynamicTriggerForeignKeyPlan` and cites hydrated real upstream SQLite `.test` files directly.

Non-overlap:

- Does not repeat the accepted trigger/FK capability/WITHOUT ROWID batch, fkey7 authorizer, fkey2/count_changes, triggerC affinity timing, trigger2 follow-up, WITHOUT ROWID3 count_changes, or previously accepted recursive/view/RETURNING trigger slices.
