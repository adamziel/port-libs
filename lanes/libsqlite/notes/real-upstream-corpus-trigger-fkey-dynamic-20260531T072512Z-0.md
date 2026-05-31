Status: added a focused real-upstream trigger/FK dynamic corpus slice.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerC.test`
- Upstream scenarios: `triggerC-16.1`, `triggerC-16.2`, `triggerC-17.0`, and `triggerC-17.1`.

Behavior added:

- `RAISE()` outside trigger programs keeps SQLite's parse/semantic diagnostic order: an invalid `ORDER BY raise(...)` reports the ORDER BY term error before evaluating `RAISE()`, while `GROUP BY` / `HAVING` use of `RAISE()` reports that RAISE is trigger-program-only.
- A `BEFORE INSERT` trigger that reads `new.x` on an INTEGER PRIMARY KEY table does not mask or coerce a text primary-key value; the statement fails with `datatype mismatch` before the row becomes visible.

Focused assertion delta:

- New TestRunner file: `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTriggerCLateDiagnostics20260531Test.php`
- Adds 4,414 focused assertions over 220 dynamic variants plus hydrated-source and corpus metadata checks.

Non-overlap:

- This does not repeat accepted triggerC affinity timing, old/new lifecycle, recursion limit, constant-loop, quoted-target, trigger1 RAISE expression, trigger2 view/variable/count_changes, fkey5 checks, fkey8 actions, savepoint-boundary, or existing trigger/FK RETURNING/UPSERT coverage.
- The new surface is specifically late `triggerC.test` diagnostic ordering and INTEGER PRIMARY KEY datatype mismatch behavior with a BEFORE trigger.

Dependency closure:

- No new support component is needed. The slice reuses the existing upstream trigger/FK dynamic corpus plan surface and hydrated SQLite upstream checkout.
