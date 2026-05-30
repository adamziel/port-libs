# real-upstream-corpus-trigger-fkey-dynamic-20260530T181939Z-0

Status: ready behavior patch for real upstream trigger/FK dynamic action coverage.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test`
- Sections 4.3 `e_fkey-39.2`, `e_fkey-39.3`, `e_fkey-42.2`, `e_fkey-42.3`, `e_fkey-42.5`, `e_fkey-42.6`, `e_fkey-44.4`, `e_fkey-45.2`, `e_fkey-46.2`, and `e_fkey-47.2`.
- The matrix covers ON UPDATE/ON DELETE `SET DEFAULT`, `SET NULL`, `CASCADE`, `NO ACTION`, and `RESTRICT`, immediate/deferred modes, and the upstream rule from `e_fkey-42` that `NO ACTION` can be repaired by AFTER triggers while `RESTRICT` is enforced immediately.
- The recursive trigger settings `0`, `1`, `ON`, and `OFF` are represented to mirror the trigger/FK dynamic pragma dimension without changing FK action behavior.

Implementation:

- `SQLiteTriggerForeignKeyReturningPlan` now defers non-RESTRICT immediate FK failure until after AFTER triggers run for parent UPDATE and DELETE paths.
- This matches upstream `e_fkey-42.3` and `e_fkey-42.6`, where AFTER triggers repair `NO ACTION` child rows before statement-end FK checking.
- `RESTRICT` still throws before AFTER triggers, preserving upstream `e_fkey-42.2` and `e_fkey-42.5`.

Focused coverage:

- Added `SQLiteRealUpstreamCorpusTriggerFkeyDynamicActionTest.php`.
- The test produces 1,750 distinct TestRunner PASS cases and 7,000 behavior assertions from the upstream action matrix.
- Non-overlap: this does not repeat accepted trigger/FK savepoint, OR REPLACE, RETURNING, recursive view trigger, PRAGMA foreign-key catalog, or prior dynamic trigger/FK batches. The new surface is the e_fkey section 4.3 timing distinction between NO ACTION statement checks and immediate RESTRICT checks with AFTER-trigger repair.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerForeignKeyReturningPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusTriggerFkeyDynamicActionTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusTriggerFkeyDynamicActionTest.php`
  - Result: `1 test files, 7000 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. This reuses the lane-local trigger/FK RETURNING planner and in-memory row-array execution primitives.
